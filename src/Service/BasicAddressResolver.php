<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Tourze\OrderCheckoutBundle\Contract\AddressResolverInterface;

/**
 * 基础地址解析器
 * 为测试和默认场景提供简单实现，避免跨模块依赖
 */
class BasicAddressResolver implements AddressResolverInterface
{
    public function resolveAddress(string $addressId): ?array
    {
        // 简单的模拟实现，实际项目中应通过配置或服务层获取地址信息
        if ('' === $addressId || !is_numeric($addressId)) {
            return null;
        }

        // 返回默认地址信息，避免跨模块调用
        return [
            'province' => '北京市',
            'city' => '北京市',
            'district' => '朝阳区',
        ];
    }

    public function addressExists(string $addressId): bool
    {
        return '' !== $addressId && is_numeric($addressId);
    }
}
