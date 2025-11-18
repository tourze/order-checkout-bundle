<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Service\ContractService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Entity\Code;
use Tourze\CouponCoreBundle\Entity\Coupon;
use Tourze\CouponCoreBundle\Enum\CouponType;
use Tourze\CouponCoreBundle\Service\CouponService;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponUsageLogger;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\DeliveryAddressBundle\Service\DeliveryAddressService;
use Tourze\OrderCartBundle\Interface\CartManagerInterface;
use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\OrderCheckoutBundle\Service\ContentFilterService;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponWorkflowHelper;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;
use Tourze\StockManageBundle\Service\StockOperator;

/**
 * @internal
 */
#[CoversClass(CheckoutService::class)]
final class CheckoutServiceCouponTest extends TestCase
{
    public function testProcessLocksRedeemsAndLogsCoupon(): void
    {
        $priceCalculationService = $this->createMock(PriceCalculationService::class);
        $stockValidator = $this->createMock(StockValidatorInterface::class);
        $shippingCalculator = $this->createMock(ShippingCalculatorInterface::class);
        $contentFilter = $this->createMock(ContentFilterService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $contractService = $this->createMock(ContractService::class);
        $cartManager = $this->createMock(CartManagerInterface::class);
        $stockOperator = $this->createMock(StockOperator::class);
        $skuService = $this->createMock(SkuServiceInterface::class);
        $deliveryService = $this->createMock(DeliveryAddressService::class);
        $couponProviderChain = $this->createMock(CouponProviderChain::class);
        $usageLogger = $this->createMock(CouponUsageLogger::class);
        $logger = $this->createMock(LoggerInterface::class);

        $couponHelper = new CouponWorkflowHelper($couponProviderChain, $skuService, $usageLogger, $logger);

        $service = new CheckoutService(
            $priceCalculationService,
            $stockValidator,
            $shippingCalculator,
            $contentFilter,
            $entityManager,
            $contractService,
            $cartManager,
            $stockOperator,
            $deliveryService,
            $couponHelper,
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user-1');

        $spu = $this->createConfiguredMock(Spu::class, ['getId' => 1, 'getTitle' => 'SPU', 'isValid' => true]);
        $sku = $this->createConfiguredMock(
            Sku::class,
            [
                'getId' => 'SKU1',
                'getSpu' => $spu,
                'getFullName' => '商品',
                'getDisplayAttribute' => '属性',
                'getMainThumb' => '',
            ]
        );

        $checkoutItem = new CheckoutItem('SKU1', 1, true, $sku);
        $context = new CalculationContext($user, [$checkoutItem], ['CODE123'], ['addressId' => 'ADDR1']);

        $priceResult = new PriceResult(
            '100.00',
            '90.00',
            '10.00',
            [
                'base_price' => [
                    [
                        'sku_id' => 'SKU1',
                        'total_price' => '100.00',
                        'unit_price' => '100.00',
                        'quantity' => 1,
                    ],
                ],
                'coupon_discount' => 10.0,
                'coupon_allocations' => [
                    ['sku_id' => 'SKU1', 'amount' => '10.00'],
                ],
                'coupon_breakdown' => [
                    'CODE123' => [
                        'discount' => '10.00',
                        'allocations' => [
                            ['sku_id' => 'SKU1', 'amount' => '10.00'],
                        ],
                        'metadata' => [
                            'coupon_type' => 'full_reduction',
                            'allocation_rule' => 'proportional',
                        ],
                    ],
                ],
                'coupon_applied_codes' => ['CODE123'],
            ]
        );

        $priceCalculationService->method('calculate')->willReturn($priceResult);
        $stockValidator->method('validate')->willReturn(StockValidationResult::success());
        $shippingCalculator->method('calculate')->willReturn(ShippingResult::paid(0.0));

        $contractService->expects(self::once())->method('createOrder');
        $stockOperator->expects(self::once())->method('lockStock');

        $deliveryAddress = new DeliveryAddress();
        $deliveryAddress->setConsignee('张三');
        $deliveryAddress->setMobile('13800000000');
        $deliveryAddress->setProvince('省');
        $deliveryAddress->setCity('市');
        $deliveryAddress->setDistrict('区');
        $deliveryAddress->setAddressLine('详细地址');
        $deliveryService->method('getAddressByIdAndUser')->willReturn($deliveryAddress);

        $coupon = new Coupon();
        $coupon->setType(CouponType::FULL_REDUCTION);
        $code = new Code();
        $code->setCoupon($coupon);
        $code->setSn('CODE123');

        $couponProviderChain->expects(self::once())
            ->method('lock')
            ->with('CODE123', $user)
            ->willReturn(true);

        $couponProviderChain->expects(self::once())
            ->method('redeem')
            ->with('CODE123', $user, self::isType('array'))
            ->willReturn(true);

        $couponProviderChain->expects(self::never())->method('unlock');

        $usageLogger->expects(self::once())
            ->method('logUsage')
            ->with('CODE123', 'full_reduction', 'user-1', self::anything(), self::anything(), '10.00', self::anything(), self::anything());

        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use ($user): void {
            if ($entity instanceof Contract) {
                $reflection = new \ReflectionProperty($entity, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($entity, 1);

                // 设置 user 属性以避免 redeemCouponCodes 中的 null 检查失败
                $userReflection = new \ReflectionProperty($entity, 'user');
                $userReflection->setAccessible(true);
                $userReflection->setValue($entity, $user);
            }
        });

        $result = $service->process($context);

        self::assertSame(['CODE123'], $result->getAppliedCoupons());
    }
}
