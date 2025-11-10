# 技术债记录 - Order Checkout Bundle

## 类复杂度超限

### CheckoutService
- **当前复杂度**: 68
- **限制**: 50
- **文件**: `src/Service/CheckoutService.php`
- **建议**: 需要架构重构，拆分为多个协作类
- **风险**: 高 - 核心业务逻辑类，修改影响范围大
- **优先级**: P2 - 建议在下次大版本重构时处理

### CouponWorkflowHelper
- **当前复杂度**: 79
- **限制**: 50
- **文件**: `src/Helper/CouponWorkflowHelper.php`
- **建议**: 考虑引入 Strategy 模式或 Visitor 模式重构
- **风险**: 中 - 辅助类，但与多个模块耦合
- **优先级**: P2 - 建议在下次大版本重构时处理

## 缺失测试覆盖

### CouponProviderChain
- **文件**: `src/Provider/CouponProviderChain.php`
- **缺失测试**: `tests/Provider/CouponProviderChainTest.php`
- **原因**: 需要 mock 多个 Provider 实现，测试场景复杂
- **建议**:
  - 编写集成测试，测试 Provider 链的调用顺序
  - Mock 各个 Provider 的返回值
  - 验证 priority 排序逻辑
- **优先级**: P1 - 核心业务逻辑需要测试保障

### LocalCouponProvider
- **文件**: `src/Provider/LocalCouponProvider.php`
- **缺失测试**: `tests/Provider/LocalCouponProviderTest.php`
- **原因**: 需要 mock Repository、EventDispatcher、Logger 等多个依赖
- **建议**:
  - 使用 PHPUnit mock 框架 mock 所有外部依赖
  - 测试优惠券查询逻辑
  - 测试 ExternalCouponRequestedEvent 事件分发
  - 验证异常处理逻辑
- **优先级**: P1 - 核心业务逻辑需要测试保障

### CouponWorkflowHelper
- **文件**: `src/Helper/CouponWorkflowHelper.php`
- **缺失测试**: `tests/Helper/CouponWorkflowHelperTest.php`
- **原因**: 类复杂度 79，包含大量业务逻辑分支，测试成本高
- **建议**:
  - 优先重构降低复杂度
  - 拆分为多个可测试的小类
  - 再补充测试覆盖
- **优先级**: P2 - 建议先重构再测试

### CouponRecommendationService
- **文件**: `src/Service/CouponRecommendationService.php`
- **缺失测试**: `tests/Service/CouponRecommendationServiceTest.php`
- **原因**: 需要 mock CouponProviderChain、PriceCalculationService 等多个 Service
- **建议**:
  - 编写集成测试验证优惠券推荐逻辑
  - Mock 价格计算服务
  - 验证最优优惠券选择算法
  - 测试边界条件（无可用优惠券、多个等价优惠券等）
- **优先级**: P1 - 核心业务逻辑需要测试保障

## 已完成改进

### ✅ 方法复杂度修复
- **CalculatePriceProcedure::execute()**: 从复杂度 11 降至 <10
- **方法**: 提取 6 个私有方法
- **提交**: 待回归验证后提交

### ✅ 测试覆盖补充（已完成）
- **RecommendedCouponTest**: 25+ 测试方法，覆盖 DTO 所有公共方法
- **ExternalCouponRequestedEventTest**: 覆盖 Event 的构造、getter、setter 和状态检测
- **OrderCompletedEventTest**: 覆盖 Event 的所有 getter、metadata 处理和业务逻辑方法

## 下一步行动

1. **立即**: 运行回归验证，确认已修复代码无引入新问题
2. **本周期**: 补充 P1 优先级的测试文件（Provider、Service）
3. **下周期**: 制定类复杂度重构方案，提交技术评审

---
**更新时间**: 2025-11-10
**维护人**: Claude Code
