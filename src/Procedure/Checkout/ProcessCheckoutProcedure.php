<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCartBundle\Interface\CartDataProviderInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Event\OrderCreateAfterEvent;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Param\Checkout\ProcessCheckoutParam;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;
use Tourze\StockManageBundle\Exception\InsufficientStockException;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '执行订单结算（生成订单）')]
#[MethodExpose(method: 'ProcessCheckout')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
final class ProcessCheckoutProcedure extends LockableProcedure
{

    public function __construct(
        private readonly Security $security,
        private readonly CheckoutService $checkoutService,
        private readonly CartDataProviderInterface $cartDataProvider,
        private readonly SkuServiceInterface $skuService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @phpstan-param ProcessCheckoutParam $param
     */
    public function execute(ProcessCheckoutParam|RpcParamInterface $param): ArrayResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 检测是否为纯兑换券场景
        $isRedeemOnlyOrder = $this->isRedeemOnlyOrder($param);

        // 纯兑换券场景可以不需要地址，其他场景必须要地址
        if (!$isRedeemOnlyOrder && $param->addressId <= 0) {
            throw new ApiException('请选择收货地址');
        }

        // 获取结算商品
        $checkoutItems = $this->getCheckoutItems($param, $user);

        // 构建结算上下文
        $appliedCoupons = null !== $param->couponCode ? [$param->couponCode] : [];
        $context = new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            [
                'addressId' => $isRedeemOnlyOrder ? 0 : $param->addressId, // 兑换券订单使用虚拟地址
                'pointsToUse' => $param->pointsToUse,
                'orderRemark' => $param->orderRemark,
                'orderType' => $isRedeemOnlyOrder ? 'redeem' : 'normal', // 标识订单类型
                'paymentMode' => $param->paymentMode, // 支付模式
                'useIntegralAmount' => $param->useIntegralAmount, // 混合支付时使用的积分数量
            ]
        );

        try {
            $checkoutResult = $this->checkoutService->process($context);

            // 格式化返回结果
            $finalPrice = $checkoutResult->getFinalTotal();
            $result = [
                '__message' => '订单创建成功',
                'orderId' => $checkoutResult->getOrderId(),
                'orderNumber' => $checkoutResult->getOrderNumber(),
                'totalAmount' => $finalPrice,
                'paymentRequired' => $finalPrice > 0,
                'orderState' => $checkoutResult->getOrderState(),
            ];

            // 如果有库存问题，返回警告信息
            $stockWarnings = [];
            if ($checkoutResult->hasStockIssues()) {
                $stockValidation = $checkoutResult->getStockValidation();
                $stockWarnings = $stockValidation?->getWarnings() ?? [];
                $result['stockWarnings'] = $stockWarnings;
            }

            // 分发订单完成事件
            $this->dispatchOrderCompletedEvent($param, $user, $checkoutResult, $context, $stockWarnings);

            return new ArrayResult($result);
        } catch (InsufficientStockException $e) {
            // InsufficientStockException 不转换为 ApiException，直接传播
            throw $e;
        } catch (CheckoutException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    public function getLockResource(JsonRpcParams $params): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 用户级别锁定，防止重复下单
        return [sprintf('checkout_process:%s', $user->getUserIdentifier())];
    }

    protected function getIdempotentCacheKey(JsonRpcRequest $request): ?string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        $params = $request->getParams();
        $addressId = $params->get('addressId', 0);
        $couponCode = $params->get('couponCode');
        $pointsToUse = $params->get('pointsToUse', 0);

        // 基于关键参数生成幂等性Key
        return sprintf(
            'checkout:%s:%d:%s:%d',
            $user->getUserIdentifier(),
            $addressId,
            $couponCode ?? 'no_coupon',
            $pointsToUse
        );
    }

    /**
     * 获取结算商品项目
     *
     * @return CheckoutItem[]
     */
    private function getCheckoutItems(ProcessCheckoutParam $param, UserInterface $user): array
    {
        if ($param->fromCart) {
            return $this->getCartCheckoutItems($user);
        }

        // 检测纯兑换券场景：没有商品但有兑换券
        if ([] === $param->skuItems) {
            if ($this->isRedeemOnlyOrder($param)) {
                // 纯兑换券场景，返回空数组，后续由价格计算生成兑换商品
                return [];
            }
            throw new ApiException('请选择商品或启用购物车模式');
        }

        return $this->convertToCheckoutItems($param->skuItems);
    }

    /**
     * 从用户购物车获取已选中的商品
     *
     * @return CheckoutItem[]
     */
    private function getCartCheckoutItems(UserInterface $user): array
    {
        $cartItems = $this->cartDataProvider->getSelectedCartEntities($user);

        if ([] === $cartItems) {
            throw new ApiException('购物车中没有选中的商品');
        }

        $checkoutItems = [];
        foreach ($cartItems as $cartItem) {
            $checkoutItems[] = CheckoutItem::fromCartItem($cartItem);
        }

        return $checkoutItems;
    }

    /**
     * 将数组形式的购物车项目转换为 CheckoutItem 对象数组
     *
     * @param array<int, array{id?: int, skuId: string|int, quantity: int, price?: float}> $cartItems
     * @return CheckoutItem[]
     */
    private function convertToCheckoutItems(array $cartItems): array
    {
        $checkoutItems = [];
        $skuIds = [];

        // 收集所有SKU ID
        foreach ($cartItems as $item) {
            $skuId = $item['skuId'] ?? null;
            if (null !== $skuId) {
                $skuIds[] = (string) $skuId;
            }
        }

        // 批量查询SKU实体
        $skus = [];
        if ([] !== $skuIds) {
            $skuEntities = $this->skuService->findByIds($skuIds);
            foreach ($skuEntities as $sku) {
                $skus[$sku->getId()] = $sku;
            }
        }

        // 转换为 CheckoutItem 对象
        foreach ($cartItems as $item) {
            $checkoutItem = CheckoutItem::fromArray($item);
            $skuId = (string) $checkoutItem->getSkuId();

            if (isset($skus[$skuId])) {
                $checkoutItem = $checkoutItem->withSku($skus[$skuId]);
            }

            $checkoutItems[] = $checkoutItem;
        }

        return $checkoutItems;
    }

    /**
     * 检测是否为纯兑换券订单
     * 纯兑换券订单的特征：不从购物车获取、没有商品、但有兑换券
     */
    private function isRedeemOnlyOrder(ProcessCheckoutParam $param): bool
    {
        return !$param->fromCart &&
               [] === $param->skuItems &&
               null !== $param->couponCode &&
               '' !== trim($param->couponCode);
    }

    /**
     * 分发订单完成事件
     *
     * @param array<string, mixed> $stockWarnings
     */
    private function dispatchOrderCompletedEvent(
        ProcessCheckoutParam $param,
        UserInterface $user,
        \Tourze\OrderCheckoutBundle\DTO\CheckoutResult $checkoutResult,
        CalculationContext $context,
        array $stockWarnings
    ): void {
        // 构建事件元数据
        $metadata = [
            'orderType' => $context->getMetadataValue('orderType', 'normal'),
            'appliedCoupons' => $context->getAppliedCoupons(),
            'addressId' => $context->getMetadataValue('addressId'),
            'pointsToUse' => $context->getMetadataValue('pointsToUse', 0),
            'orderRemark' => $context->getMetadataValue('orderRemark'),
            'stockWarnings' => $stockWarnings,
            'itemsCount' => count($context->getItems()),
            'fromCart' => $param->fromCart,
        ];

        if (null !== $param->referralDistributorId) {
            $metadata['referral'] = [
                'distributorId' => $param->referralDistributorId,
                'source' => $param->referralSource ?? 'scan_qrcode',
                'trackCode' => $param->referralTrackCode,
            ];
        }

        // 创建并分发事件
        $event = new OrderCreateAfterEvent(
            orderId: $checkoutResult->getOrderId(),
            orderNumber: $checkoutResult->getOrderNumber(),
            user: $user,
            totalAmount: $checkoutResult->getFinalTotal(),
            paymentRequired: $checkoutResult->getFinalTotal() > 0,
            orderState: $checkoutResult->getOrderState(),
            metadata: $metadata
        );
        $this->eventDispatcher->dispatch($event);
    }
}
