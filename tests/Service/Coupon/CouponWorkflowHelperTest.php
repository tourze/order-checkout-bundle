<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service\Coupon;

use Doctrine\Common\Collections\ArrayCollection;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Contract\CouponProviderInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponExtraItemBuilder;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponUsageLogger;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponWorkflowHelper;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;

/**
 * @internal
 *
 * CouponWorkflowHelper 服务的集成测试。
 * 使用真实的 CouponProviderChain 和匿名类实现的测试 Provider。
 */
#[CoversClass(CouponWorkflowHelper::class)]
#[RunTestsInSeparateProcesses]
final class CouponWorkflowHelperTest extends AbstractIntegrationTestCase
{
    private CouponWorkflowHelper $helper;
    private CouponProviderChain $providerChain;
    private MockObject $skuService;
    private CouponExtraItemBuilder $extraItemBuilder;

    /** @var array<string, bool> */
    private array $lockStates = [];

    /** @var array<string, bool> */
    private array $redeemResults = [];

    protected function onSetUp(): void
    {
        $this->skuService = $this->createMock(SkuServiceInterface::class);

        // 创建测试用的 Provider
        $testProvider = $this->createTestProvider();

        // 创建真实的 CouponProviderChain
        $this->providerChain = new CouponProviderChain(
            [$testProvider],
            new EventDispatcher(),
            new NullLogger()
        );

        // 设置容器服务
        self::getContainer()->set(SkuServiceInterface::class, $this->skuService);
        self::getContainer()->set(CouponProviderChain::class, $this->providerChain);
        // CouponUsageLogger 使用 null,因为它是可选的且只在特定测试中需要
        self::getContainer()->set(CouponUsageLogger::class, null);

        $this->extraItemBuilder = self::getService(CouponExtraItemBuilder::class);
        $this->helper = self::getService(CouponWorkflowHelper::class);
    }

    /**
     * 创建测试用的 CouponProvider 实现
     */
    private function createTestProvider(): CouponProviderInterface
    {
        return new class($this->lockStates, $this->redeemResults) implements CouponProviderInterface {
            public function __construct(
                private array &$lockStates,
                private array &$redeemResults,
            ) {
            }

            public function findByCode(string $code, UserInterface $user): ?CouponVO
            {
                return null;
            }

            public function lock(string $code, UserInterface $user): bool
            {
                return $this->lockStates[$code] ?? true;
            }

            public function unlock(string $code, UserInterface $user): bool
            {
                unset($this->lockStates[$code]);
                return true;
            }

            public function redeem(string $code, UserInterface $user, array $metadata = []): bool
            {
                return $this->redeemResults[$code] ?? true;
            }

            public function supports(string $code): bool
            {
                return true;
            }

            public function getIdentifier(): string
            {
                return 'test_provider';
            }
        };
    }

    /**
     * 配置锁定行为
     */
    private function configureLockBehavior(string $code, bool $result): void
    {
        $this->lockStates[$code] = $result;
    }

    /**
     * 配置核销行为
     */
    private function configureRedeemBehavior(string $code, bool $result): void
    {
        $this->redeemResults[$code] = $result;
    }

    public function testHelperCanBeCreated(): void
    {
        $this->assertInstanceOf(CouponWorkflowHelper::class, $this->helper);
    }

    public function testExtractCouponExtraItemsWithNoItems(): void
    {
        $priceResult = new PriceResult('100.00', '100.00');

        $extraItems = $this->helper->extractCouponExtraItems($priceResult);

        $this->assertIsArray($extraItems);
        $this->assertEmpty($extraItems);
    }

    public function testExtractCouponExtraItemsWithGiftItems(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');
        $sku->method('isValid')->willReturn(true);

        $this->skuService->method('findByIds')
            ->with(['123'])
            ->willReturn([$sku]);

        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_gift_items' => [
                ['sku_id' => 123, 'quantity' => 2],
            ],
        ]);

        $extraItems = $this->helper->extractCouponExtraItems($priceResult);

        $this->assertIsArray($extraItems);
        $this->assertCount(1, $extraItems);
        $this->assertEquals('coupon_gift', $extraItems[0]['type']);
        $this->assertEquals('0.00', $extraItems[0]['unit_price']);
        $this->assertEquals('0.00', $extraItems[0]['total_price']);
        $this->assertInstanceOf(CheckoutItem::class, $extraItems[0]['item']);
    }

    public function testExtractCouponExtraItemsWithRedeemItems(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('456');
        $sku->method('isValid')->willReturn(true);

        $this->skuService->method('findByIds')
            ->with(['456'])
            ->willReturn([$sku]);

        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_redeem_items' => [
                ['sku_id' => 456, 'quantity' => 1, 'unit_price' => '99.99'],
            ],
        ]);

        $extraItems = $this->helper->extractCouponExtraItems($priceResult);

        $this->assertIsArray($extraItems);
        $this->assertCount(1, $extraItems);
        $this->assertEquals('coupon_redeem', $extraItems[0]['type']);
        $this->assertEquals('0.00', $extraItems[0]['unit_price']);
        $this->assertEquals('0.00', $extraItems[0]['total_price']);
        $this->assertEquals('99.99', $extraItems[0]['reference_unit_price']);
        $this->assertInstanceOf(CheckoutItem::class, $extraItems[0]['item']);
    }

    public function testExtractCouponExtraItemsWithMixedItems(): void
    {
        $sku1 = $this->createMock(Sku::class);
        $sku1->method('getId')->willReturn('111');
        $sku1->method('isValid')->willReturn(true);

        $sku2 = $this->createMock(Sku::class);
        $sku2->method('getId')->willReturn('222');
        $sku2->method('isValid')->willReturn(true);

        $this->skuService->method('findByIds')
            ->with(['111', '222'])
            ->willReturn([$sku1, $sku2]);

        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_gift_items' => [
                ['sku_id' => 111, 'quantity' => 2],
            ],
            'coupon_redeem_items' => [
                ['sku_id' => 222, 'quantity' => 1, 'unit_price' => '50.00'],
            ],
        ]);

        $extraItems = $this->helper->extractCouponExtraItems($priceResult);

        $this->assertIsArray($extraItems);
        $this->assertCount(2, $extraItems);
        $this->assertEquals('coupon_gift', $extraItems[0]['type']);
        $this->assertEquals('coupon_redeem', $extraItems[1]['type']);
    }

    public function testExtractCouponExtraItemsThrowsExceptionWhenSkuNotFound(): void
    {
        $this->skuService->method('findByIds')
            ->with(['999'])
            ->willReturn([]);

        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_gift_items' => [
                ['sku_id' => 999, 'quantity' => 1],
            ],
        ]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('优惠券赠品 999 不存在或已下架');

        // Capture error_log output to prevent PHPUnit from treating it as test error
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $this->helper->extractCouponExtraItems($priceResult);
        } finally {
            ini_set('error_log', $originalErrorLog !== false ? $originalErrorLog : '');
        }
    }

    public function testMergeCheckoutItems(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('isValid')->willReturn(true);

        $baseItem1 = new CheckoutItem('sku-1', 1, true, $sku);
        $baseItem2 = new CheckoutItem('sku-2', 2, true, $sku);

        $extraItem = new CheckoutItem('sku-3', 1, true, $sku);

        $baseItems = [$baseItem1, $baseItem2];
        $extraItems = [
            ['item' => $extraItem, 'type' => 'coupon_gift', 'unit_price' => '0.00', 'total_price' => '0.00'],
        ];

        $mergedItems = $this->helper->mergeCheckoutItems($baseItems, $extraItems);

        $this->assertIsArray($mergedItems);
        $this->assertCount(3, $mergedItems);
        $this->assertSame($baseItem1, $mergedItems[0]);
        $this->assertSame($baseItem2, $mergedItems[1]);
        $this->assertSame($extraItem, $mergedItems[2]);
    }

    public function testExtractCouponCodesWithValidCodes(): void
    {
        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_applied_codes' => ['CODE1', 'CODE2', 'CODE3'],
        ]);

        $codes = $this->helper->extractCouponCodes($priceResult);

        $this->assertIsArray($codes);
        $this->assertCount(3, $codes);
        $this->assertEquals(['CODE1', 'CODE2', 'CODE3'], $codes);
    }

    public function testExtractCouponCodesWithDuplicates(): void
    {
        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_applied_codes' => ['CODE1', 'CODE2', 'CODE1', 'CODE2'],
        ]);

        $codes = $this->helper->extractCouponCodes($priceResult);

        $this->assertIsArray($codes);
        $this->assertCount(2, $codes);
        $this->assertEquals(['CODE1', 'CODE2'], $codes);
    }

    public function testExtractCouponCodesWithEmptyStrings(): void
    {
        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_applied_codes' => ['CODE1', '', 'CODE2', ''],
        ]);

        $codes = $this->helper->extractCouponCodes($priceResult);

        $this->assertIsArray($codes);
        $this->assertCount(2, $codes);
        $this->assertEquals(['CODE1', 'CODE2'], $codes);
    }

    public function testExtractCouponCodesWithNoCodes(): void
    {
        $priceResult = new PriceResult('100.00', '100.00');

        $codes = $this->helper->extractCouponCodes($priceResult);

        $this->assertIsArray($codes);
        $this->assertEmpty($codes);
    }

    public function testExtractCouponCodesWithInvalidData(): void
    {
        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_applied_codes' => 'not-an-array',
        ]);

        $codes = $this->helper->extractCouponCodes($priceResult);

        $this->assertIsArray($codes);
        $this->assertEmpty($codes);
    }

    public function testLockCouponCodesSuccess(): void
    {
        $user = $this->createMock(UserInterface::class);

        $codes = ['CODE1', 'CODE2'];
        $locked = $this->helper->lockCouponCodes($user, $codes);

        $this->assertIsArray($locked);
        $this->assertCount(2, $locked);
        $this->assertEquals(['CODE1', 'CODE2'], $locked);
        $this->assertEquals(['CODE1', 'CODE2'], $this->helper->getLockedCoupons());
    }

    public function testLockCouponCodesFailureRollsBack(): void
    {
        $user = $this->createMock(UserInterface::class);

        // 配置第二个优惠券锁定失败
        $this->configureLockBehavior('CODE2', false);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('优惠券已失效');

        $codes = ['CODE1', 'CODE2'];
        $this->helper->lockCouponCodes($user, $codes);
    }

    public function testUnlockCouponCodes(): void
    {
        $user = $this->createMock(UserInterface::class);

        // 首先锁定一些优惠券
        $this->helper->lockCouponCodes($user, ['CODE1', 'CODE2']);

        $this->helper->unlockCouponCodes(['CODE1', 'CODE2'], $user);

        $this->assertEmpty($this->helper->getLockedCoupons());
    }

    public function testRedeemCouponCodesSuccess(): void
    {
        $user = $this->createMock(UserInterface::class);
        $contract = $this->createMock(Contract::class);
        $contract->method('getUser')->willReturn($user);
        $contract->method('getId')->willReturn(123);
        $contract->method('getSn')->willReturn('ORDER-001');

        // 首先锁定一些优惠券
        $this->helper->lockCouponCodes($user, ['CODE1', 'CODE2']);

        $codes = ['CODE1', 'CODE2'];
        $this->helper->redeemCouponCodes($codes, $contract);

        $this->assertEmpty($this->helper->getLockedCoupons());
    }

    public function testRedeemCouponCodesThrowsExceptionWhenUserIsNull(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getUser')->willReturn(null);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('订单用户信息无效，无法核销优惠券');

        $this->helper->redeemCouponCodes(['CODE1'], $contract);
    }

    public function testRedeemCouponCodesThrowsExceptionWhenRedeemFails(): void
    {
        $user = $this->createMock(UserInterface::class);
        $contract = $this->createMock(Contract::class);
        $contract->method('getUser')->willReturn($user);
        $contract->method('getId')->willReturn(123);
        $contract->method('getSn')->willReturn('ORDER-001');

        // 配置核销失败
        $this->configureRedeemBehavior('CODE1', false);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('优惠券 CODE1 核销失败');

        $this->helper->redeemCouponCodes(['CODE1'], $contract);
    }

    public function testLogCouponUsageWithNoBreakdown(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->any())
            ->method('getUserIdentifier')
            ->willReturn('user@example.com');

        $context = new CalculationContext($user, []);

        $contract = $this->createMock(Contract::class);
        $priceResult = new PriceResult('100.00', '100.00');

        // 当 CouponUsageLogger 为 null 且没有 breakdown 时,logCouponUsage 应该提前返回
        $this->helper->logCouponUsage($context, $contract, $priceResult);

        // 测试通过意味着没有抛出异常
        $this->assertTrue(true);
    }

    public function testLogCouponUsageWithBreakdown(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->any())
            ->method('getUserIdentifier')
            ->willReturn('user@example.com');

        $context = new CalculationContext($user, []);

        $sku = $this->createMock(Sku::class);
        $sku->expects($this->any())
            ->method('getId')
            ->willReturn('sku-1');

        $orderProduct = $this->createMock(OrderProduct::class);
        $orderProduct->expects($this->any())
            ->method('getSku')
            ->willReturn($sku);
        $orderProduct->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        $contract = $this->createMock(Contract::class);
        $contract->expects($this->any())
            ->method('getId')
            ->willReturn(123);
        $contract->expects($this->any())
            ->method('getSn')
            ->willReturn('ORDER-001');
        $contract->expects($this->any())
            ->method('getProducts')
            ->willReturn(new ArrayCollection([$orderProduct]));

        $priceResult = new PriceResult('100.00', '100.00', '0.00', [
            'coupon_breakdown' => [
                'CODE1' => [
                    'discount' => '10.00',
                    'metadata' => ['coupon_type' => 'full_reduction'],
                    'allocations' => [
                        ['sku_id' => 'sku-1', 'amount' => '10.00'],
                    ],
                ],
            ],
        ]);

        // 由于 CouponUsageLogger 是 null,这个方法会提前返回
        $this->helper->logCouponUsage($context, $contract, $priceResult);

        // 测试通过意味着没有抛出异常
        $this->assertTrue(true);
    }

    public function testPrepareAllocationDetails(): void
    {
        $allocations = [
            ['sku_id' => 'sku-1', 'amount' => '5.00'],
            ['sku_id' => 'sku-2', 'amount' => '10.00'],
        ];

        $skuMap = [
            'sku-1' => 1,
            'sku-2' => 2,
        ];

        $result = $this->helper->prepareAllocationDetails($allocations, $skuMap);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('sku-1', $result[0]['sku_id']);
        $this->assertEquals('5.00', $result[0]['amount']);
        $this->assertEquals(1, $result[0]['order_product_id']);
        $this->assertEquals('sku-2', $result[1]['sku_id']);
        $this->assertEquals('10.00', $result[1]['amount']);
        $this->assertEquals(2, $result[1]['order_product_id']);
    }

    public function testPrepareAllocationDetailsWithInvalidData(): void
    {
        $result = $this->helper->prepareAllocationDetails('not-an-array', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testPrepareAllocationDetailsWithMissingSkuInMap(): void
    {
        $allocations = [
            ['sku_id' => 'sku-1', 'amount' => '5.00'],
            ['sku_id' => 'sku-2', 'amount' => '10.00'],
        ];

        $skuMap = [
            'sku-1' => 1,
        ];

        $result = $this->helper->prepareAllocationDetails($allocations, $skuMap);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('sku-1', $result[0]['sku_id']);
    }

    public function testDescribeExtraItem(): void
    {
        $this->assertEquals('优惠券赠品', $this->helper->describeExtraItem('coupon_gift'));
        $this->assertEquals('兑换券赠品', $this->helper->describeExtraItem('coupon_redeem'));
        $this->assertEquals('优惠券附加项', $this->helper->describeExtraItem('unknown'));
    }

    public function testBuildSkuOrderProductMap(): void
    {
        $sku1 = $this->createMock(Sku::class);
        $sku1->method('getId')->willReturn('sku-1');

        $sku2 = $this->createMock(Sku::class);
        $sku2->method('getId')->willReturn('sku-2');

        $orderProduct1 = $this->createMock(OrderProduct::class);
        $orderProduct1->method('getSku')->willReturn($sku1);
        $orderProduct1->method('getId')->willReturn(1);

        $orderProduct2 = $this->createMock(OrderProduct::class);
        $orderProduct2->method('getSku')->willReturn($sku2);
        $orderProduct2->method('getId')->willReturn(2);

        $contract = $this->createMock(Contract::class);
        $contract->method('getProducts')->willReturn(new ArrayCollection([$orderProduct1, $orderProduct2]));

        $map = $this->helper->buildSkuOrderProductMap($contract);

        $this->assertIsArray($map);
        $this->assertCount(2, $map);
        $this->assertEquals(1, $map['sku-1']);
        $this->assertEquals(2, $map['sku-2']);
    }

    public function testBuildSkuOrderProductMapWithNullSku(): void
    {
        $orderProduct = $this->createMock(OrderProduct::class);
        $orderProduct->method('getSku')->willReturn(null);

        $contract = $this->createMock(Contract::class);
        $contract->method('getProducts')->willReturn(new ArrayCollection([$orderProduct]));

        $map = $this->helper->buildSkuOrderProductMap($contract);

        $this->assertIsArray($map);
        $this->assertEmpty($map);
    }

    public function testResolveOrderProductSkuId(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('sku-123');

        $orderProduct = $this->createMock(OrderProduct::class);
        $orderProduct->method('getSku')->willReturn($sku);

        $skuId = $this->helper->resolveOrderProductSkuId($orderProduct);

        $this->assertEquals('sku-123', $skuId);
    }

    public function testResolveOrderProductSkuIdWithNullSku(): void
    {
        $orderProduct = $this->createMock(OrderProduct::class);
        $orderProduct->method('getSku')->willReturn(null);

        $skuId = $this->helper->resolveOrderProductSkuId($orderProduct);

        $this->assertEquals('', $skuId);
    }

    public function testNormalizePriceWithStringNumeric(): void
    {
        $this->assertEquals('123.45', $this->helper->normalizePrice('123.45'));
        $this->assertEquals('100.00', $this->helper->normalizePrice('100'));
        $this->assertEquals('0.99', $this->helper->normalizePrice('0.99'));
    }

    public function testNormalizePriceWithNumeric(): void
    {
        $this->assertEquals('123.45', $this->helper->normalizePrice(123.45));
        $this->assertEquals('100.00', $this->helper->normalizePrice(100));
        $this->assertEquals('0.99', $this->helper->normalizePrice(0.99));
    }

    public function testNormalizePriceWithInvalidData(): void
    {
        $this->assertEquals('0.00', $this->helper->normalizePrice('invalid'));
        $this->assertEquals('0.00', $this->helper->normalizePrice(null));
        $this->assertEquals('0.00', $this->helper->normalizePrice([]));
    }

    public function testGetLockedCoupons(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->helper->lockCouponCodes($user, ['CODE1', 'CODE2']);

        $locked = $this->helper->getLockedCoupons();

        $this->assertIsArray($locked);
        $this->assertCount(2, $locked);
        $this->assertEquals(['CODE1', 'CODE2'], $locked);
    }
}
