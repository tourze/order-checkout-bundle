<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;

/**
 * 优惠券提供者接口
 * 统一本地和外部优惠券的操作接口
 */
interface CouponProviderInterface
{
    /**
     * 根据优惠券码查找优惠券VO
     */
    public function findByCode(string $code, UserInterface $user): ?CouponVO;

    /**
     * 锁定优惠券
     */
    public function lock(string $code, UserInterface $user): bool;

    /**
     * 解锁优惠券
     */
    public function unlock(string $code, UserInterface $user): bool;

    /**
     * 核销优惠券
     * 
     * @param array<string, mixed> $metadata 核销元数据（如订单ID、订单号等）
     */
    public function redeem(string $code, UserInterface $user, array $metadata = []): bool;

    /**
     * 判断是否支持该优惠券码
     * 用于多提供者场景下的路由判断
     */
    public function supports(string $code): bool;

    /**
     * 获取提供者标识
     */
    public function getIdentifier(): string;
}