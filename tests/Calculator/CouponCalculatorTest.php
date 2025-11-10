<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Enum\CouponScopeType;
use Tourze\CouponCoreBundle\Enum\CouponType;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\ValueObject\CouponApplicationResult;
use Tourze\CouponCoreBundle\ValueObject\CouponScopeVO;
use Tourze\CouponCoreBundle\ValueObject\CouponConditionVO;
use Tourze\CouponCoreBundle\ValueObject\CouponBenefitVO;
use Tourze\CouponCoreBundle\ValueObject\FullReductionCouponVO;
use Tourze\OrderCheckoutBundle\Calculator\CouponCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * @internal
 */
#[CoversClass(CouponCalculator::class)]
final class CouponCalculatorTest extends TestCase
{
    public function testCalculateAggregatesDiscount(): void
    {
        $providerChain = $this->createMock(CouponProviderChain::class);
        $couponEvaluator = $this->createMock(CouponEvaluator::class);
        $skuLoader = $this->createMock(SkuLoaderInterface::class);

        $calculator = new CouponCalculator($providerChain, $couponEvaluator, $skuLoader);

        $sku = $this->createConfiguredMock(Sku::class, [
            'getId' => 'SKU1',
            'getSpu' => null,
            'getFullName' => '商品',
            'getMarketPrice' => '100.00',
            'getGtin' => null,
        ]);
        $skuLoader->method('loadSkuByIdentifier')->willReturn($sku);

        $couponVO = new FullReductionCouponVO(
            'CODE1',
            CouponType::FULL_REDUCTION,
            '满减',
            null,
            null,
            new CouponScopeVO(CouponScopeType::ALL),
            new CouponConditionVO(thresholdAmount: '50.00'),
            new CouponBenefitVO(discountAmount: '10.00')
        );

        $user = $this->createMock(UserInterface::class);
        $providerChain->method('findByCode')
            ->with('CODE1', $user)
            ->willReturn($couponVO);

        $couponEvaluator->method('evaluate')
            ->willReturn(
                new CouponApplicationResult(
                    'CODE1',
                    '10.00',
                    [['sku_id' => 'SKU1', 'amount' => '10.00']],
                    [],
                    [],
                    false,
                    [],
                    ['allocation_rule' => 'proportional']
                )
            );

        $checkoutItem = new CheckoutItem('SKU1', 1, true, $sku);
        $context = new CalculationContext($user, [$checkoutItem], ['CODE1']);

        $result = $calculator->calculate($context);

        self::assertSame('-10.00', $result->getFinalPrice());
        self::assertSame(10.0, $result->getDetail('coupon_discount'));
    }

    public function testSupports(): void
    {
        $providerChain = $this->createMock(CouponProviderChain::class);
        $couponEvaluator = $this->createMock(CouponEvaluator::class);
        $skuLoader = $this->createMock(SkuLoaderInterface::class);

        $calculator = new CouponCalculator($providerChain, $couponEvaluator, $skuLoader);

        $user = $this->createMock(UserInterface::class);
        $contextWithCoupons = new CalculationContext($user, [], ['CODE1']);
        $contextWithoutCoupons = new CalculationContext($user, [], []);

        self::assertTrue($calculator->supports($contextWithCoupons));
        self::assertFalse($calculator->supports($contextWithoutCoupons));
    }
}
