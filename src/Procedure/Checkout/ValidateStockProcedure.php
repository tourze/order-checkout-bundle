<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\OrderCartBundle\Interface\CartDataProviderInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '验证购物车商品库存状况')]
#[MethodExpose(method: 'ValidateCheckoutStock')]
#[IsGranted(attribute: 'ROLE_USER')]
class ValidateStockProcedure extends CacheableProcedure
{
    public function __construct(
        private readonly Security $security,
        private readonly CartDataProviderInterface $cartDataProvider,
        private readonly StockValidatorInterface $stockValidator,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 获取用户的选中购物车商品
        $cartItems = $this->cartDataProvider->getSelectedCartEntities($user);

        if ([] === $cartItems) {
            throw new ApiException('购物车中没有选中的商品');
        }

        // 转换 CartItem[] 为 CheckoutItem[]
        $checkoutItems = [];
        foreach ($cartItems as $cartItem) {
            $checkoutItems[] = CheckoutItem::fromCartItem($cartItem);
        }

        // 验证库存
        $validationResult = $this->stockValidator->validate($checkoutItems);

        return [
            'isValid' => $validationResult->isValid(),
            'hasWarnings' => [] !== $validationResult->getWarnings(),
            'errors' => $validationResult->getErrors(),
            'warnings' => $validationResult->getWarnings(),
            'details' => $validationResult->getDetails(),
            'summary' => [
                'totalItems' => count($checkoutItems),
                'validItems' => count($validationResult->getDetails()) - count($validationResult->getErrors()),
                'invalidItems' => count($validationResult->getErrors()),
                'warningItems' => count($validationResult->getWarnings()),
            ],
        ];
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 基于用户和购物车内容生成缓存Key
        return sprintf('stock_validation:%s', $user->getUserIdentifier());
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 30; // 30秒，库存信息变化较快
    }

    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        return [
            'stock_validation',
            'cart_user_' . $user->getUserIdentifier(),
        ];
    }

    public static function getMockResult(): ?array
    {
        return [
            'isValid' => false,
            'hasWarnings' => true,
            'errors' => [
                '100' => '商品 SKU001 库存不足，需要 5 件，仅有 3 件',
            ],
            'warnings' => [
                '101' => '商品 SKU002 库存较少，仅剩 8 件',
            ],
            'details' => [
                '100' => [
                    'sku_code' => 'SKU001',
                    'sku_name' => '商品1',
                    'requested_quantity' => 5,
                    'available_quantity' => 3,
                ],
                '101' => [
                    'sku_code' => 'SKU002',
                    'sku_name' => '商品2',
                    'requested_quantity' => 2,
                    'available_quantity' => 8,
                ],
            ],
            'summary' => [
                'totalItems' => 2,
                'validItems' => 1,
                'invalidItems' => 1,
                'warningItems' => 1,
            ],
        ];
    }
}
