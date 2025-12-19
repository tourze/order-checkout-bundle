<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Service\ContractService;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCartBundle\Interface\CartManagerInterface;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponWorkflowHelper;
use Tourze\OrderCheckoutBundle\Service\Order\OrderContactBuilder;
use Tourze\OrderCheckoutBundle\Service\Order\OrderPriceBuilder;
use Tourze\OrderCheckoutBundle\Service\Order\OrderProductBuilder;
use Tourze\StockManageBundle\Service\StockOperator;
use Tourze\Symfony\AopDoctrineBundle\Attribute\Transactional;

/**
 * 结算服务
 * 统筹整个结算流程，协调各个模块执行
 */
final class CheckoutService
{
    public function __construct(
        private readonly PriceCalculationService $priceCalculationService,
        private readonly StockValidatorInterface $stockValidator,
        private readonly ShippingCalculatorInterface $shippingCalculator,
        private readonly ContentFilterService $contentFilterService,
        private readonly ContractService $contractService,
        private readonly CartManagerInterface $cartManager,
        private readonly StockOperator $stockOperator,
        private readonly CouponWorkflowHelper $couponHelper,
        private readonly OrderProductBuilder $orderProductBuilder,
        private readonly OrderPriceBuilder $orderPriceBuilder,
        private readonly OrderContactBuilder $orderContactBuilder,
    ) {
    }

    /**
     * 执行完整的结算计算
     *
     * @param array<mixed>         $cartItems      购物车商品数组
     * @param string[]             $appliedCoupons 应用的优惠券代码
     * @param array<string, mixed> $options        选项（如地区、计算时间等）
     *
     * @throws CheckoutException
     */
    public function calculateCheckout(
        UserInterface $user,
        array $cartItems,
        array $appliedCoupons = [],
        array $options = [],
    ): CheckoutResult {
        if ([] === $cartItems) {
            throw new CheckoutException('购物车为空，无法结算');
        }

        $checkoutItems = $this->convertToCheckoutItems($cartItems);
        $calculationContext = $this->buildCalculationContext($user, $checkoutItems, $appliedCoupons, $options);
        $priceResult = $this->priceCalculationService->calculate($calculationContext);
        $extraItems = $this->couponHelper->extractCouponExtraItems($priceResult);
        $stockValidation = $this->performStockValidation($checkoutItems, $extraItems);
        $shippingResult = $this->calculateShipping($user, $this->couponHelper->mergeCheckoutItems($checkoutItems, $extraItems), $options);

        return new CheckoutResult(
            $checkoutItems,
            $priceResult,
            $shippingResult,
            $stockValidation,
            $appliedCoupons
        );
    }

    /**
     * 快速价格计算（不验证库存）
     *
     * @param array<mixed>         $cartItems      购物车商品数组
     * @param string[]             $appliedCoupons
     * @param array<string, mixed> $options
     */
    public function quickCalculate(
        UserInterface $user,
        array $cartItems,
        array $appliedCoupons = [],
        array $options = [],
    ): CheckoutResult {
        if ([] === $cartItems) {
            return CheckoutResult::empty();
        }

        $checkoutItems = $this->convertToCheckoutItems($cartItems);
        $calculationContext = $this->buildCalculationContext($user, $checkoutItems, $appliedCoupons, $options);
        $priceResult = $this->priceCalculationService->calculate($calculationContext);
        $extraItems = $this->couponHelper->extractCouponExtraItems($priceResult);
        $shippingResult = $this->calculateShipping($user, $this->couponHelper->mergeCheckoutItems($checkoutItems, $extraItems), $options);

        return new CheckoutResult(
            $checkoutItems,
            $priceResult,
            $shippingResult,
            null,
            $appliedCoupons
        );
    }

    /**
     * 执行订单处理（实际下单）
     *
     * @throws CheckoutException
     */
    #[Transactional]
    public function process(CalculationContext $context): CheckoutResult
    {
        $priceResult = $this->priceCalculationService->calculate($context);
        $couponCodes = $this->couponHelper->extractCouponCodes($priceResult);
        $lockedCodes = [];
        $redeemed = false;
        $extraItems = [];
        $contract = null;

        try {
            if ([] !== $couponCodes) {
                $lockedCodes = $this->couponHelper->lockCouponCodes($context->getUser(), $couponCodes);
                if ([] === $lockedCodes) {
                    throw new CheckoutException('优惠券已失效~');
                }
            }

            $extraItems = $this->couponHelper->extractCouponExtraItems($priceResult);
            $stockValidation = $this->validateStockForProcessing($context, $extraItems);
            [$contract, $persistedExtraItems] = $this->createOrder($context, $priceResult, null, $extraItems);

            if ([] !== $lockedCodes) {
                $this->couponHelper->redeemCouponCodes($lockedCodes, $contract);
                $redeemed = true;
            }

            $this->couponHelper->logCouponUsage($context, $contract, $priceResult);

            $this->executePostOrderOperations($context, $contract, $persistedExtraItems);

            return new CheckoutResult(
                $context->getItems(),
                $priceResult,
                null,
                $stockValidation,
                $context->getAppliedCoupons(),
                $contract->getId(),
                $contract->getSn(),
                $contract->getState()->value
            );
        } finally {
            if (!$redeemed && [] !== $lockedCodes) {
                $this->couponHelper->unlockCouponCodes($lockedCodes, $context->getUser());
            }
        }
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    private function validateStockForProcessing(CalculationContext $context, array $extraItems): StockValidationResult
    {
        $items = $this->couponHelper->mergeCheckoutItems($context->getItems(), $extraItems);
        $stockValidation = $this->stockValidator->validate($items);
        if (!$stockValidation->isValid()) {
            throw new CheckoutException('库存验证失败: ' . implode(', ', $stockValidation->getErrors()));
        }

        return $stockValidation;
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    private function executePostOrderOperations(CalculationContext $context, Contract $contract, array $extraItems): void
    {
        $this->lockStock($this->couponHelper->mergeCheckoutItems($context->getItems(), $extraItems));
        $this->clearCartSelectedItems($context->getUser(), $context->getItems());
        $this->handleOrderRemarkIfPresent($context, $contract);
    }

    private function handleOrderRemarkIfPresent(CalculationContext $context, Contract $contract): void
    {
        $orderRemark = $this->getValidRemark($context);
        if (null !== $orderRemark) {
            $this->processOrderRemark($orderRemark, $context, $contract);
        }
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     * @return array{0: Contract, 1: array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>}
     */
    private function createOrder(CalculationContext $context, PriceResult $priceResult, ?ShippingResult $shippingResult = null, array $extraItems = []): array
    {
        $contract = $this->buildContractEntity($context, $priceResult);
        $updatedExtraItems = $this->persistOrderData($contract, $context, $priceResult, $shippingResult, $extraItems);

        return [$contract, $updatedExtraItems];
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>
     */
    private function persistOrderData(Contract $contract, CalculationContext $context, PriceResult $priceResult, ?ShippingResult $shippingResult, array $extraItems): array
    {
        $orderProductResult = $this->orderProductBuilder->createOrderProducts($contract, $context->getItems(), $extraItems, $context);
        $baseOrderProducts = $orderProductResult['base'];
        $extraOrderProducts = $orderProductResult['extra'];
        $updatedExtraItems = $orderProductResult['extraItems'];
        $this->orderPriceBuilder->createOrderPrices($contract, $baseOrderProducts, $extraOrderProducts, $priceResult, $shippingResult, $updatedExtraItems);
        $this->orderContactBuilder->createOrderContact($contract, $context);

        $this->contractService->createOrder($contract);

        return $updatedExtraItems;
    }

    private function buildContractEntity(CalculationContext $context, PriceResult $priceResult): Contract
    {
        $contract = $this->createBasicContract($context);
        $this->configureContract($contract, $context, $priceResult);

        return $contract;
    }

    private function createBasicContract(CalculationContext $context): Contract
    {
        $contract = new Contract();
        $contract->setUser($context->getUser());
        $contract->setSn($this->generateOrderNumber());
        $contract->setState(OrderState::INIT);

        return $contract;
    }

    private function configureContract(Contract $contract, CalculationContext $context, PriceResult $priceResult): void
    {
        $this->setContractType($contract, $context);
        $this->setContractRemark($contract, $context);
        $this->setContractPricing($contract, $priceResult);
        $this->applyCouponState($contract, $priceResult);
    }

    private function applyCouponState(Contract $contract, PriceResult $priceResult): void
    {
        $shouldMarkPaid = (bool) $priceResult->getDetail('coupon_should_mark_paid', false);
        if ($shouldMarkPaid) {
            $contract->setState(OrderState::PAID);
        }
    }

    private function setContractType(Contract $contract, CalculationContext $context): void
    {
        $orderType = $context->getMetadataValue('orderType', 'normal');
        $contract->setType(is_string($orderType) ? $orderType : 'normal');
    }

    private function setContractRemark(Contract $contract, CalculationContext $context): void
    {
        $remark = $this->getValidRemark($context);
        if (null !== $remark) {
            $contract->setRemark($remark);
        }
    }

    private function getValidRemark(CalculationContext $context): ?string
    {
        $remark = $context->getMetadataValue('orderRemark');
        if (null === $remark || '' === $remark || !is_string($remark)) {
            return null;
        }

        return $remark;
    }

    private function setContractPricing(Contract $contract, PriceResult $priceResult): void
    {
        $contract->setTotalAmount($priceResult->getFinalPrice());
        $autoCancelTime = new \DateTimeImmutable('+30 minutes');
        $contract->setAutoCancelTime($autoCancelTime);

        $details = $priceResult->getDetails();
        $totalIntegral = $details['total_integral_required'] ?? 0;
        if (is_int($totalIntegral) && $totalIntegral > 0) {
            $contract->setTotalIntegral($totalIntegral);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'ORD' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param CheckoutItem[] $items
     */
    private function lockStock(array $items): void
    {
        foreach ($items as $item) {
            $sku = $item->getSku();
            $quantity = $item->getQuantity();
            if (null !== $sku && $quantity > 0) {
                $this->stockOperator->lockStock($sku, $quantity);
            }
        }
    }

    /**
     * @param CheckoutItem[] $items
     */
    private function clearCartSelectedItems(UserInterface $user, array $items): void
    {
        foreach ($items as $item) {
            $cartItemId = $item->getId();
            if (null !== $cartItemId) {
                $this->cartManager->removeItem($user, (string) $cartItemId);
            }
        }
    }

    /**
     * @param array<mixed> $cartItems
     * @return CheckoutItem[]
     * @throws CheckoutException
     */
    private function convertToCheckoutItems(array $cartItems): array
    {
        $checkoutItems = [];
        foreach ($cartItems as $item) {
            $checkoutItems[] = $this->convertSingleItem($item);
        }

        return $checkoutItems;
    }

    private function convertSingleItem(mixed $item): CheckoutItem
    {
        return match (true) {
            is_array($item) => CheckoutItem::fromArray($this->sanitizeArrayItem($item)),
            is_object($item) => CheckoutItem::fromCartItem($item),
            default => throw new CheckoutException('无效的购物车项目格式'),
        };
    }

    /**
     * @param array<mixed, mixed> $item
     * @return array{id?: int, skuId?: int|string, quantity?: int, selected?: bool}
     */
    private function sanitizeArrayItem(array $item): array
    {
        $sanitized = [];

        if (isset($item['id']) && is_int($item['id'])) {
            $sanitized['id'] = $item['id'];
        }
        if (isset($item['skuId']) && (is_string($item['skuId']) || is_int($item['skuId']))) {
            $sanitized['skuId'] = $item['skuId'];
        }
        if (isset($item['quantity']) && is_int($item['quantity'])) {
            $sanitized['quantity'] = $item['quantity'];
        }
        if (isset($item['selected']) && is_bool($item['selected'])) {
            $sanitized['selected'] = $item['selected'];
        }

        /** @var array{id?: int, skuId?: int|string, quantity?: int, selected?: bool} $sanitized */
        return $sanitized;
    }

    /**
     * @param CheckoutItem[] $checkoutItems
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    private function performStockValidation(array $checkoutItems, array $extraItems = []): StockValidationResult
    {
        $items = $this->couponHelper->mergeCheckoutItems($checkoutItems, $extraItems);
        $stockValidation = $this->stockValidator->validate($items);
        if (!$stockValidation->isValid()) {
            throw new CheckoutException('库存验证失败: ' . implode(', ', $stockValidation->getErrors()));
        }

        return $stockValidation;
    }

    /**
     * @param CheckoutItem[] $checkoutItems
     * @param string[] $appliedCoupons
     * @param array<string, mixed> $options
     */
    private function buildCalculationContext(
        UserInterface $user,
        array $checkoutItems,
        array $appliedCoupons,
        array $options,
    ): CalculationContext {
        return new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            array_merge([
                'calculate_time' => new \DateTimeImmutable(),
            ], $options)
        );
    }

    /**
     * @param CheckoutItem[] $checkoutItems
     * @param array<string, mixed> $options
     */
    private function calculateShipping(UserInterface $user, array $checkoutItems, array $options): ShippingResult
    {
        $region = is_string($options['region'] ?? null) ? $options['region'] : 'default';
        $shippingContext = new ShippingContext($user, $checkoutItems, $region);

        return $this->shippingCalculator->calculate($shippingContext);
    }

    private function processOrderRemark(string $orderRemark, CalculationContext $context, Contract $contract): void
    {
        try {
            $sanitizedRemark = $this->contentFilterService->sanitizeRemark($orderRemark);
            $filterResult = $this->contentFilterService->filterContent($sanitizedRemark);
        } catch (\Exception $e) {
            error_log(sprintf('订单备注处理失败: %s', $e->getMessage()));
        }
    }
}
