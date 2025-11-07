<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Entity\Code;
use Tourze\CouponCoreBundle\Entity\Coupon;
use Tourze\CouponCoreBundle\Enum\CouponType;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\Service\CouponService;
use Tourze\CouponCoreBundle\Service\CouponVOFactory;
use Tourze\CouponCoreBundle\ValueObject\CouponApplicationResult;
use Tourze\CouponCoreBundle\ValueObject\CouponOrderItem;
use Tourze\CouponCoreBundle\ValueObject\CouponScopeVO;
use Tourze\CouponCoreBundle\ValueObject\CouponConditionVO;
use Tourze\CouponCoreBundle\ValueObject\CouponBenefitVO;
use Tourze\CouponCoreBundle\ValueObject\FullReductionCouponVO;
use Tourze\OrderCheckoutBundle\Calculator\CouponCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
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
        $couponService = $this->createMock(CouponService::class);
        $factory = $this->createMock(CouponVOFactory::class);
        $evaluator = $this->createMock(CouponEvaluator::class);
        $skuLoader = $this->createMock(SkuLoaderInterface::class);

        $calculator = new CouponCalculator($couponService, $factory, $evaluator, $skuLoader);

        $sku = $this->createConfiguredMock(Sku::class, ['getId' => 'SKU1', 'getSpu' => null, 'getFullName' => '商品']);
        $skuLoader->method('loadSkuByIdentifier')->willReturn($sku);

        $coupon = new Coupon();
        $coupon->setType(CouponType::FULL_REDUCTION);
        $coupon->setConfiguration([
            'condition' => ['threshold_amount' => '50.00'],
            'benefit' => ['discount_amount' => '10.00'],
        ]);

        $code = new Code();
        $code->setCoupon($coupon);
        $code->setSn('CODE1');
        $couponService->method('getCodeDetail')->willReturn($code);

        $factory->method('createFromCouponCode')->willReturn(
            new FullReductionCouponVO(
                'CODE1',
                CouponType::FULL_REDUCTION,
                '满减',
                null,
                null,
                new CouponScopeVO(),
                new CouponConditionVO(thresholdAmount: '50.00'),
                new CouponBenefitVO(discountAmount: '10.00')
            )
        );

        $evaluator->method('evaluate')->willReturn(
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

        $user = $this->createMock(UserInterface::class);
        $checkoutItem = new CheckoutItem('SKU1', 1, true, $sku);
        $context = new CalculationContext($user, [$checkoutItem], ['CODE1']);

        $result = $calculator->calculate($context);

        self::assertSame('-10.00', $result->getFinalPrice());
        self::assertSame(10.0, $result->getDetail('coupon_discount'));
    }
}
