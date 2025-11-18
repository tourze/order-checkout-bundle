<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Coupon;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;
use Tourze\OrderCheckoutBundle\Entity\CouponUsageLog;

class CouponUsageLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<int, array{sku_id?: string, amount?: string, order_product_id?: int|null}> $allocations
     * @param array<string, mixed> $metadata
     */
    public function logUsage(
        string $couponCode,
        string $couponType,
        string $userIdentifier,
        int $orderId,
        string $orderNumber,
        string $discountAmount,
        array $allocations = [],
        array $metadata = []
    ): void {
        $usage = new CouponUsageLog();
        $usage->setCouponCode($couponCode);
        $usage->setCouponType($couponType);
        $usage->setUserIdentifier($userIdentifier);
        $usage->setOrderId($orderId);
        $usage->setOrderNumber($orderNumber);
        $usage->setDiscountAmount($this->normalizeAmount($discountAmount));
        $usage->setMetadata($metadata);
        $usage->setUsageTime(new \DateTimeImmutable());

        $this->entityManager->persist($usage);

        $allocationRuleValue = $metadata['allocation_rule'] ?? '';
        $allocationRule = is_scalar($allocationRuleValue) ? (string) $allocationRuleValue : '';
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }

            $detail = new CouponAllocationDetail();
            $detail->setCouponCode($couponCode);
            $detail->setOrderId($orderId);
            $detail->setOrderProductId(isset($allocation['order_product_id']) ? (int) $allocation['order_product_id'] : null);
            $detail->setSkuId(isset($allocation['sku_id']) ? (string) $allocation['sku_id'] : '');
            $detail->setAllocatedAmount($this->normalizeAmount($allocation['amount'] ?? '0.00'));
            $detail->setAllocationRule($allocationRule);

            $this->entityManager->persist($detail);
        }

        $this->entityManager->flush();
    }

    private function normalizeAmount(mixed $amount): string
    {
        if (is_string($amount) && is_numeric($amount)) {
            return sprintf('%.2f', (float) $amount);
        }

        if (is_numeric($amount)) {
            return sprintf('%.2f', (float) $amount);
        }

        return '0.00';
    }
}
