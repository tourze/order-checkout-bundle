<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Entity\Code;
use Tourze\CouponCoreBundle\Repository\CodeRepository;
use Tourze\CouponCoreBundle\Service\CouponVOFactory;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Contract\CouponProviderInterface;

/**
 * 本地优惠券提供者
 * 负责处理数据库中的优惠券Entity
 */
#[AutoconfigureTag(name: 'coupon.provider')]
#[WithMonologChannel(channel: 'order_checkout')]
class LocalCouponProvider implements CouponProviderInterface
{
    /** @var array<string, Code> 临时缓存已锁定的优惠券 */
    private array $lockedCodes = [];

    public function __construct(
        private readonly CodeRepository $codeRepository,
        private readonly CouponVOFactory $couponVOFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function findByCode(string $code, UserInterface $user): ?CouponVO
    {
        $codeEntity = $this->codeRepository->findOneBy([
            'owner' => $user,
            'sn' => $code,
            'valid' => true,
        ]);

        if (null === $codeEntity) {
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

        if ($codeEntity->isLocked()) {
            $this->logger?->warning('优惠券已被锁定', [
                'code' => $code,
                'codeId' => $codeEntity->getId(),
            ]);

            return false;
        }

        try {
            $codeEntity->setLocked(true);
            $this->entityManager->persist($codeEntity);
            $this->entityManager->flush();

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
            $codeEntity->setLocked(false);
            $this->entityManager->persist($codeEntity);
            $this->entityManager->flush();

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
            $codeEntity->setValid(false);
            $codeEntity->setLocked(false);
            $codeEntity->setRedeemTime(new \DateTimeImmutable());

            // 设置核销元数据
            if (!empty($metadata)) {
                $existingMetadata = $codeEntity->getMetadata() ?? [];
                $codeEntity->setMetadata(array_merge($existingMetadata, $metadata));
            }

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
        return $this->codeRepository->findOneBy([
            'owner' => $user,
            'sn' => $code,
            'valid' => true,
        ]);
    }
}
