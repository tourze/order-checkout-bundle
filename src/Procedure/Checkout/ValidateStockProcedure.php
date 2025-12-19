<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\OrderCartBundle\Interface\CartDataProviderInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Param\Checkout\ValidateStockParam;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '验证购物车商品库存状况')]
#[MethodExpose(method: 'ValidateCheckoutStock')]
#[IsGranted(attribute: 'ROLE_USER')]
final class ValidateStockProcedure extends CacheableProcedure
{
    public function __construct(
        private readonly Security $security,
        private readonly CartDataProviderInterface $cartDataProvider,
        private readonly StockValidatorInterface $stockValidator,
    ) {
    }

    /**
     * @phpstan-param ValidateStockParam $param
     */
    public function execute(ValidateStockParam|RpcParamInterface $param): ArrayResult
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

        return new ArrayResult([
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
        ]);
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

        return new ArrayResult([
            'stock_validation',
            'cart_user_' . $user->getUserIdentifier(),
        ]);
    }
}
