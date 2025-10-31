<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCartBundle\Interface\CartDataProviderInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;
use Tourze\StockManageBundle\Exception\InsufficientStockException;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '执行订单结算（生成订单）')]
#[MethodExpose(method: 'ProcessCheckout')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
class ProcessCheckoutProcedure extends LockableProcedure
{
    /** @var array<int, array{id?: int, skuId: string|int, quantity: int, price?: float}> */
    #[MethodParam(description: 'SKU商品数组（当fromCart为false时使用）')]
    public array $skuItems = [];

    #[MethodParam(description: '是否从购物车获取商品（为true时忽略skuItems）')]
    public bool $fromCart = false;

    #[MethodParam(description: '收货地址ID')]
    public int $addressId = 0;

    #[MethodParam(description: '优惠券代码')]
    #[Assert\Length(max: 50)]
    public ?string $couponCode = null;

    #[MethodParam(description: '用户积分抵扣数量')]
    #[Assert\PositiveOrZero]
    public int $pointsToUse = 0;

    #[MethodParam(description: '订单备注')]
    #[Assert\Length(max: 500)]
    public ?string $orderRemark = null;

    public function __construct(
        private readonly Security $security,
        private readonly CheckoutService $checkoutService,
        private readonly CartDataProviderInterface $cartDataProvider,
        private readonly SkuServiceInterface $skuService,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        if ($this->addressId <= 0) {
            throw new ApiException('请选择收货地址');
        }

        // 获取结算商品
        $checkoutItems = $this->getCheckoutItems($user);

        // 构建结算上下文
        $appliedCoupons = null !== $this->couponCode ? [$this->couponCode] : [];
        $context = new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            [
                'addressId' => $this->addressId,
                'pointsToUse' => $this->pointsToUse,
                'orderRemark' => $this->orderRemark,
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
            if ($checkoutResult->hasStockIssues()) {
                $stockValidation = $checkoutResult->getStockValidation();
                $result['stockWarnings'] = $stockValidation?->getWarnings() ?? [];
            }

            return $result;
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

        // 基于关键参数生成幂等性Key
        return sprintf(
            'checkout:%s:%d:%s:%d',
            $user->getUserIdentifier(),
            $this->addressId,
            $this->couponCode ?? 'no_coupon',
            $this->pointsToUse
        );
    }

    /**
     * 获取结算商品项目
     *
     * @return CheckoutItem[]
     */
    private function getCheckoutItems(UserInterface $user): array
    {
        if ($this->fromCart) {
            return $this->getCartCheckoutItems($user);
        }
        if ([] === $this->skuItems) {
            throw new ApiException('请选择商品或启用购物车模式');
        }

        return $this->convertToCheckoutItems($this->skuItems);
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

    public static function getMockResult(): ?array
    {
        return [
            '__message' => '订单创建成功',
            'orderId' => 123456,
            'orderNumber' => 'ORD202401010001',
            'totalAmount' => 234.98,
            'paymentRequired' => true,
            'orderState' => 'init',
            'stockWarnings' => [],
        ];
    }
}
