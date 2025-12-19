<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service\Order;

use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use OrderCoreBundle\Enum\OrderState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Service\Order\OrderProductBuilder;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;

#[CoversClass(OrderProductBuilder::class)]
#[RunTestsInSeparateProcesses]
final class OrderProductBuilderTest extends AbstractIntegrationTestCase
{
    private OrderProductBuilder $builder;

    protected function onSetUp(): void
    {
        $this->builder = self::getService(OrderProductBuilder::class);
    }

    #[Test]
    public function testCreateOrderProductsWithEmptyItemsReturnsEmptyArrays(): void
    {
        $contract = new Contract();
        $contract->setSn('TEST-001');
        $contract->setState(OrderState::INIT);

        $result = $this->builder->createOrderProducts($contract, [], []);

        self::assertSame([], $result['base']);
        self::assertSame([], $result['extra']);
        self::assertSame([], $result['extraItems']);
    }

    #[Test]
    public function testCreateOrderProductsWithBaseItemsCreatesOrderProducts(): void
    {
        $spu = new Spu();
        $spu->setTitle('测试商品');

        $sku = new Sku();
        $sku->setSpu($spu);

        $checkoutItem = CheckoutItem::fromArray([
            'skuId' => '123',
            'quantity' => 2,
            'selected' => true,
        ]);
        $checkoutItem = $checkoutItem->withSku($sku);

        $contract = new Contract();
        $contract->setSn('TEST-002');
        $contract->setState(OrderState::INIT);

        $result = $this->builder->createOrderProducts($contract, [$checkoutItem], []);

        self::assertCount(1, $result['base']);
        $orderProduct = $result['base'][0];
        self::assertInstanceOf(OrderProduct::class, $orderProduct);
        self::assertSame(2, $orderProduct->getQuantity());
        self::assertFalse($orderProduct->getIsGift());
        self::assertSame('normal', $orderProduct->getSource());

        // 验证已持久化
        self::assertTrue(self::getEntityManager()->contains($orderProduct));
    }

    #[Test]
    public function testCreateOrderProductsWithExtraItemsCreatesGiftProducts(): void
    {
        $spu = new Spu();
        $spu->setTitle('赠品');

        $sku = new Sku();
        $sku->setSpu($spu);

        $checkoutItem = CheckoutItem::fromArray([
            'skuId' => '456',
            'quantity' => 1,
            'selected' => true,
        ]);
        $checkoutItem = $checkoutItem->withSku($sku);

        $extraItems = [
            [
                'item' => $checkoutItem,
                'type' => 'coupon_gift',
                'unit_price' => '0.00',
                'total_price' => '0.00',
            ],
        ];

        $contract = new Contract();
        $contract->setSn('TEST-003');
        $contract->setState(OrderState::INIT);

        $result = $this->builder->createOrderProducts($contract, [], $extraItems);

        self::assertSame([], $result['base']);
        self::assertCount(1, $result['extra']);

        $giftProduct = $result['extra'][0];
        self::assertInstanceOf(OrderProduct::class, $giftProduct);
        self::assertTrue($giftProduct->getIsGift());
        self::assertSame('coupon_gift', $giftProduct->getSource());
        // Remark should be set by CouponWorkflowHelper service
        self::assertIsString($giftProduct->getRemark());

        // 验证已持久化
        self::assertTrue(self::getEntityManager()->contains($giftProduct));
    }

    #[Test]
    public function testCreateOrderProductsWithInvalidExtraItemSkipsInvalid(): void
    {
        $contract = new Contract();
        $contract->setSn('TEST-005');
        $contract->setState(OrderState::INIT);

        $extraItems = [
            [
                'item' => 'invalid',
                'type' => 'coupon_gift',
                'unit_price' => '0.00',
                'total_price' => '0.00',
            ],
        ];

        $result = $this->builder->createOrderProducts($contract, [], $extraItems);

        self::assertSame([], $result['base']);
        self::assertSame([], $result['extra']);
    }

    #[Test]
    public function testCreateOrderProductsSetsIntegralPriceFromSku(): void
    {
        $spu = new Spu();
        $spu->setTitle('积分商品');

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setIntegralPrice(100);

        $checkoutItem = CheckoutItem::fromArray(['skuId' => '789', 'quantity' => 1, 'selected' => true]);
        $checkoutItem = $checkoutItem->withSku($sku);

        $contract = new Contract();
        $contract->setSn('TEST-006');
        $contract->setState(OrderState::INIT);

        // 使用 INTEGRAL_ONLY 支付模式来测试积分价格设置
        $user = new class implements \Symfony\Component\Security\Core\User\UserInterface {
            public function getRoles(): array { return ['ROLE_USER']; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'test-user'; }
        };
        $context = new \Tourze\OrderCheckoutBundle\DTO\CalculationContext(
            $user,
            [$checkoutItem],
            [],
            ['paymentMode' => 'INTEGRAL_ONLY']
        );

        $result = $this->builder->createOrderProducts($contract, [$checkoutItem], [], $context);

        self::assertCount(1, $result['base']);
        $orderProduct = $result['base'][0];
        self::assertSame(100, $orderProduct->getIntegralPrice());

        // 验证已持久化
        self::assertTrue(self::getEntityManager()->contains($orderProduct));
    }
}
