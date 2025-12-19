# 积分商城购买场景 - 完整测试套件

> **测试交付版本**: v1.0.0
> **交付日期**: 2025-11-20
> **状态**: ✅ 测试用例已完成

---

## 📦 交付内容

### 1. 测试文件
- **IntegralMallCheckoutTest.php** - 积分商城购买场景的完整验收测试套件

### 2. 文档
- **INTEGRAL_MALL_TEST_PLAN.md** - 测试计划和覆盖范围
- **TEST_EXECUTION_SUMMARY.md** - 测试执行总结和待实现功能清单
- **README.md** (本文件) - 测试套件概览

---

## 🎯 测试覆盖场景

### ✅ 已实现的测试用例 (7个)

| # | 测试用例 | 场景描述 | 优先级 | 状态 |
|---|---------|---------|--------|------|
| 1 | testPureIntegralProductPriceCalculation | 纯积分商品价格计算 | P0 | ❌ 等待功能 |
| 2 | testMixedProductPriceCalculation | 混合商品(现金+积分)价格计算 | P0 | ❌ 等待功能 |
| 3 | testMultipleProductsIntegralCalculation | 多商品混合场景(3个数据集) | P0 | ❌ 等待功能 |
| 4 | testInsufficientBalanceExceptionStructure | 积分不足异常验证 | P1 | ✅ 通过 |
| 5 | testServiceUnavailableExceptionStructure | 积分服务不可用异常验证 | P1 | ✅ 通过 |
| 6 | testRefundIntegralOnOrderCancel | 订单取消退还积分 | P0 | ❌ 等待功能 |
| 7 | testRefundIntegralIdempotency | 积分退还幂等性验证 | P0 | ❌ 等待功能 |

**测试通过率**: 2/7 (28.57%)
**功能完整度**: 5/7 测试等待功能实现

---

## 🏗️ 测试架构

### 测试分层

```
IntegralMallCheckoutTest
├── 价格计算层 (BasePriceCalculator)
│   ├── 纯积分商品
│   ├── 混合商品
│   └── 多商品组合 (DataProvider)
│
├── 异常处理层
│   ├── 积分不足异常
│   └── 服务不可用异常
│
└── 积分退还层 (IntegralRefundService)
    ├── 订单取消退还
    └── 幂等性验证
```

### Mock 依赖

测试使用以下 Mock 对象隔离外部依赖:
- `IntegralServiceInterface` - 积分服务
- `SkuLoaderInterface` - SKU 加载器
- `EntityManagerInterface` - 数据库操作
- `PriceCalculationService` - 价格计算服务
- `StockValidatorInterface` - 库存验证
- 其他...

---

## 🚀 快速开始

### 运行测试

```bash
# 运行完整测试套件
./vendor/bin/phpunit packages/order-checkout-bundle/tests/Integration/IntegralMallCheckoutTest.php

# 运行单个测试
./vendor/bin/phpunit --filter testPureIntegralProductPriceCalculation packages/order-checkout-bundle/tests/Integration/IntegralMallCheckoutTest.php

# 详细输出
./vendor/bin/phpunit --testdox packages/order-checkout-bundle/tests/Integration/IntegralMallCheckoutTest.php

# 生成覆盖率报告
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage packages/order-checkout-bundle/tests/Integration/IntegralMallCheckoutTest.php
```

---

## 📋 测试数据提供者 (DataProvider)

### provideMultipleProductScenarios

提供3组测试数据,覆盖不同的商品组合:

1. **两个纯积分商品**
   - SKU1: 积分=100, 现金=0, 数量=1
   - SKU2: 积分=50, 现金=0, 数量=2
   - 预期: 现金=0, 积分=200

2. **纯积分 + 混合商品**
   - SKU1: 积分=100, 现金=0, 数量=1
   - SKU2: 积分=50, 现金=99, 数量=1
   - 预期: 现金=99, 积分=150

3. **三个混合商品**
   - SKU1: 积分=30, 现金=50, 数量=2
   - SKU2: 积分=20, 现金=30, 数量=1
   - SKU3: 积分=0, 现金=100, 数量=1
   - 预期: 现金=230, 积分=80

---

## ⚙️ 测试配置

### PHPUnit 配置
- **版本**: 11.5.44
- **PHP 版本**: 8.4.14
- **测试框架**: PHPUnit
- **配置文件**: `/phpunit.xml`

### 测试标签
- `@internal` - 内部测试
- `#[CoversClass(CheckoutService::class)]` - 覆盖类
- `#[DataProvider('provideMultipleProductScenarios')]` - 数据提供者

---

## 📊 测试覆盖目标

### 代码覆盖率目标
- **行覆盖率**: ≥ 90%
- **分支覆盖率**: ≥ 85%
- **关键路径覆盖**: 100%

### 当前状态
- **实际覆盖率**: 待计算 (功能实现后)
- **测试用例完整性**: ✅ 100%
- **文档完整性**: ✅ 100%

---

## 🔧 待实现功能清单

测试用例已完成,但以下功能需要实现才能让所有测试通过:

### P0 - 阻塞测试 (预估 7h)

1. **BasePriceCalculator 积分计算** (2h)
   - [ ] 读取 `SKU::getIntegralPrice()`
   - [ ] 累加积分需求
   - [ ] 在 PriceResult 中添加 `total_integral_required`

2. **CheckoutService 积分扣减逻辑** (4h)
   - [ ] 注入 `IntegralServiceInterface` 依赖
   - [ ] 实现 `deductIntegral()` 方法
   - [ ] 实现 `refundIntegral()` 回滚方法
   - [ ] 实现 `isPureIntegralOrder()` 判断
   - [ ] 纯积分订单自动标记为 PAID

3. **OrderIntegralInfo 实体完善** (1h)
   - [ ] 添加必需字段
   - [ ] 创建数据库迁移

详见: [TEST_EXECUTION_SUMMARY.md](./TEST_EXECUTION_SUMMARY.md)

---

## 📚 测试设计原则

### 1. 独立性
每个测试用例独立运行,不依赖其他测试的执行顺序和结果。

### 2. 可重复性
测试使用 Mock 对象隔离外部依赖,确保结果可重复。

### 3. 清晰性
- 明确的测试命名 (testXxx)
- 详细的文档注释
- Given-When-Then 结构

### 4. 完整性
- 正常场景 + 异常场景
- 边界值测试
- 数据组合测试 (DataProvider)

### 5. 可维护性
- Mock 配置集中在 setUp()
- 辅助方法提取 (createIntegralSku)
- 常量和魔术数字说明

---

## 🎓 测试用例示例

### 示例: 纯积分商品价格计算

```php
public function testPureIntegralProductPriceCalculation(): void
{
    // Given: 准备纯积分商品 (积分=100, 现金=0, 数量=2)
    $sku = $this->createIntegralSku(skuId: 1001, integralPrice: 100, cashPrice: 0.0);
    $this->skuLoader->method('loadSkuByIdentifier')->willReturn($sku);

    $checkoutItem = new CheckoutItem(id: null, skuId: 1001, quantity: 2, selected: true, sku: $sku);
    $context = new CalculationContext($this->user, [$checkoutItem], [], []);

    // When: 执行价格计算
    $priceResult = $this->basePriceCalculator->calculate($context);

    // Then: 验证结果
    $this->assertEquals('0.00', $priceResult->getFinalPrice(), '纯积分商品现金价格应为0');

    $details = $priceResult->getDetails();
    $this->assertEquals(200, $details['total_integral_required'], '总积分需求应为 100 * 2 = 200');
}
```

---

## 📖 相关文档

### 需求文档
- [1120积分需求变更.md](../../1120积分需求变更.md) - 积分功能需求
- [1120积分扣减业务需求.md](../../1120积分扣减业务需求.md) - 业务协作方案
- [PRD-订单结算系统.md](../../PRD-订单结算系统.md) - 产品需求文档

### 技术文档
- [IMPLEMENTATION.md](../../IMPLEMENTATION.md) - 实现指南
- [PROCEDURES.md](../../PROCEDURES.md) - RPC 接口文档

---

## 🤝 贡献指南

### 添加新测试用例

1. 在 `IntegralMallCheckoutTest.php` 中添加测试方法
2. 使用 `test` 前缀命名方法
3. 添加 PHPDoc 注释说明测试场景
4. 遵循 Given-When-Then 结构
5. 更新文档

### 修改现有测试

1. 确保修改不影响其他测试
2. 更新相关文档
3. 运行完整测试套件验证
4. 提交前检查 PHPStan

---

## ✅ 验收标准

### 测试用例验收 ✅
- [x] 覆盖所有核心业务场景
- [x] 包含正常和异常路径
- [x] 使用数据提供者测试多种组合
- [x] Mock 对象配置合理
- [x] 测试命名清晰
- [x] 文档完善

### 功能实现验收 ⏳
- [ ] 所有测试通过
- [ ] 代码覆盖率 ≥ 90%
- [ ] PHPStan Level 8 零错误
- [ ] 性能测试通过
- [ ] 集成测试通过

---

## 📞 联系方式

**测试负责人**: Claude
**技术支持**: order-checkout-bundle 维护团队
**文档更新**: 2025-11-20

---

## 📜 变更日志

### v1.0.0 (2025-11-20)
- ✅ 创建完整测试套件
- ✅ 实现 7 个核心测试用例
- ✅ 添加数据提供者支持
- ✅ 完善测试文档
- ✅ 生成执行总结

---

**测试套件状态**: ✅ 已交付
**下一步**: 等待功能实现,并执行完整测试验证
