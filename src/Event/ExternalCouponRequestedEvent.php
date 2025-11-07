<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Event;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;

/**
 * 外部优惠券请求事件
 * 当本地找不到优惠券时分发此事件，允许外部系统提供优惠券VO
 */
class ExternalCouponRequestedEvent extends Event
{
    private ?CouponVO $couponVO = null;

    public function __construct(
        private readonly string $code,
        private readonly UserInterface $user
    ) {
    }

    /**
     * 获取优惠券码
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * 获取用户
     */
    public function getUser(): UserInterface
    {
        return $this->user;
    }

    /**
     * 获取优惠券VO
     */
    public function getCouponVO(): ?CouponVO
    {
        return $this->couponVO;
    }

    /**
     * 设置优惠券VO
     * 事件监听器应该调用此方法来提供优惠券VO
     */
    public function setCouponVO(?CouponVO $couponVO): void
    {
        $this->couponVO = $couponVO;
    }

    /**
     * 检查是否已解析到优惠券
     */
    public function isResolved(): bool
    {
        return null !== $this->couponVO;
    }
}