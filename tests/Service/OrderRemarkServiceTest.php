<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkResult;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(OrderRemarkService::class)]
#[RunTestsInSeparateProcesses]
final class OrderRemarkServiceTest extends AbstractIntegrationTestCase
{
    private OrderRemarkService $orderRemarkService;

    protected function onSetUp(): void
    {
        $this->orderRemarkService = self::getService(OrderRemarkService::class);
    }

    public function testSaveOrderRemarkWithCleanContent(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testSaveOrderRemarkWithFilteredContent(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testSaveOrderRemarkWithNonExistentOrder(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testGetOrderRemarkExists(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testGetOrderRemarkNotExists(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testGetOrderRemarkHistory(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testSaveOrderRemarkWithInvalidContent(): void
    {
        self::markTestSkipped('需要真实订单数据，跳过集成测试');
    }

    public function testServiceCanBeInstantiatedFromContainer(): void
    {
        $this->assertInstanceOf(OrderRemarkService::class, $this->orderRemarkService);
    }
}
