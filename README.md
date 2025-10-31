# Order Checkout Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/order-checkout-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/order-checkout-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/order-checkout-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/order-checkout-bundle)
[![License](https://img.shields.io/github/license/tourze/order-checkout-bundle.svg?style=flat-square)](LICENSE)

A comprehensive Symfony bundle for order checkout functionality, including shopping cart management, price calculation, promotion matching, stock validation, and shipping calculation.

## Features

- **Shopping Cart Management**: Complete cart operations (add, remove, update, clear)
- **Price Calculation**: Flexible price calculation system with support for base prices, discounts, and promotions
- **Promotion System**: Built-in promotion matching with support for full reduction and custom promotion rules
- **Stock Validation**: Real-time stock validation with configurable cache support
- **Shipping Calculation**: Basic shipping calculation with regional support and free shipping thresholds
- **Admin Operations**: Administrative cart management for customer support
- **JSON-RPC Procedures**: Ready-to-use JSON-RPC endpoints for frontend integration
- **Comprehensive Testing**: Full test coverage with real-world scenarios

## Installation

### Requirements

- PHP 8.2 or higher
- Symfony 7.3 or higher
- Doctrine ORM 3.0 or higher
- Required bundles: product-core-bundle, biz-user-bundle

### Via Composer

```bash
composer require tourze/order-checkout-bundle
```

### Bundle Registration

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    Tourze\OrderCheckoutBundle\OrderCheckoutBundle::class => ['all' => true],
];
```

## Quick Start

### Update: Recent Quality Improvements

**v0.0.1** - Major quality and testing improvements:
- ✅ Fixed entity class annotations and Stringable interface implementation
- ✅ Enhanced test coverage with proper integration test patterns
- ✅ Created comprehensive DataFixtures for development/testing
- ✅ Improved PHPStan compliance and type safety
- ✅ Fixed JSON-RPC procedure attribute requirements

**Quality Status**: 468 tests passing with 1283 assertions, PHPStan level 8 compliance with minimal remaining issues.

### Basic Cart Operations

```php
<?php
use Tourze\OrderCheckoutBundle\Service\CartService;

// Add item to cart
$cartService->addToCart($user, $sku, $quantity, $attributes, $remark);

// Update quantity
$cartService->updateQuantity($user, $cartItemId, $newQuantity);

// Remove item
$cartService->removeFromCart($user, $cartItemId);

// Get cart items
$items = $cartService->getCartItems($user, $selectedOnly = false);
```

### Price Calculation

```php
<?php
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;

$context = new CalculationContext($user, $cartItems, $appliedCoupons, $metadata);
$priceResult = $priceCalculationService->calculate($context);

echo "Original Price: " . $priceResult->getOriginalPrice();
echo "Final Price: " . $priceResult->getFinalPrice();
echo "Discount: " . $priceResult->getDiscount();
```

### Stock Validation

```php
<?php
use Tourze\OrderCheckoutBundle\Service\BasicStockValidator;

$validator = new BasicStockValidator($cache);
$result = $validator->validate($cartItems);

if ($result->isValid()) {
    echo "All items are in stock";
} else {
    foreach ($result->getErrors() as $skuId => $error) {
        echo "Stock error for $skuId: $error";
    }
}
```

## Architecture

### Core Components

1. **Entities**
    - `CartItem`: Shopping cart item entity with user, SKU, quantity, and metadata

2. **Services**
    - `CartService`: Main shopping cart operations
    - `PriceCalculationService`: Price calculation with calculator plugins
    - `CheckoutService`: Complete checkout workflow
    - `BasicStockValidator`: Stock validation with cache support

3. **Calculators**
    - `BasePriceCalculator`: Base price calculation
    - `PromotionCalculator`: Promotion-based price adjustments
    - `BasicShippingCalculator`: Shipping fee calculation

4. **DTOs**
    - `CalculationContext`: Context for price calculations
    - `PriceResult`: Price calculation results
    - `ShippingResult`: Shipping calculation results
    - `StockValidationResult`: Stock validation results

### Price Calculation System

The bundle uses a pluggable calculator system:

```php
// Register custom calculator
$priceCalculationService->addCalculator(new CustomPriceCalculator());

// Calculators are executed in priority order
class CustomPriceCalculator implements PriceCalculatorInterface
{
    public function getPriority(): int { return 100; }
    public function getType(): string { return 'custom'; }
    public function supports(CalculationContext $context): bool { /* logic */ }
    public function calculate(CalculationContext $context): PriceResult { /* logic */ }
}
```

## JSON-RPC Procedures

The bundle includes ready-to-use JSON-RPC procedures for frontend integration:

### Cart Procedures

- `AddToCartProcedure`: Add items to cart
- `UpdateCartQuantityProcedure`: Update item quantities
- `RemoveFromCartProcedure`: Remove items from cart
- `GetCartListProcedure`: Get cart contents
- `ClearCartProcedure`: Clear entire cart
- `ToggleCartSelectionProcedure`: Toggle item selection
- `BatchToggleCartSelectionProcedure`: Batch toggle selection

### Admin Procedures

- `AdminClearUserCartProcedure`: Admin clear user's cart
- `AdminGetUserCartProcedure`: Admin view user's cart

### Checkout Procedures

- `CalculatePriceProcedure`: Calculate prices and shipping
- `ValidateStockProcedure`: Validate stock availability
- `ProcessCheckoutProcedure`: Complete checkout process

## Configuration

### Service Configuration

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

### Cache Configuration

The bundle supports PSR-6 cache for stock validation:

```yaml
framework:
    cache:
        pools:
            stock_cache:
                adapter: cache.adapter.redis
                default_lifetime: 300
```

## API Reference

### CartService

Main service for shopping cart operations.

#### Methods

- `addToCart(UserInterface $user, Sku $sku, int $quantity, array $attributes = [], ?string $remark = null): CartItem`
- `updateQuantity(UserInterface $user, string $cartItemId, int $quantity): void`
- `removeFromCart(UserInterface $user, string $cartItemId): void`
- `getCartItems(UserInterface $user, bool $selectedOnly = false): array`
- `clearCart(UserInterface $user): void`

### PriceCalculationService

Service for calculating prices with support for multiple calculators.

#### Methods

- `addCalculator(PriceCalculatorInterface $calculator): void`
- `calculate(CalculationContext $context): PriceResult`
- `getCalculatorByType(string $type): ?PriceCalculatorInterface`

### BasicStockValidator

Service for validating stock availability.

#### Methods

- `validate(array $cartItems): StockValidationResult`
- `getAvailableQuantity(string $skuId): int`
- `getAvailableQuantities(array $skuIds): array`

## Testing

The bundle includes comprehensive tests covering all major functionality:

```bash
# Run tests
./vendor/bin/phpunit packages/order-checkout-bundle/tests

# Run with coverage
./vendor/bin/phpunit packages/order-checkout-bundle/tests --coverage-html coverage
```

## Performance Considerations

- **Caching**: Stock validation uses configurable cache with 5-minute default TTL
- **Batch Operations**: Support for batch cart operations to reduce database queries
- **Lazy Loading**: Cart items are loaded only when needed
- **Optimized Queries**: Repository uses optimized DQL queries with proper joins

## Security

- **User Isolation**: All cart operations are scoped to authenticated users
- **Input Validation**: Comprehensive validation for all input parameters
- **SQL Injection Protection**: Uses Doctrine ORM parameterized queries
- **Access Control**: Admin procedures require appropriate permissions

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.