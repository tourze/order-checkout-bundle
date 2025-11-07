<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Contract\CouponProviderInterface;
use Tourze\OrderCheckoutBundle\Event\ExternalCouponRequestedEvent;

/**
 * 优惠券提供者责任链
 * 管理多个优惠券提供者，按优先级依次尝试，最后分发事件
 */
class CouponProviderChain
{
    /** @var CouponProviderInterface[] */
    private array $providers = [];

    public function __construct(
        #[TaggedIterator('coupon.provider')] iterable $providers,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null
    ) {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    /**
     * 添加优惠券提供者
     */
    public function addProvider(CouponProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * 根据优惠券码查找优惠券VO
     */
    public function findByCode(string $code, UserInterface $user): ?CouponVO
    {
        // 首先尝试所有注册的提供者
        foreach ($this->providers as $provider) {
            if (!$provider->supports($code)) {
                continue;
            }

            try {
                $vo = $provider->findByCode($code, $user);
                if (null !== $vo) {
                    $this->logger?->debug('优惠券由提供者解析', [
                        'code' => $code,
                        'provider' => $provider->getIdentifier(),
                        'user' => $user->getUserIdentifier(),
                    ]);
                    return $vo;
                }
            } catch (\Throwable $e) {
                $this->logger?->warning('优惠券提供者查找失败', [
                    'code' => $code,
                    'provider' => $provider->getIdentifier(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 如果所有提供者都找不到，分发事件尝试外部解析
        $event = new ExternalCouponRequestedEvent($code, $user);
        $this->eventDispatcher->dispatch($event);

        if ($event->isResolved()) {
            $this->logger?->debug('优惠券由外部事件解析', [
                'code' => $code,
                'user' => $user->getUserIdentifier(),
            ]);
            return $event->getCouponVO();
        }

        $this->logger?->debug('优惠券未找到', [
            'code' => $code,
            'user' => $user->getUserIdentifier(),
        ]);
        return null;
    }

    /**
     * 锁定优惠券
     */
    public function lock(string $code, UserInterface $user): bool
    {
        $provider = $this->findSupportingProvider($code);
        if (null === $provider) {
            $this->logger?->warning('无法找到支持的优惠券提供者进行锁定', ['code' => $code]);
            return false;
        }

        try {
            $result = $provider->lock($code, $user);
            $this->logger?->debug('优惠券锁定结果', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'result' => $result,
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券锁定失败', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 解锁优惠券
     */
    public function unlock(string $code, UserInterface $user): bool
    {
        $provider = $this->findSupportingProvider($code);
        if (null === $provider) {
            $this->logger?->warning('无法找到支持的优惠券提供者进行解锁', ['code' => $code]);
            return false;
        }

        try {
            $result = $provider->unlock($code, $user);
            $this->logger?->debug('优惠券解锁结果', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'result' => $result,
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券解锁失败', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 核销优惠券
     *
     * @param array<string, mixed> $metadata
     */
    public function redeem(string $code, UserInterface $user, array $metadata = []): bool
    {
        $provider = $this->findSupportingProvider($code);
        if (null === $provider) {
            $this->logger?->warning('无法找到支持的优惠券提供者进行核销', ['code' => $code]);
            return false;
        }

        try {
            $result = $provider->redeem($code, $user, $metadata);
            $this->logger?->debug('优惠券核销结果', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'result' => $result,
                'metadata' => $metadata,
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券核销失败', [
                'code' => $code,
                'provider' => $provider->getIdentifier(),
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * 查找支持该优惠券码的提供者
     */
    private function findSupportingProvider(string $code): ?CouponProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($code)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * 获取所有提供者
     *
     * @return CouponProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}