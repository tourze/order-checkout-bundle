<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Service\ContractService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\DeliveryAddressBundle\Service\DeliveryAddressService;
use Tourze\OrderCartBundle\Interface\CartManagerInterface;
use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\OrderCheckoutBundle\Service\ContentFilterService;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;
use Tourze\StockManageBundle\Service\StockOperator;

/**
 * @internal
 */
#[CoversClass(CheckoutService::class)]
final class CheckoutServiceTest extends TestCase
{
    private CheckoutService $checkoutService;

    private PriceCalculationService&MockObject $priceCalculationService;

    private StockValidatorInterface&MockObject $stockValidator;

    private ShippingCalculatorInterface&MockObject $shippingCalculator;

    private ContentFilterService&MockObject $contentFilterService;

    private EntityManagerInterface&MockObject $entityManager;

    private ContractService&MockObject $contractService;

    private CartManagerInterface&MockObject $cartManager;

    private StockOperator&MockObject $stockOperator;

    private DeliveryAddressService&MockObject $deliveryAddressService;

    private UserInterface&MockObject $user;

    protected function setUp(): void
    {
        $this->priceCalculationService = $this->createMock(PriceCalculationService::class);
        $this->stockValidator = $this->createMock(StockValidatorInterface::class);
        $this->shippingCalculator = $this->createMock(ShippingCalculatorInterface::class);
        $this->contentFilterService = $this->createMock(ContentFilterService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->contractService = $this->createMock(ContractService::class);
        $this->cartManager = $this->createMock(CartManagerInterface::class);
        $this->stockOperator = $this->createMock(StockOperator::class);
        $this->deliveryAddressService = $this->createMock(DeliveryAddressService::class);
        $this->user = $this->createMock(UserInterface::class);

        $this->checkoutService = new CheckoutService(
            $this->priceCalculationService,
            $this->stockValidator,
            $this->shippingCalculator,
            $this->contentFilterService,
            $this->entityManager,
            $this->contractService,
            $this->cartManager,
            $this->stockOperator,
            $this->deliveryAddressService
        );
    }

    public function testCheckoutServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CheckoutService::class, $this->checkoutService);
    }

    public function testCalculateCheckoutWithEmptyCart(): void
    {
        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('购物车为空，无法结算');

        $this->checkoutService->calculateCheckout($this->user, []);
    }

    public function testCalculateCheckoutWithStockValidationFailure(): void
    {
        // 使用数组格式传递购物车项目，因为 calculateCheckout 期望 array<mixed>
        $checkoutItems = [['skuId' => 1, 'quantity' => 1]];

        $stockValidation = StockValidationResult::failure(['error' => '库存不足'], [], ['details' => 'test']);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('库存验证失败: 库存不足');

        $this->checkoutService->calculateCheckout($this->user, $checkoutItems);
    }

    public function testCalculateCheckoutSuccess(): void
    {
        // 使用数组格式传递购物车项目，因为 calculateCheckout 期望 array<mixed>
        $checkoutItems = [['skuId' => 1, 'quantity' => 1]];

        $stockValidation = StockValidationResult::success([]);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $priceResult = $this->createMock(PriceResult::class);
        $this->priceCalculationService->method('calculate')
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        $result = $this->checkoutService->calculateCheckout($this->user, $checkoutItems);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        // 验证结果包含转换后的 CheckoutItem 对象
        $resultItems = $result->getItems();
        $this->assertCount(1, $resultItems);
        $this->assertInstanceOf(CheckoutItem::class, $resultItems[0]);
        $this->assertSame(1, $resultItems[0]->getSkuId());
        $this->assertSame(1, $resultItems[0]->getQuantity());
        $this->assertSame($priceResult, $result->getPriceResult());
        $this->assertSame($shippingResult, $result->getShippingResult());
        $this->assertSame($stockValidation, $result->getStockValidation());
    }

    public function testQuickCalculateWithEmptyCart(): void
    {
        $result = $this->checkoutService->quickCalculate($this->user, []);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertTrue(0.0 === $result->getFinalTotal());
    }

    public function testQuickCalculateSuccess(): void
    {
        // 使用数组格式传递购物车项目，因为 quickCalculate 期望 array<mixed>
        $checkoutItems = [['skuId' => 1, 'quantity' => 1]];

        $priceResult = $this->createMock(PriceResult::class);
        $this->priceCalculationService->method('calculate')
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        $result = $this->checkoutService->quickCalculate($this->user, $checkoutItems);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        // 验证结果包含转换后的 CheckoutItem 对象
        $resultItems = $result->getItems();
        $this->assertCount(1, $resultItems);
        $this->assertInstanceOf(CheckoutItem::class, $resultItems[0]);
        $this->assertSame(1, $resultItems[0]->getSkuId());
        $this->assertSame(1, $resultItems[0]->getQuantity());
        $this->assertSame($priceResult, $result->getPriceResult());
        $this->assertSame($shippingResult, $result->getShippingResult());
        $this->assertNull($result->getStockValidation());
    }

    public function testProcess(): void
    {
        // 使用数组格式的购物车项目，而不是对象mock
        $checkoutItems = [CheckoutItem::fromArray([
            'skuId' => 'TEST_SKU_001',
            'quantity' => 2,
            'selected' => true,
        ])];
        $user = $this->createMock(UserInterface::class);
        $appliedCoupons = ['COUPON1'];
        $context = new CalculationContext($user, $checkoutItems, $appliedCoupons, ['addressId' => 123]);

        $stockValidation = StockValidationResult::success([]);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('getFinalPrice')->willReturn('20.00');
        $this->priceCalculationService->method('calculate')
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $shippingResult->method('getShippingFee')->willReturn(5.00);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        // Mock EntityManager expectations
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
        ;
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush')
        ;

        // Mock ContractService
        $this->contractService->expects($this->once())
            ->method('createOrder')
        ;

        // Mock DeliveryAddressService - 返回有效地址
        $mockAddress = $this->createMock(DeliveryAddress::class);
        $mockAddress->method('getConsignee')->willReturn('张三');
        $mockAddress->method('getMobile')->willReturn('13800138000');
        $mockAddress->method('getProvince')->willReturn('广东省');
        $mockAddress->method('getCity')->willReturn('广州市');
        $mockAddress->method('getDistrict')->willReturn('天河区');
        $mockAddress->method('getAddressLine')->willReturn('天河路123号');

        $this->deliveryAddressService->method('getAddressByIdAndUser')
            ->willReturn($mockAddress)
        ;

        $result = $this->checkoutService->process($context);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertNotEmpty($result->getItems());
    }

    public function testCalculateCheckoutWithCoupons(): void
    {
        $checkoutItems = [new CheckoutItem(1, 1)];
        $appliedCoupons = ['SAVE10', 'FREESHIP'];

        $stockValidation = StockValidationResult::success([]);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $priceResult = $this->createMock(PriceResult::class);
        $this->priceCalculationService->expects($this->once())
            ->method('calculate')
            ->with(self::callback(function (CalculationContext $context) use ($appliedCoupons) {
                return $context->getAppliedCoupons() === $appliedCoupons;
            }))
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        $result = $this->checkoutService->calculateCheckout($this->user, $checkoutItems, $appliedCoupons);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame($appliedCoupons, $result->getAppliedCoupons());
    }

    public function testCalculateCheckoutWithOptions(): void
    {
        $checkoutItems = [new CheckoutItem(1, 1)];
        $options = ['region' => 'beijing', 'urgency' => 'high'];

        $stockValidation = StockValidationResult::success([]);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $priceResult = $this->createMock(PriceResult::class);
        $this->priceCalculationService->expects($this->once())
            ->method('calculate')
            ->with(self::callback(function (CalculationContext $context) use ($options) {
                $metadata = $context->getMetadata();

                return $metadata['region'] === $options['region']
                    && $metadata['urgency'] === $options['urgency']
                    && $metadata['calculate_time'] instanceof \DateTimeImmutable;
            }))
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $this->shippingCalculator->expects($this->once())
            ->method('calculate')
            ->with(self::callback(function (ShippingContext $context) use ($options) {
                return $context->getRegion() === $options['region'];
            }))
            ->willReturn($shippingResult)
        ;

        $result = $this->checkoutService->calculateCheckout($this->user, $checkoutItems, [], $options);

        $this->assertInstanceOf(CheckoutResult::class, $result);
    }

    public function testQuickCalculateWithCouponsAndOptions(): void
    {
        $checkoutItems = [new CheckoutItem(1, 1)];
        $appliedCoupons = ['QUICK10'];
        $options = ['region' => 'shanghai'];

        $priceResult = $this->createMock(PriceResult::class);
        $this->priceCalculationService->method('calculate')
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        $result = $this->checkoutService->quickCalculate($this->user, $checkoutItems, $appliedCoupons, $options);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame($appliedCoupons, $result->getAppliedCoupons());
        $this->assertNull($result->getStockValidation()); // quickCalculate不验证库存
    }

    public function testProcessWithStockValidationFailure(): void
    {
        // 使用数组格式的购物车项目，而不是对象mock
        $checkoutItems = [CheckoutItem::fromArray([
            'skuId' => 'TEST_SKU_002',
            'quantity' => 1,
            'selected' => true,
        ])];
        $user = $this->createMock(UserInterface::class);
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $stockValidation = StockValidationResult::failure(['sku-1' => '库存不足'], [], []);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('库存验证失败: 库存不足');

        $this->checkoutService->process($context);
    }

    public function testProcessWithAddressIdInMetadata(): void
    {
        // 使用数组格式的购物车项目，而不是对象mock
        $checkoutItems = [CheckoutItem::fromArray([
            'skuId' => 'TEST_SKU_003',
            'quantity' => 1,
            'selected' => true,
        ])];
        $user = $this->createMock(UserInterface::class);
        $context = new CalculationContext($user, $checkoutItems, [], ['addressId' => 123, 'region' => 'guangzhou']);

        $stockValidation = StockValidationResult::success([]);
        $this->stockValidator->method('validate')
            ->willReturn($stockValidation)
        ;

        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('getFinalPrice')->willReturn('15.00');
        $this->priceCalculationService->method('calculate')
            ->willReturn($priceResult)
        ;

        $shippingResult = $this->createMock(ShippingResult::class);
        $shippingResult->method('getShippingFee')->willReturn(3.00);
        $this->shippingCalculator->method('calculate')
            ->willReturn($shippingResult)
        ;

        // Mock EntityManager expectations
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
        ;
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush')
        ;

        // Mock ContractService
        $this->contractService->expects($this->once())
            ->method('createOrder')
        ;

        // Mock DeliveryAddressService - 返回有效地址
        $mockAddress = $this->createMock(DeliveryAddress::class);
        $mockAddress->method('getConsignee')->willReturn('李四');
        $mockAddress->method('getMobile')->willReturn('13900139000');
        $mockAddress->method('getProvince')->willReturn('广东省');
        $mockAddress->method('getCity')->willReturn('深圳市');
        $mockAddress->method('getDistrict')->willReturn('南山区');
        $mockAddress->method('getAddressLine')->willReturn('科技园456号');

        $this->deliveryAddressService->method('getAddressByIdAndUser')
            ->willReturn($mockAddress)
        ;

        $result = $this->checkoutService->process($context);

        $this->assertInstanceOf(CheckoutResult::class, $result);
    }
}
