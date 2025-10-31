# 订单结算包

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/order-checkout-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/order-checkout-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/order-checkout-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/order-checkout-bundle)
[![License](https://img.shields.io/github/license/tourze/order-checkout-bundle.svg?style=flat-square)](LICENSE)

一个功能全面的Symfony订单结算Bundle，包括购物车管理、价格计算、促销匹配、库存验证和运费计算功能。

## 功能特性

- **购物车管理**：完整的购物车操作（添加、删除、更新、清空）
- **价格计算**：灵活的价格计算系统，支持基础价格、折扣和促销
- **促销系统**：内置促销匹配，支持满减和自定义促销规则
- **库存验证**：实时库存验证，支持可配置缓存
- **运费计算**：基础运费计算，支持地区运费和免邮门槛
- **管理员操作**：客服人员的购物车管理功能
- **JSON-RPC程序**：为前端集成提供即用的JSON-RPC端点
- **全面测试**：完整的测试覆盖，包含真实场景

## 安装

### 系统要求

- PHP 8.2或更高版本
- Symfony 7.3或更高版本
- Doctrine ORM 3.0或更高版本
- 必需的包：product-core-bundle、biz-user-bundle

### 通过Composer安装

```bash
composer require tourze/order-checkout-bundle
```

### 注册Bundle

在`config/bundles.php`中启用Bundle：

```php
return [
    // ...
    Tourze\OrderCheckoutBundle\OrderCheckoutBundle::class => ['all' => true],
];
```

## 快速开始

### 基础购物车操作

```php
<?php
use Tourze\OrderCheckoutBundle\Service\CartService;

// 添加商品到购物车
$cartService->addToCart($user, $sku, $quantity, $attributes, $remark);

// 更新数量
$cartService->updateQuantity($user, $cartItemId, $newQuantity);

// 删除商品
$cartService->removeFromCart($user, $cartItemId);

// 获取购物车商品
$items = $cartService->getCartItems($user, $selectedOnly = false);
```

### 价格计算

```php
<?php
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;

$context = new CalculationContext($user, $cartItems, $appliedCoupons, $metadata);
$priceResult = $priceCalculationService->calculate($context);

echo "原价: " . $priceResult->getOriginalPrice();
echo "实付价格: " . $priceResult->getFinalPrice();
echo "优惠金额: " . $priceResult->getDiscount();
```

### 库存验证

```php
<?php
use Tourze\OrderCheckoutBundle\Service\BasicStockValidator;

$validator = new BasicStockValidator($cache);
$result = $validator->validate($cartItems);

if ($result->isValid()) {
    echo "所有商品库存充足";
} else {
    foreach ($result->getErrors() as $skuId => $error) {
        echo "商品 $skuId 库存错误: $error";
    }
}
```

## 架构设计

### 核心组件

1. **实体**
    - `CartItem`：购物车商品实体，包含用户、SKU、数量和元数据

2. **服务**
    - `CartService`：主要购物车操作
    - `PriceCalculationService`：使用计算器插件的价格计算
    - `CheckoutService`：完整的结算工作流
    - `BasicStockValidator`：支持缓存的库存验证

3. **计算器**
    - `BasePriceCalculator`：基础价格计算
    - `PromotionCalculator`：基于促销的价格调整
    - `BasicShippingCalculator`：运费计算

4. **数据传输对象**
    - `CalculationContext`：价格计算上下文
    - `PriceResult`：价格计算结果
    - `ShippingResult`：运费计算结果
    - `StockValidationResult`：库存验证结果

### 价格计算系统

Bundle使用可插拔的计算器系统：

```php
// 注册自定义计算器
$priceCalculationService->addCalculator(new CustomPriceCalculator());

// 计算器按优先级顺序执行
class CustomPriceCalculator implements PriceCalculatorInterface
{
    public function getPriority(): int { return 100; }
    public function getType(): string { return 'custom'; }
    public function supports(CalculationContext $context): bool { /* 逻辑 */ }
    public function calculate(CalculationContext $context): PriceResult { /* 逻辑 */ }
}
```

## JSON-RPC程序

Bundle包含用于前端集成的即用JSON-RPC程序：

### 购物车程序

- `AddToCartProcedure`：添加商品到购物车
- `UpdateCartQuantityProcedure`：更新商品数量
- `RemoveFromCartProcedure`：从购物车删除商品
- `GetCartListProcedure`：获取购物车内容
- `ClearCartProcedure`：清空整个购物车
- `ToggleCartSelectionProcedure`：切换商品选择状态
- `BatchToggleCartSelectionProcedure`：批量切换选择状态

### 管理员程序

- `AdminClearUserCartProcedure`：管理员清空用户购物车
- `AdminGetUserCartProcedure`：管理员查看用户购物车

### 结算程序

- `CalculatePriceProcedure`：计算价格和运费
- `ValidateStockProcedure`：验证库存可用性
- `ProcessCheckoutProcedure`：完成结算流程

## 配置

### 服务配置

```yaml
services:
    Tourze\OrderCheckoutBundle\Service\BasicStockValidator:
        arguments:
            $cache: '@cache.app'

    Tourze\OrderCheckoutBundle\Calculator\BasicShippingCalculator:
        arguments:
            $freeShippingThreshold: 100.0
            $defaultShippingFee: 10.0
```

### 缓存配置

Bundle支持PSR-6缓存用于库存验证：

```yaml
framework:
    cache:
        pools:
            stock_cache:
                adapter: cache.adapter.redis
                default_lifetime: 300
```

## API参考

### CartService

购物车操作的主要服务。

#### 方法

- `addToCart(UserInterface $user, Sku $sku, int $quantity, array $attributes = [], ?string $remark = null): CartItem`
- `updateQuantity(UserInterface $user, string $cartItemId, int $quantity): void`
- `removeFromCart(UserInterface $user, string $cartItemId): void`
- `getCartItems(UserInterface $user, bool $selectedOnly = false): array`
- `clearCart(UserInterface $user): void`

### PriceCalculationService

支持多个计算器的价格计算服务。

#### 方法

- `addCalculator(PriceCalculatorInterface $calculator): void`
- `calculate(CalculationContext $context): PriceResult`
- `getCalculatorByType(string $type): ?PriceCalculatorInterface`

### BasicStockValidator

库存可用性验证服务。

#### 方法

- `validate(array $cartItems): StockValidationResult`
- `getAvailableQuantity(string $skuId): int`
- `getAvailableQuantities(array $skuIds): array`

## 测试

Bundle包含覆盖所有主要功能的全面测试：

```bash
# 运行测试
./vendor/bin/phpunit packages/order-checkout-bundle/tests

# 运行测试并生成覆盖率报告
./vendor/bin/phpunit packages/order-checkout-bundle/tests --coverage-html coverage
```

## 性能考虑

- **缓存**：库存验证使用可配置缓存，默认TTL为5分钟
- **批量操作**：支持批量购物车操作以减少数据库查询
- **延迟加载**：购物车商品仅在需要时加载
- **优化查询**：仓储使用带有适当连接的优化DQL查询

## 安全性

- **用户隔离**：所有购物车操作都限定在已认证用户范围内
- **输入验证**：对所有输入参数进行全面验证
- **SQL注入防护**：使用Doctrine ORM参数化查询
- **访问控制**：管理员程序需要适当权限

## 贡献指南

详情请参阅[CONTRIBUTING.md](CONTRIBUTING.md)。

## 版权和许可

MIT许可证。详情请参阅[许可证文件](LICENSE)。