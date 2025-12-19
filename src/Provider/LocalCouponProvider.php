<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Entity\Code;
use Tourze\CouponCoreBundle\Exception\CodeNotFoundException;
use Tourze\CouponCoreBundle\Service\CouponService;
use Tourze\CouponCoreBundle\Service\CouponVOFactory;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Contract\CouponProviderInterface;

/**
 * 本地优惠券提供者
 * 负责处理数据库中的优惠券Entity
 */
#[AutoconfigureTag(name: 'coupon.provider')]
#[WithMonologChannel(channel: 'order_checkout')]
final class LocalCouponProvider implements CouponProviderInterface
{
    /** @var array<string, Code> 临时缓存已锁定的优惠券 */
    private array $lockedCodes = [];

    public function __construct(
        private readonly CouponService $couponService,
        private readonly CouponVOFactory $couponVOFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function findByCode(string $code, UserInterface $user): ?CouponVO
    {
        $codeEntity = $this->findCodeEntity($code, $user);
        if (null === $codeEntity) {
            return null;
        }

        if (!$codeEntity->isValid()) {
            return null;
        }

        try {
            return $this->couponVOFactory->createFromCouponCode($codeEntity);
        } catch (\Throwable $e) {
            $this->logger?->error('创建优惠券VO失败', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function lock(string $code, UserInterface $user): bool
    {
        $codeEntity = $this->findCodeEntity($code, $user);
        if (null === $codeEntity) {
            return false;
        }

        if (!$codeEntity->isValid()) {
            return false;
        }

        if ($codeEntity->isLocked()) {
            $this->logger?->warning('优惠券已被锁定', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
            ]);

            return false;
        }

        try {
            $this->couponService->lockCode($codeEntity);

            // 缓存已锁定的代码
            $this->lockedCodes[$code] = $codeEntity;

            $this->logger?->debug('优惠券锁定成功', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券锁定失败', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function unlock(string $code, UserInterface $user): bool
    {
        // 先从缓存中查找
        $codeEntity = $this->lockedCodes[$code] ?? null;
        if (null === $codeEntity) {
            $codeEntity = $this->findCodeEntity($code, $user);
        }

        if (null === $codeEntity) {
            // 解锁不存在的券不算失败
            return true;
        }

        try {
            $this->couponService->unlockCode($codeEntity);

            // 清理缓存
            unset($this->lockedCodes[$code]);

            $this->logger?->debug('优惠券解锁成功', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券解锁失败', [
                'code' => $code,
                'codeId' => $codeEntity->getId() ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function redeem(string $code, UserInterface $user, array $metadata = []): bool
    {
        // 先从缓存中查找
        $codeEntity = $this->lockedCodes[$code] ?? null;
        if (null === $codeEntity) {
            $codeEntity = $this->findCodeEntity($code, $user);
        }

        if (null === $codeEntity) {
            $this->logger?->error('核销失败：优惠券不存在', ['code' => $code]);

            return false;
        }

        if (!$codeEntity->isLocked()) {
            $this->logger?->error('核销失败：优惠券未锁定', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
            ]);

            return false;
        }

        try {
            // 设置核销备注（元数据以序列化形式记录到备注中）
            if ($metadata !== []) {
                $existingRemark = $codeEntity->getRemark() ?? '';
                $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
                $codeEntity->setRemark($existingRemark . ($existingRemark !== '' ? "\n" : '') . "[metadata]: " . $metadataJson);
            }

            // 核销券码（会设置 useTime）
            $this->couponService->redeemCode($codeEntity);

            // 核销成功后解锁券码
            // 注意：不能调用 unlockCode，因为它会检查 useTime，已核销的券码会抛出 CodeUsedException
            // 因此直接更新 locked 状态
            $codeEntity->setLocked(false);
            $this->entityManager->persist($codeEntity);
            $this->entityManager->flush();

            // 清理缓存
            unset($this->lockedCodes[$code]);

            $this->logger?->debug('优惠券核销成功', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
                'metadata' => $metadata,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券核销失败', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);

            return false;
        }
    }

    public function supports(string $code): bool
    {
        // 本地提供者作为默认提供者，支持所有格式的券码
        // TODO 本地暂未实现
        return false;
    }

    public function getIdentifier(): string
    {
        return 'local';
    }

    /**
     * 查找优惠券码实体
     */
    private function findCodeEntity(string $code, UserInterface $user): ?Code
    {
        try {
            return $this->couponService->getCodeDetail($user, $code);
        } catch (CodeNotFoundException) {
            return null;
        }
    }
}
