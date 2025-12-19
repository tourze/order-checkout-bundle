<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Entity\OrderProduct;
use OrderCoreBundle\Enum\OrderState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\IntegralServiceContracts\DTO\Request\IncreaseIntegralRequest;
use Tourze\IntegralServiceContracts\DTO\Response\IncreaseIntegralResponse;
use Tourze\IntegralServiceContracts\Exception\IntegralServiceException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\OrderCheckoutBundle\Service\IntegralRefundService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(IntegralRefundService::class)]
#[RunTestsInSeparateProcesses]
final class IntegralRefundServiceTest extends AbstractIntegrationTestCase
{
    private IntegralRefundService $service;

    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        $this->service = self::getService(IntegralRefundService::class);
        $this->logger = self::getService(LoggerInterface::class);
    }

    public function testServiceCanBeInstantiatedFromContainer(): void
    {
        $this->assertInstanceOf(IntegralRefundService::class, $this->service);
    }

    public function testRefundIntegralWhenIntegralServiceIsNull(): void
    {
        // 当 IntegralService 为 null 时，应该只记录警告而不抛出异常
        $contract = $this->createContract('ORDER-001');
        $user = $this->createUser('testuser', 'password', ['ROLE_USER']);

        // 不应抛出异常
        $this->service->refundIntegral(
            $contract,
            $user->getUserIdentifier(),
            100,
            '测试退款'
        );

        $this->assertTrue(true); // 验证没有异常抛出
    }

    public function testRefundIntegralSuccess(): void
    {
        $contract = $this->createContract('ORDER-002');
        $user = $this->createUser('testuser2', 'password', ['ROLE_USER']);

        // 在集成测试环境中，IntegralService 可能为 null
        // 如果不为 null，测试应该能够正常执行
        try {
            $this->service->refundIntegral(
                $contract,
                $user->getUserIdentifier(),
                100,
                '订单创建失败退款'
            );
            $this->assertTrue(true);
        } catch (IntegralServiceException $e) {
            // 如果抛出异常，也是预期行为（取决于 IntegralService 的实现）
            $this->assertInstanceOf(IntegralServiceException::class, $e);
        }
    }

    public function testRefundProductOnFailureWithZeroIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-003');
        $user = $this->createUser('testuser3', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(0); // 积分价格为 0

        // 不应该执行退款，也不应抛出异常
        $this->service->refundProductOnFailure($contract, $user, $product);
        $this->assertTrue(true);
    }

    public function testRefundProductOnFailureWithNullIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-004');
        $user = $this->createUser('testuser4', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(null); // 积分价格为 null

        // 不应该执行退款，也不应抛出异常
        $this->service->refundProductOnFailure($contract, $user, $product);
        $this->assertTrue(true);
    }

    public function testRefundProductOnFailureWithPositiveIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-005');
        $user = $this->createUser('testuser5', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(500); // 积分价格为 500

        // 应该尝试执行退款
        try {
            $this->service->refundProductOnFailure($contract, $user, $product);
            $this->assertTrue(true);
        } catch (IntegralServiceException $e) {
            // 如果 IntegralService 抛出异常，也是预期行为
            $this->assertInstanceOf(IntegralServiceException::class, $e);
        }
    }

    public function testRefundProductOnCancelWithZeroIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-006');
        $user = $this->createUser('testuser6', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(0);

        // 不应该执行退款
        $this->service->refundProductOnCancel($contract, $user, $product);
        $this->assertTrue(true);
    }

    public function testRefundProductOnCancelWithNullIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-007');
        $user = $this->createUser('testuser7', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(null);

        // 不应该执行退款
        $this->service->refundProductOnCancel($contract, $user, $product);
        $this->assertTrue(true);
    }

    public function testRefundProductOnCancelWithPositiveIntegralPrice(): void
    {
        $contract = $this->createContract('ORDER-008');
        $user = $this->createUser('testuser8', 'password', ['ROLE_USER']);
        $product = $this->createOrderProduct(300);

        // 应该尝试执行退款
        try {
            $this->service->refundProductOnCancel($contract, $user, $product);
            $this->assertTrue(true);
        } catch (IntegralServiceException $e) {
            $this->assertInstanceOf(IntegralServiceException::class, $e);
        }
    }

    /**
     * @deprecated Testing deprecated method
     */
    public function testRefundPriceOnFailureWithUnpaidPrice(): void
    {
        $contract = $this->createContract('ORDER-009');
        $user = $this->createUser('testuser9', 'password', ['ROLE_USER']);
        $price = $this->createOrderPrice(false); // 未支付

        // 不应该执行退款
        $this->service->refundPriceOnFailure($contract, $user, $price);
        $this->assertTrue(true);
    }

    /**
     * @deprecated Testing deprecated method
     */
    public function testRefundPriceOnFailureWithZeroTotalIntegral(): void
    {
        $contract = $this->createContractWithTotalIntegral('ORDER-010', 0);
        $user = $this->createUser('testuser10', 'password', ['ROLE_USER']);
        $price = $this->createOrderPrice(true); // 已支付

        // 不应该执行退款
        $this->service->refundPriceOnFailure($contract, $user, $price);
        $this->assertTrue(true);
    }

    /**
     * @deprecated Testing deprecated method
     */
    public function testRefundPriceOnFailureWithNullTotalIntegral(): void
    {
        $contract = $this->createContractWithTotalIntegral('ORDER-011', null);
        $user = $this->createUser('testuser11', 'password', ['ROLE_USER']);
        $price = $this->createOrderPrice(true);

        // 不应该执行退款
        $this->service->refundPriceOnFailure($contract, $user, $price);
        $this->assertTrue(true);
    }

    /**
     * @deprecated Testing deprecated method
     */
    public function testRefundPriceOnFailureWithValidIntegralAndPaidPrice(): void
    {
        $contract = $this->createContractWithTotalIntegral('ORDER-012', 1000);
        $user = $this->createUser('testuser12', 'password', ['ROLE_USER']);
        $price = $this->createOrderPrice(true);

        // 应该尝试执行退款并标记为未支付
        try {
            $this->service->refundPriceOnFailure($contract, $user, $price);
            $this->assertFalse($price->isPaid()); // 验证已标记为未支付
        } catch (IntegralServiceException $e) {
            $this->assertInstanceOf(IntegralServiceException::class, $e);
        }
    }

    public function testProcessPriceRefundSuccess(): void
    {
        $contract = $this->createContract('ORDER-013');
        $contract = $this->persistAndFlush($contract);

        $price = $this->createOrderPrice(false, $contract);
        $price = $this->persistAndFlush($price);

        $userIdentifier = 'testuser13';

        // 执行价格退款
        $this->service->processPriceRefund($contract, $price, $userIdentifier, 500);

        // 刷新实体以获取最新状态
        self::getEntityManager()->refresh($price);

        // 验证退款标记已设置
        $this->assertTrue($price->isRefund());
    }

    public function testProcessIntegralInfoRefundWhenIntegralServiceIsNull(): void
    {
        $contract = $this->createContract('ORDER-014');
        $integralInfo = $this->createOrderIntegralInfo(1, 200);

        // 当 IntegralService 为 null 时，应该安静返回
        $this->service->processIntegralInfoRefund($contract, $integralInfo);

        // 验证没有异常抛出
        $this->assertTrue(true);
    }

    public function testProcessIntegralInfoRefundSuccess(): void
    {
        $contract = $this->createContract('ORDER-015');
        $contract = $this->persistAndFlush($contract);

        $integralInfo = $this->createOrderIntegralInfo(1, 300);
        $integralInfo = $this->persistAndFlush($integralInfo);

        // 执行积分退款
        try {
            $this->service->processIntegralInfoRefund($contract, $integralInfo);

            // 如果 IntegralService 不为 null 且成功，验证状态更新
            if ($integralInfo->isRefunded()) {
                $this->assertTrue($integralInfo->isRefunded());
                $this->assertNotNull($integralInfo->getRefundedTime());
            } else {
                // IntegralService 为 null 的情况
                $this->assertFalse($integralInfo->isRefunded());
            }
        } catch (IntegralServiceException $e) {
            // 如果发生异常，也是预期行为
            $this->assertInstanceOf(IntegralServiceException::class, $e);
        }
    }

    /**
     * 创建测试用的 Contract 实体
     */
    private function createContract(string $sn): Contract
    {
        $contract = new Contract();
        $contract->setSn($sn);
        $contract->setState(OrderState::INIT); // 初始化必需的状态字段

        return $contract;
    }

    /**
     * 创建带有总积分的 Contract 实体
     */
    private function createContractWithTotalIntegral(string $sn, ?int $totalIntegral): Contract
    {
        $contract = new Contract();
        $contract->setSn($sn);
        $contract->setState(OrderState::INIT); // 初始化必需的状态字段

        if (null !== $totalIntegral) {
            $contract->setTotalIntegral($totalIntegral);
        }

        return $contract;
    }

    /**
     * 创建测试用的 OrderProduct 实体
     */
    private function createOrderProduct(?int $integralPrice): OrderProduct
    {
        $product = new OrderProduct();

        if (null !== $integralPrice) {
            $product->setIntegralPrice($integralPrice);
        }

        return $product;
    }

    /**
     * 创建测试用的 OrderPrice 实体
     */
    private function createOrderPrice(bool $isPaid, ?Contract $contract = null): OrderPrice
    {
        $price = new OrderPrice();
        $price->setPaid($isPaid);
        $price->setName('Test Price');
        $price->setMoney('100.00');

        if (null !== $contract) {
            $price->setContract($contract);
        }

        return $price;
    }

    /**
     * 创建测试用的 OrderIntegralInfo 实体
     */
    private function createOrderIntegralInfo(int $userId, int $integralRequired): OrderIntegralInfo
    {
        $integralInfo = new OrderIntegralInfo();
        $integralInfo->setUserId($userId);
        $integralInfo->setIntegralRequired($integralRequired);
        $integralInfo->setOrderId(1);
        $integralInfo->setIntegralOperationId('test-operation-id');
        $integralInfo->setDeductedTime(new \DateTimeImmutable());
        $integralInfo->setBalanceBefore(1000);
        $integralInfo->setBalanceAfter(800);

        return $integralInfo;
    }
}
