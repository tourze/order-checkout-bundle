# 金额计算精度问题解决方案

## 问题背景

在 `PaymentService::calculateOrderTotal` 方法中，使用浮点数运算计算订单总金额时可能出现精度问题。例如：

```php
// 浮点数精度问题示例
$result = 0.1 + 0.2; // 实际结果：0.30000000000000004
echo $result === 0.3; // false
```

在金融计算中，这种精度问题是不可接受的，可能导致：
- 订单金额计算错误
- 微信支付金额转换错误（元转分）
- 财务对账不一致

## 解决方案

### 1. 创建 MoneyCalculator 工具类

使用 PHP 的 BCMath 扩展进行精确的货币计算：

```php
// 精确计算
$result = MoneyCalculator::add('0.1', '0.2'); // '0.30'
$sum = MoneyCalculator::sum(['99.99', '0.01']); // '100.00'
$cents = MoneyCalculator::toCents('99.99'); // 9999
```

### 2. 核心特性

- **精确计算**：使用 BCMath 避免浮点数精度问题
- **四舍五入**：在求和过程中保持高精度，最后统一四舍五入
- **类型安全**：支持 `string|int|float` 输入，内部转换为数值字符串
- **货币转换**：提供元/分转换方法，适用于微信支付等场景
- **完整API**：包含加减乘除、比较、格式化等完整功能

### 3. 主要方法

```php
// 基础运算
MoneyCalculator::add('1.11', '2.22');          // '3.33'
MoneyCalculator::subtract('3.33', '2.22');     // '1.11'
MoneyCalculator::multiply('2.50', '3');        // '7.50'
MoneyCalculator::divide('10.00', '3');         // '3.33'

// 比较运算
MoneyCalculator::compare('1.00', '2.00');      // -1
MoneyCalculator::equals('1.00', '1.00');       // true
MoneyCalculator::greaterThan('2.00', '1.00');  // true

// 实用功能
MoneyCalculator::sum(['1.11', '2.22', '3.33']); // '6.66'
MoneyCalculator::percentage('100.00', '10');    // '10.00'
MoneyCalculator::toCents('99.99');              // 9999
MoneyCalculator::fromCents(9999);               // '99.99'
MoneyCalculator::format('1234.56');             // '1,234.56'
```

### 4. PaymentService 集成

修改 `calculateOrderTotal` 方法使用精确计算：

```php
public function calculateOrderTotal(Contract $contract): float
{
    $amounts = [];
    foreach ($contract->getPrices() as $price) {
        if (false === $price->isRefund()) {
            $money = $price->getMoney();
            if (null !== $money) {
                $amounts[] = $money;
            }
        }
    }

    return (float) MoneyCalculator::sum($amounts);
}
```

## 测试覆盖

### 1. 单元测试

- **MoneyCalculatorTest**：完整的计算功能测试
- **PaymentServicePrecisionTest**：订单金额计算精度测试

### 2. 精度验证

```php
// 验证浮点数问题
$floatResult = 0.1 + 0.2; // 0.30000000000000004
$preciseResult = MoneyCalculator::add('0.1', '0.2'); // '0.30'

// 复杂计算验证
$amounts = ['12.345', '67.890', '0.001', '0.999', '100.00'];
$total = MoneyCalculator::sum($amounts); // '181.24' (四舍五入)
```

## 使用建议

1. **金融计算**：所有涉及金额的计算都应使用 `MoneyCalculator`
2. **数据库存储**：金额字段使用 `DECIMAL` 类型，避免 `FLOAT`
3. **API 接口**：金额参数使用字符串类型传递
4. **显示格式**：使用 `MoneyCalculator::format()` 格式化显示

## 性能考虑

- BCMath 计算比浮点数略慢，但精度要求高的场景必须使用
- 对于大量计算，可以考虑批量处理
- 内存使用基本与原浮点数计算相当

## 兼容性

- 要求 PHP BCMath 扩展（通常默认启用）
- 兼容现有代码，渐进式迁移
- 静态分析友好，支持 PHPStan 类型检查