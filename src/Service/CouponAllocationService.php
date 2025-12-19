<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Tourze\OrderCheckoutBundle\Repository\CouponAllocationDetailRepository;

/**
 * 优惠券分摊明细服务
 * 负责处理优惠券分摊明细的查询和计算
 */
final class CouponAllocationService
{
    public function __construct(
        private readonly CouponAllocationDetailRepository $couponAllocationDetailRepository,
    ) {
    }

    /**
     * 获取订单商品的优惠金额映射
     *
     * @return array<int|string, string> [orderProductId => discountAmount] 或 ['sku_'.$skuId => discountAmount]
     */
    public function getOrderProductDiscountMap(int $orderId): array
    {
        $couponAllocations = $this->couponAllocationDetailRepository->findBy(['orderId' => $orderId]);

        $discountMap = [];
        foreach ($couponAllocations as $allocation) {
            $orderProductId = $allocation->getOrderProductId();
            $skuId = $allocation->getSkuId();
            $allocatedAmount = (string) $allocation->getAllocatedAmount();

            $key = $orderProductId ?? ('sku_' . $skuId);
            $discountMap[$key] = bcadd($discountMap[$key] ?? '0.00', $allocatedAmount, 2);
        }

        return $discountMap;
    }

    /**
     * 获取订单的总优惠金额
     * @param int $orderId
     * @return string
     */
    public function getOrderDiscount(int $orderId): string
    {
        return $this->couponAllocationDetailRepository->createQueryBuilder('o')
            ->select('SUM(o.allocatedAmount)')
            ->where('o.orderId = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getSingleScalarResult() ?? '0.00';
    }

    /**
     * 根据订单商品ID和SKU ID获取优惠金额
     */
    public function getProductDiscountAmount(int $orderId, int $orderProductId, ?int $skuId = null): string
    {
        $discountMap = $this->getOrderProductDiscountMap($orderId);
        
        // 优先通过 orderProductId 查找优惠
        if (isset($discountMap[$orderProductId])) {
            return $discountMap[$orderProductId];
        }
        
        // 如果通过 orderProductId 找不到且有 skuId，通过 skuId 查找
        if ($skuId !== null && isset($discountMap['sku_' . $skuId])) {
            return $discountMap['sku_' . $skuId];
        }
        
        return '0.00';
    }
}
