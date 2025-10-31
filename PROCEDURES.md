# Order Checkout Bundle - Procedure 类文档

## 概述

本文档记录了 `order-checkout-bundle` 包中生成的所有 Procedure 类，包括购物车管理和订单结算相关的 RPC 接口。

## 购物车管理 Procedure

### 用户级操作

| 类名 | RPC 方法名 | 描述 | 基类 | 权限 |
|------|------------|------|------|------|
| `AddToCartProcedure` | `cart.add` | 添加商品到购物车 | LockableProcedure | ROLE_USER |
| `GetCartListProcedure` | `cart.list` | 获取用户购物车列表 | CacheableProcedure | ROLE_USER |
| `UpdateCartQuantityProcedure` | `cart.updateQuantity` | 更新购物车商品数量 | LockableProcedure | ROLE_USER |
| `ToggleCartSelectionProcedure` | `cart.toggleSelection` | 切换商品选中状态 | LockableProcedure | ROLE_USER |
| `BatchToggleCartSelectionProcedure` | `cart.batchToggleSelection` | 批量切换选中状态 | LockableProcedure | ROLE_USER |
| `RemoveFromCartProcedure` | `cart.remove` | 从购物车删除商品 | LockableProcedure | ROLE_USER |
| `ClearCartProcedure` | `cart.clear` | 清空购物车 | LockableProcedure | ROLE_USER |

### 管理员操作

| 类名 | RPC 方法名 | 描述 | 基类 | 权限 |
|------|------------|------|------|------|
| `AdminGetUserCartProcedure` | `admin.cart.getUserCart` | 查看指定用户购物车 | CacheableProcedure | ROLE_ADMIN |
| `AdminClearUserCartProcedure` | `admin.cart.clearUserCart` | 清空指定用户购物车 | LockableProcedure | ROLE_ADMIN |

## 订单结算 Procedure

| 类名 | RPC 方法名 | 描述 | 基类 | 权限 |
|------|------------|------|------|------|
| `CalculatePriceProcedure` | `checkout.calculatePrice` | 计算购物车价格（预结算） | CacheableProcedure | ROLE_USER |
| `ProcessCheckoutProcedure` | `checkout.process` | 执行订单结算（生成订单） | LockableProcedure | ROLE_USER |
| `ValidateStockProcedure` | `checkout.validateStock` | 验证购物车商品库存 | CacheableProcedure | ROLE_USER |

## API 使用示例

### 添加商品到购物车
```json
{
  "method": "cart.add",
  "params": {
    "skuId": 100,
    "quantity": 2,
    "attributes": {"color": "红色", "size": "L"},
    "remark": "请尽快发货"
  }
}
```

### 获取购物车列表
```json
{
  "method": "cart.list",
  "params": {
    "selectedOnly": false
  }
}
```

### 批量选中商品
```json
{
  "method": "cart.batchToggleSelection",
  "params": {
    "skuIds": [100, 101, 102],
    "selected": true
  }
}
```

### 计算价格（预结算）
```json
{
  "method": "checkout.calculatePrice",
  "params": {
    "addressId": 1,
    "couponCode": "DISCOUNT20",
    "pointsToUse": 100
  }
}
```

### 执行结算
```json
{
  "method": "checkout.process",
  "params": {
    "addressId": 1,
    "paymentMethod": "alipay",
    "couponCode": "DISCOUNT20",
    "pointsToUse": 100,
    "orderRemark": "请尽快发货"
  }
}
```

### 管理员查看用户购物车
```json
{
  "method": "admin.cart.getUserCart",
  "params": {
    "userId": 123,
    "selectedOnly": false
  }
}
```

## 错误处理

所有 Procedure 使用统一的错误处理机制：

- **业务逻辑错误**：抛出 `ApiException`，返回友好的中文错误信息
- **参数验证错误**：使用 Symfony Validator 约束自动处理
- **权限错误**：使用 `@IsGranted` 注解自动处理

常见错误信息：
- "商品不存在"
- "购物车中没有此商品"
- "购物车中没有选中的商品"
- "商品不可用"
- "商品数量必须大于0"

## 缓存策略

### 缓存时长设置

| 操作类型 | 缓存时长 | 原因 |
|----------|----------|------|
| 购物车列表 | 1分钟 | 购物车数据变化频繁 |
| 价格计算 | 2分钟 | 价格相关数据相对稳定 |
| 库存验证 | 30秒 | 库存信息变化最快 |
| 管理员操作 | 1分钟 | 管理员查看频率较低 |

### 缓存标签

所有缓存都使用适当的标签进行管理，支持精确失效：

- `cart`: 购物车相关
- `cart_user_{userId}`: 特定用户购物车
- `checkout`: 结算相关
- `price_calculation`: 价格计算
- `stock_validation`: 库存验证
- `coupon_{couponCode}`: 特定优惠券

## 并发控制

### 锁定策略

| 操作类型 | 锁定级别 | 锁定资源 |
|----------|----------|----------|
| 添加到购物车 | 用户级 | `cart_add:{userId}` |
| 更新数量 | SKU级 | `cart_update:{userId}:{skuId}` |
| 切换选中状态 | 用户级 | `cart_selection:{userId}` |
| 执行结算 | 用户级 | `checkout_process:{userId}` |
| 管理员操作 | 目标用户级 | `admin_clear_cart:{targetUserId}` |

### 幂等性支持

关键操作支持幂等性，避免重复执行：

- 添加商品：基于用户、SKU、数量、属性生成幂等Key
- 更新数量：基于用户、SKU、新数量生成幂等Key
- 执行结算：基于用户、地址、支付方式等生成幂等Key

## 日志记录

重要操作自动记录日志（使用 `@Log` 注解）：

- 添加/删除购物车商品
- 批量操作
- 执行结算
- 管理员操作

## 文件结构

```
src/Procedure/
├── Cart/                           # 购物车相关
│   ├── AddToCartProcedure.php
│   ├── GetCartListProcedure.php
│   ├── UpdateCartQuantityProcedure.php
│   ├── ToggleCartSelectionProcedure.php
│   ├── BatchToggleCartSelectionProcedure.php
│   ├── RemoveFromCartProcedure.php
│   ├── ClearCartProcedure.php
│   ├── AdminGetUserCartProcedure.php
│   └── AdminClearUserCartProcedure.php
└── Checkout/                       # 订单结算相关
    ├── CalculatePriceProcedure.php
    ├── ProcessCheckoutProcedure.php
    └── ValidateStockProcedure.php
```

## 依赖关系

所有 Procedure 类遵循依赖注入原则，主要依赖：

- `CartService`: 购物车业务逻辑
- `CheckoutService`: 结算业务逻辑
- `PriceCalculationService`: 价格计算
- `CartItemRepository`: 购物车数据访问
- `SkuRepository`: 商品数据访问
- `UserRepository`: 用户数据访问（管理员功能）

## 测试数据

每个 Procedure 都提供了 `getMockResult()` 方法，返回模拟数据供前端开发使用。这些模拟数据反映了真实的返回格式和数据结构。