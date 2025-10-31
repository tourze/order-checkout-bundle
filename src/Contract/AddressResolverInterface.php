<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

/**
 * 地址解析接口
 * 解决跨模块调用问题的抽象接口
 */
interface AddressResolverInterface
{
    /**
     * 根据地址ID获取地址信息
     *
     * @return array{province: string, city: string, district: string}|null
     */
    public function resolveAddress(string $addressId): ?array;

    /**
     * 检查地址是否存在
     */
    public function addressExists(string $addressId): bool;
}
