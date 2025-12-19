<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Checkout;

use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * 验证购物车商品库存参数(无参数,从当前用户购物车读取)
 */
final class ValidateStockParam implements RpcParamInterface
{
}
