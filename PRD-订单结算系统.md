# PRD - 订单结算系统（Order Checkout Bundle）

## 📋 产品概述

### 产品定义
订单结算系统是电商平台的核心交易模块，负责处理购物车管理、价格计算、促销优惠、运费计算、优惠券应用等复杂业务逻辑，为用户提供完整的下单结算体验。

### 核心价值
- **业务价值**：提升电商平台的转化率和用户体验，支撑完整的交易闭环
- **技术价值**：提供高度解耦、可扩展的价格计算引擎和结算服务
- **用户价值**：实时准确的价格计算、透明的优惠展示、流畅的结算体验

## 🎯 设计原则

### 1. 高度解耦设计
- 价格计算与促销活动解耦
- 购物车存储与商品信息解耦  
- 运费计算与订单逻辑解耦
- 优惠券系统与订单系统解耦

### 2. 实时计算策略
- 购物车仅存储必要的商品标识信息
- 所有展示数据（价格、库存、促销）实时获取
- 确保用户看到的信息始终准确

### 3. 接口化设计
- 所有核心功能通过接口暴露
- 支持多种促销策略的插拔式扩展
- 支持多种价格计算器的组合使用

## 📊 功能规划

### 1. 购物车管理模块

#### 1.1 购物车存储
**存储内容**：
- 用户 ID（必需）
- 商品 SKU ID（必需）
- 商品数量（必需）
- 加入时间（用于过期清理）
- 规格信息（颜色、尺寸等）
- 备注信息（可选）

**不存储内容**：
- 商品名称、图片、详情（实时获取）
- 商品价格（实时计算）
- 促销信息（实时匹配）
- 库存状态（实时查询）

#### 1.2 购物车操作
- 添加商品到购物车
- 更新商品数量
- 删除购物车商品
- 清空购物车
- 购物车商品合并（登录后）

### 2. 价格计算引擎

#### 2.1 多层价格计算架构
```
基础价格 → 商品级促销 → 订单级促销 → 优惠券 → 最终价格
```

#### 2.2 价格计算器接口
```php
interface PriceCalculatorInterface
{
    public function calculate(CalculationContext $context): PriceResult;
    public function supports(CalculationContext $context): bool;
    public function getPriority(): int;
}
```

#### 2.3 核心价格计算器
- **基础价格计算器**：获取商品基础售价
- **商品促销计算器**：秒杀、团购、限时折扣等
- **订单促销计算器**：满减、满折、阶梯优惠等
- **优惠券计算器**：现金券、折扣券、满减券等
- **运费计算器**：基于地区、重量、体积的运费计算

### 3. 促销系统接口

#### 3.1 促销匹配接口
```php
interface PromotionMatcherInterface
{
    public function match(array $items, PromotionContext $context): PromotionResult;
    public function getType(): string;
}
```

#### 3.2 促销类型支持
- **商品级促销**
  - 限时特价
  - 秒杀活动
  - 团购优惠
  - 会员专享价

- **订单级促销**
  - 满 X 元减 Y 元
  - 满 X 件打 Y 折
  - 阶梯式满减
  - 买 X 送 Y

- **组合促销**
  - 套餐优惠
  - 跨品类满减
  - 店铺级促销

### 4. 库存验证模块

#### 4.1 库存检查接口
```php
interface StockValidatorInterface
{
    public function validate(array $items): StockValidationResult;
    public function getAvailableQuantity(string $skuId): int;
}
```

#### 4.2 特殊库存逻辑
- **赠品库存验证**
  - 买赠：主品与赠品必须同仓有货
  - 满赠：赠品无货时可正常下单，提示"先到先得"
- **预售商品验证**
- **限购数量验证**

### 5. 运费计算模块

#### 5.1 运费计算接口
```php
interface ShippingCalculatorInterface
{
    public function calculate(ShippingContext $context): ShippingResult;
    public function supports(array $items, string $region): bool;
}
```

#### 5.2 运费模板支持
- **单品运费模板**：特定商品到特定地区的运费规则
- **订单运费模板**：基于订单金额/重量的运费规则
- **包邮规则**：满额包邮、指定地区包邮等

### 6. 优惠券系统接口

#### 6.1 优惠券验证接口
```php
interface CouponValidatorInterface
{
    public function validate(string $couponCode, CouponContext $context): CouponValidationResult;
    public function getAvailableCoupons(string $userId, array $items): array;
}
```

#### 6.2 优惠券类型
- **现金券**：满 X 减 Y
- **折扣券**：X 折优惠
- **免邮券**：免运费
- **新人券**：首次购买专享

## 🏗️ 技术架构

### 1. 核心实体设计

#### 1.1 购物车实体 (Cart)
```php
class Cart
{
    private string $id;
    private string $userId;
    private array $items;
    private \DateTimeInterface $createdAt;
    private \DateTimeInterface $updatedAt;
}
```

#### 1.2 购物车项实体 (CartItem)
```php
class CartItem
{
    private string $id;
    private string $cartId;
    private string $skuId;
    private int $quantity;
    private array $attributes; // 规格信息
    private ?string $remark;
    private \DateTimeInterface $addedAt;
}
```

#### 1.3 计算上下文 (CalculationContext)
```php
class CalculationContext
{
    private string $userId;
    private array $items;
    private string $region;
    private array $appliedCoupons;
    private \DateTimeInterface $calculateTime;
}
```

### 2. 服务层设计

#### 2.1 购物车服务 (CartService)
- 购物车 CRUD 操作
- 购物车数据验证
- 购物车合并逻辑

#### 2.2 结算服务 (CheckoutService)
- 统筹整个结算流程
- 协调各个计算器执行
- 生成最终结算结果

#### 2.3 价格计算服务 (PriceCalculationService)
- 管理价格计算器链
- 执行多层价格计算
- 生成价格明细

### 3. 数据库设计

#### 3.1 购物车表 (carts)
```sql
CREATE TABLE carts (
    id VARCHAR(20) PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    INDEX idx_user_id (user_id)
);
```

#### 3.2 购物车项表 (cart_items)
```sql
CREATE TABLE cart_items (
    id VARCHAR(20) PRIMARY KEY,
    cart_id VARCHAR(20) NOT NULL,
    sku_id VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    attributes JSON,
    remark TEXT,
    added_at TIMESTAMP NOT NULL,
    INDEX idx_cart_id (cart_id),
    INDEX idx_sku_id (sku_id)
);
```

## 🔧 接口设计

### 1. 购物车管理接口

#### 1.1 添加商品到购物车
```php
POST /api/cart/items
{
    "sku_id": "sku_123",
    "quantity": 2,
    "attributes": {"color": "红色", "size": "L"},
    "remark": "备注信息"
}
```

#### 1.2 获取购物车信息
```php
GET /api/cart
Response: {
    "cart_id": "cart_123",
    "items": [...],
    "summary": {
        "total_items": 5,
        "subtotal": 299.00,
        "discount": 50.00,
        "shipping": 10.00,
        "total": 259.00
    },
    "promotions": [...],
    "available_coupons": [...]
}
```

### 2. 价格计算接口

#### 2.1 实时价格计算
```php
POST /api/checkout/calculate
{
    "items": [
        {"sku_id": "sku_123", "quantity": 2},
        {"sku_id": "sku_456", "quantity": 1}
    ],
    "region": "beijing",
    "coupons": ["coupon_789"]
}
```

#### 2.2 促销匹配
```php
POST /api/promotions/match
{
    "items": [...],
    "user_id": "user_123"
}
```

## 📈 性能要求

### 1. 响应时间
- 购物车查询：< 200ms
- 价格计算：< 500ms
- 促销匹配：< 300ms

### 2. 并发支持
- 支持单用户高频操作（秒杀场景）
- 支持多用户并发访问
- 购物车操作支持乐观锁

### 3. 缓存策略
- 商品基础信息缓存（1小时）
- 促销规则缓存（10分钟）
- 用户购物车缓存（30分钟）

## 🔐 安全考虑

### 1. 数据安全
- 购物车数据加密存储
- 价格计算服务端验证
- 防止价格篡改攻击

### 2. 业务安全
- 库存超卖防护
- 促销规则防刷
- 优惠券防薅羊毛

## 🚀 扩展性设计

### 1. 插件化促销规则
- 通过接口实现新的促销类型
- 支持促销规则热插拔
- 促销优先级可配置

### 2. 多渠道支持
- 支持 B2C、B2B 不同计价逻辑
- 支持多货币、多语言
- 支持多租户隔离

### 3. 第三方集成
- 支持外部促销系统对接
- 支持第三方支付计算
- 支持外部库存系统

## 📋 开发计划

### Phase 1：基础功能（2周）
- [ ] 购物车基础 CRUD
- [ ] 基础价格计算引擎
- [ ] 简单促销匹配
- [ ] 基础运费计算

### Phase 2：高级功能（3周）
- [ ] 复杂促销规则
- [ ] 优惠券系统集成
- [ ] 库存验证逻辑
- [ ] 赠品处理逻辑

### Phase 3：优化增强（2周）
- [ ] 性能优化
- [ ] 缓存策略
- [ ] 监控告警
- [ ] 压力测试

### Phase 4：扩展功能（2周）
- [ ] 多渠道支持
- [ ] 第三方集成
- [ ] 管理后台
- [ ] 数据分析

## 🎯 成功指标

### 1. 技术指标
- 代码覆盖率 ≥ 90%
- PHPStan Level 8 零错误
- API 响应时间达标率 ≥ 95%

### 2. 业务指标
- 购物车转化率提升 5%
- 价格计算准确率 100%
- 用户投诉率 < 0.1%

---

*本 PRD 定义了订单结算系统的完整功能范围，强调了高度解耦的架构设计和接口化的扩展能力，为电商平台提供强大而灵活的结算能力。*