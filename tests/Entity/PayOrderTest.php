<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use OrderCoreBundle\Entity\Contract;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(PayOrder::class)]
final class PayOrderTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new PayOrder();
    }

    public function testConstruct(): void
    {
        $payOrder = new PayOrder();

        $this->assertInstanceOf(PayOrder::class, $payOrder);
        $this->assertSame('0.00', $payOrder->getAmount());
        $this->assertNull($payOrder->getContract());
        $this->assertNull($payOrder->getTradeNo());
        $this->assertNull($payOrder->getPayTime());
    }

    public function testSetAndGetContract(): void
    {
        $payOrder = new PayOrder();
        $contract = new Contract();

        $payOrder->setContract($contract);

        $this->assertSame($contract, $payOrder->getContract());
    }

    public function testSetAndGetAmount(): void
    {
        $payOrder = new PayOrder();
        $amount = '123.45';

        $payOrder->setAmount($amount);

        $this->assertSame($amount, $payOrder->getAmount());
    }

    public function testSetAndGetTradeNo(): void
    {
        $payOrder = new PayOrder();
        $tradeNo = 'TXN123456789';

        $payOrder->setTradeNo($tradeNo);

        $this->assertSame($tradeNo, $payOrder->getTradeNo());
    }

    public function testSetAndGetPayTime(): void
    {
        $payOrder = new PayOrder();
        $payTime = new \DateTimeImmutable('2025-09-02 12:00:00');

        $payOrder->setPayTime($payTime);

        $this->assertSame($payTime, $payOrder->getPayTime());
    }

    public function testToString(): void
    {
        $payOrder = new PayOrder();

        // 当ID为null时，返回空字符串
        $this->assertSame('', (string) $payOrder);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    /** @return \Generator<string, array{string, mixed}> */
    public static function propertiesProvider(): \Generator
    {
        yield 'amount' => ['amount', '999.99'];
        yield 'tradeNo' => ['tradeNo', 'TXN123456789'];
        yield 'payTime' => ['payTime', new \DateTimeImmutable('2025-09-02 12:00:00')];
    }
}
