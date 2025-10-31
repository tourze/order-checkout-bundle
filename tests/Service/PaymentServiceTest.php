<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Repository\ContractRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\OrderCheckoutBundle\Exception\PaymentException;
use Tourze\OrderCheckoutBundle\Service\PaymentService;
use Tourze\PaymentContracts\Event\PaymentParametersRequestedEvent;

/**
 * @internal
 */
#[CoversClass(PaymentService::class)]
final class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;

    private EntityManagerInterface&MockObject $entityManager;

    private ContractRepository&MockObject $contractRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->contractRepository = $this->createMock(ContractRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->paymentService = new PaymentService(
            $this->entityManager,
            $this->contractRepository,
            $this->eventDispatcher
        );
    }

    public function testGetOrderById(): void
    {
        $contract = $this->createMock(Contract::class);
        $this->contractRepository->method('find')->with(123)->willReturn($contract);

        $result = $this->paymentService->getOrderById(123);
        $this->assertSame($contract, $result);
    }

    public function testGetOrderByIdReturnsNull(): void
    {
        $this->contractRepository->method('find')->with(999)->willReturn(null);

        $result = $this->paymentService->getOrderById(999);
        $this->assertNull($result);
    }

    public function testSetPaymentMethod(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::INIT);
        $contract->method('getRemark')->willReturn('原始备注');
        $contract->expects($this->once())->method('setRemark')->with('原始备注
支付方式: alipay');

        $this->entityManager->expects($this->once())->method('flush');

        $this->paymentService->setPaymentMethod($contract, 'alipay');
    }

    public function testSetPaymentMethodThrowsExceptionForInvalidState(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::PAID);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('订单状态不允许设置支付方式');

        $this->paymentService->setPaymentMethod($contract, 'alipay');
    }

    public function testSetPaymentMethodAllowsPayingState(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::PAYING);
        $contract->method('getRemark')->willReturn('原始备注');
        $contract->expects($this->once())->method('setRemark')->with('原始备注
支付方式: alipay');

        $this->entityManager->expects($this->once())->method('flush');

        $this->paymentService->setPaymentMethod($contract, 'alipay');
    }

    public function testGetOrderPaymentMethod(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getRemark')->willReturn('原始备注
支付方式: wechat_pay');

        $result = $this->paymentService->getOrderPaymentMethod($contract);
        $this->assertSame('wechat_pay', $result);
    }

    public function testGetOrderPaymentMethodReturnsNull(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getRemark')->willReturn('原始备注');

        $result = $this->paymentService->getOrderPaymentMethod($contract);
        $this->assertNull($result);
    }

    public function testGetPaymentParamsForAlipay(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getSn')->willReturn('ORDER123');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(100.0, false),
        ]));

        $result = $this->paymentService->getPaymentParams($contract, 'alipay');

        $this->assertArrayHasKey('app_id', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertSame('100', $result['total_amount']);
        $this->assertSame('订单支付-ORDER123', $result['subject']);
    }

    public function testGetPaymentParamsForWechatPay(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getSn')->willReturn('ORDER456');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(99.5, false),
        ]));

        $result = $this->paymentService->getPaymentParams($contract, 'wechat_pay');

        $this->assertArrayHasKey('appid', $result);
        $this->assertArrayHasKey('mch_id', $result);
        $this->assertSame(9950, $result['total_fee']); // 99.5 * 100
        $this->assertSame('订单支付-ORDER456', $result['body']);
    }

    public function testCalculateOrderTotal(): void
    {
        $contract = $this->createMock(Contract::class);
        $prices = new ArrayCollection([
            $this->createMockPrice(100.0, false),
            $this->createMockPrice(50.0, false),
            $this->createMockPrice(-20.0, true), // 退款，应该被排除
        ]);

        $contract->method('getPrices')->willReturn($prices);

        $total = $this->paymentService->calculateOrderTotal($contract);
        $this->assertSame(150.0, $total);
    }

    public function testInitiatePayment(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER789');
        $contract->method('getState')->willReturn(OrderState::INIT);
        $contract->method('getRemark')->willReturn('');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(200.0, false),
        ]));

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user123');
        $contract->method('getUser')->willReturn($user);

        $this->contractRepository->method('find')->with(1)->willReturn($contract);
        $contract->expects($this->once())->method('setState')->with(OrderState::PAYING);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->paymentService->initiatePayment(1, 'balance');

        $this->assertArrayHasKey('orderNumber', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame('ORDER789', $result['orderNumber']);
        $this->assertSame(200.0, $result['amount']);
    }

    public function testInitiatePaymentThrowsExceptionForNonExistentOrder(): void
    {
        $this->contractRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('订单不存在');

        $this->paymentService->initiatePayment(999, 'alipay');
    }

    public function testInitiatePaymentAllowsPayingState(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(2);
        $contract->method('getSn')->willReturn('ORDER890');
        $contract->method('getState')->willReturn(OrderState::PAYING);
        $contract->method('getRemark')->willReturn('');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(150.0, false),
        ]));

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user456');
        $contract->method('getUser')->willReturn($user);

        $this->contractRepository->method('find')->with(2)->willReturn($contract);
        $contract->expects($this->once())->method('setState')->with(OrderState::PAYING);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->paymentService->initiatePayment(2, 'wechat_pay');

        $this->assertArrayHasKey('orderNumber', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertSame('ORDER890', $result['orderNumber']);
        $this->assertSame(150.0, $result['amount']);
    }

    public function testProcessPayment(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');
        $contract->method('getState')->willReturn(OrderState::INIT);
        $contract->method('getRemark')->willReturn('支付方式: balance');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(100.0, false),
        ]));

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user123');
        $contract->method('getUser')->willReturn($user);

        $contract->expects($this->once())->method('setState')
            ->with(OrderState::PAYING)
        ;

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->paymentService->processPayment($contract);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('paymentId', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertSame('success', $result['status']);
        $this->assertSame(100.0, $result['amount']);
    }

    public function testProcessPaymentThrowsExceptionForInvalidState(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::PAID);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('订单状态不允许支付');

        $this->paymentService->processPayment($contract);
    }

    public function testProcessPaymentThrowsExceptionForMissingPaymentMethod(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::INIT);
        $contract->method('getRemark')->willReturn('原始备注');

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('未设置支付方式');

        $this->paymentService->processPayment($contract);
    }

    public function testProcessPaymentThrowsExceptionForInvalidAmount(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getState')->willReturn(OrderState::INIT);
        $contract->method('getRemark')->willReturn('支付方式: alipay');
        $contract->method('getPrices')->willReturn(new ArrayCollection([
            $this->createMockPrice(0.0, false),
        ]));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('订单金额异常');

        $this->paymentService->processPayment($contract);
    }

    private function createMockPrice(float $money, bool $isRefund): object
    {
        return new class($money, $isRefund) {
            public function __construct(private float $money, private bool $isRefund)
            {
            }

            public function getMoney(): string
            {
                return (string) $this->money;
            }

            public function isRefund(): bool
            {
                return $this->isRefund;
            }
        };
    }
}
