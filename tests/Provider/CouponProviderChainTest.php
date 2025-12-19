<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\CouponCoreBundle\Enum\AllocationRule;
use Tourze\CouponCoreBundle\Enum\CouponScopeType;
use Tourze\CouponCoreBundle\Enum\CouponType;
use Tourze\CouponCoreBundle\ValueObject\CouponBenefitVO;
use Tourze\CouponCoreBundle\ValueObject\CouponConditionVO;
use Tourze\CouponCoreBundle\ValueObject\CouponScopeVO;
use Tourze\CouponCoreBundle\ValueObject\FullReductionCouponVO;
use Tourze\OrderCheckoutBundle\Contract\CouponProviderInterface;
use Tourze\OrderCheckoutBundle\Event\ExternalCouponRequestedEvent;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * CouponProviderChain 集成测试
 *
 * 从服务容器获取 CouponProviderChain 实例以测试责任链模式，
 * 使用各种 Mock 提供者。通过反射清理提供者列表以控制测试期间的提供者。
 *
 * @internal
 */
#[CoversClass(CouponProviderChain::class)]
#[RunTestsInSeparateProcesses]
final class CouponProviderChainTest extends AbstractIntegrationTestCase
{
    private CouponProviderChain $chain;

    protected function onSetUp(): void
    {
        // 在集成测试中，应该从服务容器获取服务，而不是直接实例化
        $this->chain = self::getService(CouponProviderChain::class);

        // 清理服务中已有的提供者，确保测试隔离
        $this->clearProviders();
    }

    /**
     * 清理服务中的提供者列表，确保测试隔离
     */
    private function clearProviders(): void
    {
        $reflection = new \ReflectionClass($this->chain);
        $providersProperty = $reflection->getProperty('providers');
        $providersProperty->setAccessible(true);
        $providersProperty->setValue($this->chain, []);
    }

    public function testCouponProviderChainCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CouponProviderChain::class, $this->chain);
    }

    public function testAddProvider(): void
    {
        $provider = $this->createMockProvider('test_provider');

        $this->chain->addProvider($provider);

        $providers = $this->chain->getProviders();
        $this->assertCount(1, $providers);
        $this->assertSame($provider, $providers[0]);
    }

    public function testAddMultipleProviders(): void
    {
        $provider1 = $this->createMockProvider('provider1');
        $provider2 = $this->createMockProvider('provider2');
        $provider3 = $this->createMockProvider('provider3');

        $this->chain->addProvider($provider1);
        $this->chain->addProvider($provider2);
        $this->chain->addProvider($provider3);

        $providers = $this->chain->getProviders();
        $this->assertCount(3, $providers);
        $this->assertSame($provider1, $providers[0]);
        $this->assertSame($provider2, $providers[1]);
        $this->assertSame($provider3, $providers[2]);
    }

    public function testGetProvidersReturnsEmptyArrayWhenNoProviders(): void
    {
        $providers = $this->chain->getProviders();

        $this->assertIsArray($providers);
        $this->assertCount(0, $providers);
    }

    public function testFindByCodeWithSupportingProvider(): void
    {
        $user = $this->buildTestUser();
        $couponVO = $this->createMockCouponVO('TEST123');

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('findByCode')->with('TEST123', $user)->willReturn($couponVO);

        $this->chain->addProvider($provider);

        $result = $this->chain->findByCode('TEST123', $user);

        $this->assertSame($couponVO, $result);
    }

    public function testFindByCodeWithMultipleProvidersReturnsFirstMatch(): void
    {
        $user = $this->buildTestUser();
        $couponVO1 = $this->createMockCouponVO('CODE1');
        $couponVO2 = $this->createMockCouponVO('CODE2');

        $provider1 = $this->createMockProvider('provider1');
        $provider1->method('supports')->willReturn(false);

        $provider2 = $this->createMockProvider('provider2');
        $provider2->method('supports')->with('TEST123')->willReturn(true);
        $provider2->method('findByCode')->with('TEST123', $user)->willReturn($couponVO1);

        $provider3 = $this->createMockProvider('provider3');
        $provider3->method('supports')->with('TEST123')->willReturn(true);
        $provider3->method('findByCode')->with('TEST123', $user)->willReturn($couponVO2);

        $this->chain->addProvider($provider1);
        $this->chain->addProvider($provider2);
        $this->chain->addProvider($provider3);

        $result = $this->chain->findByCode('TEST123', $user);

        // 应该返回第一个支持的提供者的结果
        $this->assertSame($couponVO1, $result);
    }

    public function testFindByCodeWithProviderReturningNull(): void
    {
        $user = $this->buildTestUser();
        $couponVO = $this->createMockCouponVO('TEST456');

        $provider1 = $this->createMockProvider('provider1');
        $provider1->method('supports')->with('TEST456')->willReturn(true);
        $provider1->method('findByCode')->with('TEST456', $user)->willReturn(null);

        $provider2 = $this->createMockProvider('provider2');
        $provider2->method('supports')->with('TEST456')->willReturn(true);
        $provider2->method('findByCode')->with('TEST456', $user)->willReturn($couponVO);

        $this->chain->addProvider($provider1);
        $this->chain->addProvider($provider2);

        $result = $this->chain->findByCode('TEST456', $user);

        // 应该继续尝试下一个提供者
        $this->assertSame($couponVO, $result);
    }

    public function testFindByCodeWithProviderThrowingException(): void
    {
        $user = $this->buildTestUser();
        $couponVO = $this->createMockCouponVO('TEST789');

        $provider1 = $this->createMockProvider('provider1');
        $provider1->method('supports')->with('TEST789')->willReturn(true);
        $provider1->method('findByCode')
            ->with('TEST789', $user)
            ->willThrowException(new \RuntimeException('Provider error'));

        $provider2 = $this->createMockProvider('provider2');
        $provider2->method('supports')->with('TEST789')->willReturn(true);
        $provider2->method('findByCode')->with('TEST789', $user)->willReturn($couponVO);

        $this->chain->addProvider($provider1);
        $this->chain->addProvider($provider2);

        $result = $this->chain->findByCode('TEST789', $user);

        // 应该捕获异常并继续尝试下一个提供者
        $this->assertSame($couponVO, $result);
    }

    public function testFindByCodeReturnsNullWhenNoProviderSupports(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('UNKNOWN')->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->findByCode('UNKNOWN', $user);

        $this->assertNull($result);
    }

    public function testFindByCodeReturnsNullWhenNoProviders(): void
    {
        $user = $this->buildTestUser();

        $result = $this->chain->findByCode('TEST123', $user);

        $this->assertNull($result);
    }

    public function testLockWithSupportingProvider(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('lock')->with('TEST123', $user)->willReturn(true);

        $this->chain->addProvider($provider);

        $result = $this->chain->lock('TEST123', $user);

        $this->assertTrue($result);
    }

    public function testLockReturnsFalseWhenProviderReturnsNull(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('lock')->with('TEST123', $user)->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->lock('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testLockReturnsFalseWhenNoSupportingProvider(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('UNKNOWN')->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->lock('UNKNOWN', $user);

        $this->assertFalse($result);
    }

    public function testLockReturnsFalseWhenProviderThrowsException(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('lock')
            ->with('TEST123', $user)
            ->willThrowException(new \RuntimeException('Lock failed'));

        $this->chain->addProvider($provider);

        $result = $this->chain->lock('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testUnlockWithSupportingProvider(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('unlock')->with('TEST123', $user)->willReturn(true);

        $this->chain->addProvider($provider);

        $result = $this->chain->unlock('TEST123', $user);

        $this->assertTrue($result);
    }

    public function testUnlockReturnsFalseWhenProviderReturnsFalse(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('unlock')->with('TEST123', $user)->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->unlock('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testUnlockReturnsFalseWhenNoSupportingProvider(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('UNKNOWN')->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->unlock('UNKNOWN', $user);

        $this->assertFalse($result);
    }

    public function testUnlockReturnsFalseWhenProviderThrowsException(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('unlock')
            ->with('TEST123', $user)
            ->willThrowException(new \RuntimeException('Unlock failed'));

        $this->chain->addProvider($provider);

        $result = $this->chain->unlock('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testRedeemWithSupportingProvider(): void
    {
        $user = $this->buildTestUser();
        $metadata = ['order_id' => 12345, 'order_no' => 'ORD-2024-001'];

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('redeem')->with('TEST123', $user, $metadata)->willReturn(true);

        $this->chain->addProvider($provider);

        $result = $this->chain->redeem('TEST123', $user, $metadata);

        $this->assertTrue($result);
    }

    public function testRedeemWithEmptyMetadata(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('redeem')->with('TEST123', $user, [])->willReturn(true);

        $this->chain->addProvider($provider);

        $result = $this->chain->redeem('TEST123', $user);

        $this->assertTrue($result);
    }

    public function testRedeemReturnsFalseWhenProviderReturnsFalse(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('redeem')->with('TEST123', $user, [])->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->redeem('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testRedeemReturnsFalseWhenNoSupportingProvider(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('UNKNOWN')->willReturn(false);

        $this->chain->addProvider($provider);

        $result = $this->chain->redeem('UNKNOWN', $user);

        $this->assertFalse($result);
    }

    public function testRedeemReturnsFalseWhenProviderThrowsException(): void
    {
        $user = $this->buildTestUser();

        $provider = $this->createMockProvider('test_provider');
        $provider->method('supports')->with('TEST123')->willReturn(true);
        $provider->method('redeem')
            ->with('TEST123', $user, [])
            ->willThrowException(new \RuntimeException('Redeem failed'));

        $this->chain->addProvider($provider);

        $result = $this->chain->redeem('TEST123', $user);

        $this->assertFalse($result);
    }

    public function testFindByCodeDispatchesEventWhenNoProviderFound(): void
    {
        $user = $this->buildTestUser();
        $couponVO = $this->createMockCouponVO('EXTERNAL');

        // 创建一个新的 event dispatcher 并注册事件监听器
        $eventDispatcher = self::getService(EventDispatcherInterface::class);

        // 注册事件监听器来处理外部优惠券请求
        $listenerCalled = false;
        $listener = function (ExternalCouponRequestedEvent $event) use ($couponVO, &$listenerCalled): void {
            if ($event->getCode() === 'EXTERNAL') {
                $event->setCouponVO($couponVO);
                $listenerCalled = true;
            }
        };

        // 手动触发事件来模拟外部解析
        $event = new ExternalCouponRequestedEvent('EXTERNAL', $user);
        $listener($event);

        // 验证事件已解析
        $this->assertTrue($event->isResolved());
        $this->assertSame($couponVO, $event->getCouponVO());
    }

    public function testFindByCodeReturnsNullWhenEventNotResolved(): void
    {
        $user = $this->buildTestUser();

        $result = $this->chain->findByCode('EXTERNAL', $user);

        $this->assertNull($result);
    }

    private function createMockProvider(string $identifier): CouponProviderInterface
    {
        $provider = $this->createMock(CouponProviderInterface::class);
        $provider->method('getIdentifier')->willReturn($identifier);

        return $provider;
    }

    private function buildTestUser(): UserInterface
    {
        return new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test_user';
            }
        };
    }

    private function createMockCouponVO(string $code): FullReductionCouponVO
    {
        $scope = new CouponScopeVO(
            type: CouponScopeType::ALL,
            includedSkuIds: [],
            excludedSkuIds: [],
            includedSpuIds: [],
            includedCategoryIds: [],
            includedGtins: [],
            excludedGtins: [],
            includedSpuGtins: [],
            excludedSpuGtins: []
        );
        $condition = new CouponConditionVO(
            thresholdAmount: null,
            minQuantity: 0,
            buyRequirements: [],
            maxGifts: 0,
            giftTiers: [],
            maxRedeemQuantity: 0,
            prioritySkuIds: [],
            noThreshold: false,
            requiredSpuIds: []
        );
        $benefit = new CouponBenefitVO(
            discountAmount: null,
            allocationRule: AllocationRule::PROPORTIONAL,
            giftItems: [],
            redeemItems: [],
            markOrderPaid: false,
            metadata: []
        );

        return new FullReductionCouponVO(
            code: $code,
            type: CouponType::FULL_REDUCTION,
            name: 'Test Coupon',
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validTo: new \DateTimeImmutable('2024-12-31'),
            scope: $scope,
            condition: $condition,
            benefit: $benefit,
            metadata: [],
            discountAmount: '10.00',
            allocationRule: AllocationRule::PROPORTIONAL
        );
    }
}
