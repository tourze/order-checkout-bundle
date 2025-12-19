<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Event;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * 订单完成事件
 * 在ProcessCheckoutProcedure成功完成下单后分发
 */
final class OrderCreateAfterEvent extends Event
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $orderNumber,
        private readonly UserInterface $user,
        private readonly float $totalAmount,
        private readonly bool $paymentRequired,
        private readonly string $orderState,
        private readonly array $metadata = []
    ) {
    }

    /**
     * 获取订单ID
     */
    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * 获取订单编号
     */
    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    /**
     * 获取下单用户
     */
    public function getUser(): UserInterface
    {
        return $this->user;
    }

    /**
     * 获取订单总金额
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    /**
     * 是否需要支付
     */
    public function isPaymentRequired(): bool
    {
        return $this->paymentRequired;
    }

    /**
     * 获取订单状态
     */
    public function getOrderState(): string
    {
        return $this->orderState;
    }

    /**
     * 获取附加元数据
     * 
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 获取特定元数据值
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 检查是否为特定类型的订单
     */
    public function isOrderType(string $type): bool
    {
        return $this->getMetadataValue('orderType') === $type;
    }

    /**
     * 是否为兑换券订单
     */
    public function isRedeemOrder(): bool
    {
        return $this->isOrderType('redeem');
    }

    /**
     * 是否为正常订单
     */
    public function isNormalOrder(): bool
    {
        return $this->isOrderType('normal');
    }

    /**
     * 获取使用的优惠券代码
     * 
     * @return string[]
     */
    public function getCouponCodes(): array
    {
        return $this->getMetadataValue('appliedCoupons', []);
    }

    /**
     * 是否使用了优惠券
     */
    public function hasCoupons(): bool
    {
        return $this->getCouponCodes() !== [];
    }

    /**
     * 获取收货地址ID
     */
    public function getAddressId(): ?int
    {
        return $this->getMetadataValue('addressId');
    }

    /**
     * 获取使用的积分数量
     */
    public function getPointsUsed(): int
    {
        return $this->getMetadataValue('pointsToUse', 0);
    }

    /**
     * 是否使用了积分
     */
    public function hasPointsUsed(): bool
    {
        return $this->getPointsUsed() > 0;
    }

    /**
     * 获取订单备注
     */
    public function getOrderRemark(): ?string
    {
        return $this->getMetadataValue('orderRemark');
    }

    /**
     * 获取库存警告信息
     * 
     * @return array<string, mixed>
     */
    public function getStockWarnings(): array
    {
        return $this->getMetadataValue('stockWarnings', []);
    }

    /**
     * 是否有库存警告
     */
    public function hasStockWarnings(): bool
    {
        return $this->getStockWarnings() !== [];
    }
}