# 订单完成事件 (OrderCompletedEvent)

## 概述

`OrderCompletedEvent` 是在 `ProcessCheckoutProcedure` 成功完成下单后分发的事件。它包含订单的基本信息以及下单过程中的相关元数据，方便其他系统组件监听并执行相应的业务逻辑。

## 事件信息

### 基本属性

- **订单ID** (`getOrderId()`): 订单的唯一标识符
- **订单编号** (`getOrderNumber()`): 订单的业务编号  
- **下单用户** (`getUser()`): 执行下单操作的用户
- **订单总金额** (`getTotalAmount()`): 订单的总金额
- **是否需要支付** (`isPaymentRequired()`): 订单是否需要支付
- **订单状态** (`getOrderState()`): 订单的当前状态

### 元数据信息

事件还包含丰富的元数据，可以通过以下方法获取：

- `getMetadata()`: 获取所有元数据
- `getMetadataValue(string $key, mixed $default = null)`: 获取特定元数据值

### 便利方法

#### 订单类型判断
```php
$event->isNormalOrder()     // 是否为正常订单
$event->isRedeemOrder()     // 是否为兑换券订单
$event->isOrderType($type)  // 判断特定订单类型
```

#### 优惠券相关
```php
$event->hasCoupons()        // 是否使用了优惠券
$event->getCouponCodes()    // 获取使用的优惠券代码列表
```

#### 积分相关
```php
$event->hasPointsUsed()     // 是否使用了积分
$event->getPointsUsed()     // 获取使用的积分数量
```

#### 库存警告
```php
$event->hasStockWarnings()  // 是否有库存警告
$event->getStockWarnings()  // 获取库存警告信息
```

#### 其他信息
```php
$event->getAddressId()      // 获取收货地址ID
$event->getOrderRemark()    // 获取订单备注
```

## 事件监听

### 创建事件监听器

```php
<?php

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\OrderCheckoutBundle\Event\OrderCreateAfterEvent;

#[AsEventListener(event: OrderCreateAfterEvent::class)]
class MyOrderCompletedEventListener
{
    public function __invoke(OrderCreateAfterEvent $event): void
    {
        // 获取订单基本信息
        $orderId = $event->getOrderId();
        $orderNumber = $event->getOrderNumber();
        $user = $event->getUser();
        
        // 根据订单类型执行不同逻辑
        if ($event->isRedeemOrder()) {
            $this->handleRedeemOrder($event);
        } elseif ($event->isNormalOrder()) {
            $this->handleNormalOrder($event);
        }
        
        // 处理优惠券使用
        if ($event->hasCoupons()) {
            $coupons = $event->getCouponCodes();
            // 处理优惠券使用逻辑
        }
        
        // 处理积分使用
        if ($event->hasPointsUsed()) {
            $points = $event->getPointsUsed();
            // 处理积分使用逻辑
        }
    }
    
    private function handleRedeemOrder(OrderCreateAfterEvent $event): void
    {
        // 兑换券订单处理逻辑
    }
    
    private function handleNormalOrder(OrderCreateAfterEvent $event): void
    {
        // 正常订单处理逻辑
    }
}
```

### 服务配置

如果不使用属性方式，也可以通过服务配置：

```yaml
# config/services.yaml
services:
    App\EventListener\MyOrderCompletedEventListener:
        tags:
            - { name: kernel.event_listener, event: Tourze\OrderCheckoutBundle\Event\OrderCompletedEvent }
```

## 使用场景

### 1. 订单通知
```php
public function __invoke(OrderCompletedEvent $event): void
{
    // 发送订单确认邮件
    $this->emailService->sendOrderConfirmation(
        $event->getUser()->getEmail(),
        $event->getOrderNumber(),
        $event->getTotalAmount()
    );
    
    // 发送SMS通知
    if ($event->isPaymentRequired()) {
        $this->smsService->sendPaymentReminder($event->getUser()->getPhone());
    }
}
```

### 2. 库存管理
```php
public function __invoke(OrderCompletedEvent $event): void
{
    // 处理库存警告
    if ($event->hasStockWarnings()) {
        foreach ($event->getStockWarnings() as $warning) {
            $this->stockAlertService->sendAlert($warning);
        }
    }
}
```

### 3. 积分系统
```php
public function __invoke(OrderCompletedEvent $event): void
{
    // 扣减使用的积分
    if ($event->hasPointsUsed()) {
        $this->pointsService->deductPoints(
            $event->getUser(),
            $event->getPointsUsed(),
            $event->getOrderNumber()
        );
    }
    
    // 根据消费金额奖励积分
    if ($event->getTotalAmount() > 0) {
        $earnedPoints = $this->pointsService->calculateEarnedPoints($event->getTotalAmount());
        $this->pointsService->awardPoints($event->getUser(), $earnedPoints);
    }
}
```

### 4. 营销自动化
```php
public function __invoke(OrderCompletedEvent $event): void
{
    // 首次购买用户的特殊处理
    if ($this->userService->isFirstOrder($event->getUser())) {
        $this->marketingService->sendWelcomeBonus($event->getUser());
    }
    
    // 大额订单的VIP服务
    if ($event->getTotalAmount() > 1000) {
        $this->vipService->assignPersonalConsultant($event->getUser());
    }
}
```

### 5. 数据统计
```php
public function __invoke(OrderCompletedEvent $event): void
{
    // 更新用户统计
    $this->statisticsService->updateUserOrderStats(
        $event->getUser(),
        $event->getTotalAmount()
    );
    
    // 更新优惠券使用统计
    if ($event->hasCoupons()) {
        foreach ($event->getCouponCodes() as $couponCode) {
            $this->statisticsService->updateCouponUsageStats($couponCode);
        }
    }
}
```

## 事件时机

事件在以下时机分发：

1. ✅ 订单创建成功
2. ✅ 价格计算完成
3. ✅ 库存验证通过（或有警告但允许继续）
4. ✅ 订单数据持久化完成
5. ✅ 优惠券兑换完成（如有）
6. ✅ 响应数据格式化完成

**注意**: 事件在返回结果给客户端之前分发，确保所有下单逻辑都已完成。

## 错误处理

如果事件监听器中发生异常，不会影响订单创建的成功状态，但会记录错误日志。建议在监听器中：

1. 使用 try-catch 捕获异常
2. 记录详细的错误日志
3. 对于非关键业务，允许失败而不影响主流程

```php
public function __invoke(OrderCompletedEvent $event): void
{
    try {
        // 业务逻辑
        $this->someService->doSomething($event);
    } catch (\Exception $e) {
        $this->logger->error('订单完成事件处理失败', [
            'orderId' => $event->getOrderId(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // 对于非关键业务，可以静默失败
        // 对于关键业务，可以考虑重新抛出异常
    }
}
```