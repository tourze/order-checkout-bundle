<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 结算结果
 */
final class CheckoutResult
{
    /**
     * @param array<mixed> $items            购物车商品
     * @param string[]     $appliedCoupons   应用的优惠券
     */
    public function __construct(
        private readonly array $items = [],
        private readonly ?PriceResult $priceResult = null,
        private readonly ?ShippingResult $shippingResult = null,
        private readonly ?StockValidationResult $stockValidation = null,
        private readonly array $appliedCoupons = [],
        private readonly ?int $orderId = null,
        private readonly ?string $orderSn = null,
        private readonly ?string $orderState = null,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPriceResult(): ?PriceResult
    {
        return $this->priceResult;
    }

    public function getShippingResult(): ?ShippingResult
    {
        return $this->shippingResult;
    }

    public function getStockValidation(): ?StockValidationResult
    {
        return $this->stockValidation;
    }

    /**
     * @return string[]
     */
    public function getAppliedCoupons(): array
    {
        return $this->appliedCoupons;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getOrderSn(): ?string
    {
        return $this->orderSn;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderSn;
    }

    public function getOrderState(): ?string
    {
        return $this->orderState;
    }

    /**
     * 获取最终总价（商品价格 + 运费）
     */
    public function getFinalTotal(): float
    {
        $itemsTotal = $this->priceResult?->getFinalPrice() ?? '0.00';
        $shippingFee = $this->shippingResult?->getShippingFee() ?? 0.0;

        return (float) $itemsTotal + $shippingFee;
    }

    /**
     * 获取商品原价总计
     */
    public function getOriginalTotal(): float
    {
        $originalPrice = $this->priceResult?->getOriginalPrice() ?? '0.00';

        return (float) $originalPrice;
    }

    /**
     * 获取总优惠金额
     */
    public function getTotalDiscount(): float
    {
        $discount = $this->priceResult?->getDiscount() ?? '0.00';

        return (float) $discount;
    }

    /**
     * 是否有库存问题
     */
    public function hasStockIssues(): bool
    {
        return null !== $this->stockValidation && !$this->stockValidation->isValid();
    }

    /**
     * 是否可以结算
     */
    public function canCheckout(): bool
    {
        return count($this->items) > 0 && !$this->hasStockIssues();
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'items' => $this->items,
            'summary' => [
                'original_total' => $this->getOriginalTotal(),
                'final_total' => $this->getFinalTotal(),
                'total_discount' => $this->getTotalDiscount(),
                'shipping_fee' => $this->shippingResult?->getShippingFee() ?? 0.0,
                'free_shipping' => $this->shippingResult?->isFreeShipping() ?? false,
            ],
            'price_details' => $this->priceResult?->toArray() ?? [],
            'shipping_details' => $this->shippingResult?->toArray() ?? [],
            'stock_validation' => $this->stockValidation?->toArray() ?? ['valid' => true],
            'applied_coupons' => $this->appliedCoupons,
            'can_checkout' => $this->canCheckout(),
        ];

        // 如果有订单信息，添加订单字段
        if (null !== $this->orderId) {
            $result['order'] = [
                'id' => $this->orderId,
                'sn' => $this->orderSn,
                'state' => $this->orderState,
            ];
        }

        return $result;
    }

    /**
     * 创建空结果
     */
    public static function empty(): self
    {
        return new self();
    }
}
