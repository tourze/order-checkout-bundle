<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\OrderCheckoutBundle\Contract\AddressResolverInterface;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationInput;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateAreaRepository;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateRepository;
use Tourze\OrderCheckoutBundle\Service\ShippingCalculationService;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationService::class)]
final class ShippingCalculationServiceTest extends TestCase
{
    private ShippingCalculationService $service;

    private ShippingTemplateRepository&MockObject $templateRepository;

    private ShippingTemplateAreaRepository&MockObject $areaRepository;

    private AddressResolverInterface&MockObject $addressResolver;

    protected function setUp(): void
    {
        $this->templateRepository = $this->createMock(ShippingTemplateRepository::class);
        $this->areaRepository = $this->createMock(ShippingTemplateAreaRepository::class);
        $this->addressResolver = $this->createMock(AddressResolverInterface::class);

        $this->service = new ShippingCalculationService(
            $this->templateRepository,
            $this->areaRepository,
            $this->addressResolver
        );
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
        $items = [new ShippingCalculationItem('product1', 1, '1.000', '10.00')];
        $input = new ShippingCalculationInput('invalid_address', $items);

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('invalid_address')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('收货地址不存在', $result->errorMessage);
        $this->assertFalse($result->isDeliverable);
    }

    public function testCalculateWithNonexistentTemplate(): void
    {
        $items = [new ShippingCalculationItem('product1', 1, '1.000', '10.00', 'template1')];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '深圳市', 'district' => '南山区'];

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('find')
            ->with('template1')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('运费模板不存在', $result->errorMessage);
        $this->assertFalse($result->isDeliverable);
    }

    public function testCalculateWithNonDeliverableLocation(): void
    {
        $items = [new ShippingCalculationItem('product1', 1, '1.000', '10.00')];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '广州市', 'district' => '荔湾区'];

        $template = $this->createMock(ShippingTemplate::class);
        $template->method('getId')->willReturn('template1');

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($template)
        ;

        $this->areaRepository->expects($this->once())
            ->method('isLocationDeliverableByName')
            ->with($template, '广东省', '广州市', '荔湾区')
            ->willReturn(false)
        ;

        $result = $this->service->calculate($input);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('该地区不支持配送', $result->errorMessage);
        $this->assertFalse($result->isDeliverable);
    }

    public function testCalculateBasicScenario(): void
    {
        $items = [new ShippingCalculationItem('product1', 2, '1.500', '25.00')];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '广州市', 'district' => '荔湾区'];

        $template = $this->createMock(ShippingTemplate::class);
        $template->method('getId')->willReturn('template1');
        $template->method('getName')->willReturn('默认模板');
        $template->method('getChargeType')->willReturn(ChargeType::WEIGHT);
        $template->method('calculateBasicFee')->with('3.000')->willReturn('12.00');
        $template->method('getFreeShippingThreshold')->willReturn('99.00');

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($template)
        ;

        $this->areaRepository->expects($this->once())
            ->method('isLocationDeliverableByName')
            ->willReturn(true)
        ;

        $this->areaRepository->expects($this->once())
            ->method('findBestMatchForLocationByName')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('12.00', $result->fee);
        $this->assertSame('99.00', $result->freeShippingThreshold);
        $this->assertFalse($result->isFreeShipping);
        $this->assertTrue($result->isDeliverable);
        $this->assertCount(1, $result->details);
    }

    public function testCalculateWithFreeShipping(): void
    {
        $items = [new ShippingCalculationItem('product1', 1, '1.500', '100.00')];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '广州市', 'district' => '荔湾区'];

        $template = $this->createMock(ShippingTemplate::class);
        $template->method('getId')->willReturn('template1');
        $template->method('getName')->willReturn('默认模板');
        $template->method('getChargeType')->willReturn(ChargeType::WEIGHT);
        $template->method('calculateBasicFee')->with('1.500')->willReturn('12.00');
        $template->method('getFreeShippingThreshold')->willReturn('99.00');

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($template)
        ;

        $this->areaRepository->expects($this->once())
            ->method('isLocationDeliverableByName')
            ->willReturn(true)
        ;

        $this->areaRepository->expects($this->once())
            ->method('findBestMatchForLocationByName')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('0.00', $result->fee);
        $this->assertSame('99.00', $result->freeShippingThreshold);
        $this->assertTrue($result->isFreeShipping);
        $this->assertCount(1, $result->details);
    }

    public function testCalculateWithAreaSpecificRates(): void
    {
        $items = [new ShippingCalculationItem('product1', 1, '1.500', '25.00')];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '广州市', 'district' => '荔湾区'];

        $template = $this->createMock(ShippingTemplate::class);
        $template->method('getId')->willReturn('template1');
        $template->method('getName')->willReturn('默认模板');
        $template->method('getChargeType')->willReturn(ChargeType::WEIGHT);
        $template->method('getFreeShippingThreshold')->willReturn('99.00');

        $area = $this->createMock(ShippingTemplateArea::class);
        $area->method('hasCustomRates')->willReturn(true);
        $area->method('calculateFee')->with('1.500')->willReturn('8.00');
        $area->method('hasFreeShipping')->willReturn(false);
        $area->method('getAreaName')->willReturn('荔湾区');

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($template)
        ;

        $this->areaRepository->expects($this->once())
            ->method('isLocationDeliverableByName')
            ->willReturn(true)
        ;

        $this->areaRepository->expects($this->once())
            ->method('findBestMatchForLocationByName')
            ->willReturn($area)
        ;

        $result = $this->service->calculate($input);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('8.00', $result->fee);
        $this->assertSame('99.00', $result->freeShippingThreshold);
        $this->assertFalse($result->isFreeShipping);
        $this->assertCount(1, $result->details);
        $this->assertSame('荔湾区', $result->details[0]->areaName);
    }

    public function testCalculateWithQuantityChargeType(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 2, '1.500', '25.00'),
            new ShippingCalculationItem('product2', 3, '0.800', '30.00'),
        ];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '深圳市', 'district' => '南山区'];

        $template = $this->createMock(ShippingTemplate::class);
        $template->method('getId')->willReturn('template1');
        $template->method('getName')->willReturn('按件计费模板');
        $template->method('getChargeType')->willReturn(ChargeType::QUANTITY);
        $template->method('calculateBasicFee')->with('5')->willReturn('15.00');
        $template->method('getFreeShippingThreshold')->willReturn(null);

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($template)
        ;

        $this->areaRepository->expects($this->once())
            ->method('isLocationDeliverableByName')
            ->willReturn(true)
        ;

        $this->areaRepository->expects($this->once())
            ->method('findBestMatchForLocationByName')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('15.00', $result->fee);
        $this->assertNull($result->freeShippingThreshold);
        $this->assertCount(1, $result->details);
        $this->assertSame('5', $result->details[0]->unitValue);
    }

    public function testCalculateMultipleTemplates(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 1, '1.000', '20.00'),
            new ShippingCalculationItem('product2', 1, '1.000', '30.00', 'template2'),
        ];
        $input = new ShippingCalculationInput('address1', $items);

        $address = ['province' => '广东省', 'city' => '深圳市', 'district' => '南山区'];

        $defaultTemplate = $this->createMock(ShippingTemplate::class);
        $defaultTemplate->method('getId')->willReturn('template1');
        $defaultTemplate->method('getName')->willReturn('默认模板');
        $defaultTemplate->method('getChargeType')->willReturn(ChargeType::WEIGHT);
        $defaultTemplate->method('calculateBasicFee')->with('1.000')->willReturn('8.00');
        $defaultTemplate->method('getFreeShippingThreshold')->willReturn('99.00');

        $customTemplate = $this->createMock(ShippingTemplate::class);
        $customTemplate->method('getId')->willReturn('template2');
        $customTemplate->method('getName')->willReturn('特殊模板');
        $customTemplate->method('getChargeType')->willReturn(ChargeType::WEIGHT);
        $customTemplate->method('calculateBasicFee')->with('1.000')->willReturn('12.00');
        $customTemplate->method('getFreeShippingThreshold')->willReturn('199.00');

        $this->addressResolver->expects($this->once())
            ->method('resolveAddress')
            ->with('address1')
            ->willReturn($address)
        ;

        $this->templateRepository->expects($this->once())
            ->method('findDefault')
            ->willReturn($defaultTemplate)
        ;

        $this->templateRepository->expects($this->once())
            ->method('find')
            ->with('template2')
            ->willReturn($customTemplate)
        ;

        $this->areaRepository->expects($this->exactly(2))
            ->method('isLocationDeliverableByName')
            ->willReturn(true)
        ;

        $this->areaRepository->expects($this->exactly(2))
            ->method('findBestMatchForLocationByName')
            ->willReturn(null)
        ;

        $result = $this->service->calculate($input);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('20.00', $result->fee);
        $this->assertSame('99.00', $result->freeShippingThreshold);
        $this->assertCount(2, $result->details);
    }
}
