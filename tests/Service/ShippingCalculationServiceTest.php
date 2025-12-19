<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationInput;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;
use Tourze\OrderCheckoutBundle\Service\ShippingCalculationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationService::class)]
#[RunTestsInSeparateProcesses]
final class ShippingCalculationServiceTest extends AbstractIntegrationTestCase
{
    private ShippingCalculationService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(ShippingCalculationService::class);
    }

    public function testCalculateWithEmptyItems(): void
    {
        $input = new ShippingCalculationInput('address1', []);

        $result = $this->service->calculate($input);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('商品列表为空', $result->errorMessage);
        $this->assertFalse($result->isDeliverable);
    }

    public function testCalculateWithInvalidAddress(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateWithNonexistentTemplate(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateWithNonDeliverableLocation(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateBasicScenario(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateWithFreeShipping(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateWithAreaSpecificRates(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateWithQuantityChargeType(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testCalculateMultipleTemplates(): void
    {
        self::markTestSkipped('需要真实地址和模板数据，跳过集成测试');
    }

    public function testServiceCanBeInstantiatedFromContainer(): void
    {
        $this->assertInstanceOf(ShippingCalculationService::class, $this->service);
    }
}
