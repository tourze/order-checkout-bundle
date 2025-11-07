<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Repository\ContractRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\OrderCheckoutBundle\Service\PaymentService;

class PaymentServicePrecisionTest extends TestCase
{
    private PaymentService $paymentService;
    private EntityManagerInterface|MockObject $entityManager;
    private ContractRepository|MockObject $contractRepository;
    private EventDispatcherInterface|MockObject $eventDispatcher;

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

    public function testCalculateOrderTotalWithPrecisionIssues(): void
    {
        // 创建一个合同 mock
        $contract = $this->createMock(Contract::class);

        // 创建价格项目，这些价格在浮点数运算中会有精度问题
        $price1 = $this->createMock(OrderPrice::class);
        $price1->method('getMoney')->willReturn('0.1');
        $price1->method('isRefund')->willReturn(false);

        $price2 = $this->createMock(OrderPrice::class);
        $price2->method('getMoney')->willReturn('0.2');
        $price2->method('isRefund')->willReturn(false);

        $price3 = $this->createMock(OrderPrice::class);
        $price3->method('getMoney')->willReturn('99.99');
        $price3->method('isRefund')->willReturn(false);

        // 添加一个退款项目，应该被排除
        $refundPrice = $this->createMock(OrderPrice::class);
        $refundPrice->method('getMoney')->willReturn('5.00');
        $refundPrice->method('isRefund')->willReturn(true);

        $prices = new ArrayCollection([$price1, $price2, $price3, $refundPrice]);
        $contract->method('getPrices')->willReturn($prices);

        // 计算总金额
        $total = $this->paymentService->calculateOrderTotal($contract);

        // 验证结果：0.1 + 0.2 + 99.99 = 100.29 (排除退款的 5.00)
        $this->assertSame(100.29, $total);
        
        // 确保精度正确（浮点数 0.1 + 0.2 实际上不等于 0.3）
        $floatSum = 0.1 + 0.2 + 99.99;
        $this->assertNotSame(100.29, $floatSum); // 证明浮点数有精度问题
    }

    public function testCalculateOrderTotalWithComplexAmounts(): void
    {
        $contract = $this->createMock(Contract::class);

        // 创建复杂的价格组合
        $prices = [];
        $expectedTotal = 0.0;
        
        $amounts = ['12.345', '67.890', '0.001', '0.999', '100.00'];
        foreach ($amounts as $amount) {
            $price = $this->createMock(OrderPrice::class);
            $price->method('getMoney')->willReturn($amount);
            $price->method('isRefund')->willReturn(false);
            $prices[] = $price;
            $expectedTotal += (float) $amount;
        }

        $contract->method('getPrices')->willReturn(new ArrayCollection($prices));

        $total = $this->paymentService->calculateOrderTotal($contract);

        // 验证总金额（四舍五入到两位小数）
        $this->assertSame(181.24, $total); // 12.345 + 67.890 + 0.001 + 0.999 + 100.00 = 181.235 → 181.24
    }

    public function testCalculateOrderTotalExcludesRefunds(): void
    {
        $contract = $this->createMock(Contract::class);

        // 正常价格
        $normalPrice = $this->createMock(OrderPrice::class);
        $normalPrice->method('getMoney')->willReturn('100.00');
        $normalPrice->method('isRefund')->willReturn(false);

        // 退款价格 - 应该被排除
        $refundPrice = $this->createMock(OrderPrice::class);
        $refundPrice->method('getMoney')->willReturn('50.00');
        $refundPrice->method('isRefund')->willReturn(true);

        $prices = [$normalPrice, $refundPrice];
        $contract->method('getPrices')->willReturn(new ArrayCollection($prices));

        $total = $this->paymentService->calculateOrderTotal($contract);

        // 只计算非退款项目
        $this->assertSame(100.00, $total);
    }

    public function testCalculateOrderTotalWithZeroAmount(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getPrices')->willReturn(new ArrayCollection([]));

        $total = $this->paymentService->calculateOrderTotal($contract);

        $this->assertSame(0.00, $total);
    }

    public function testGetPaymentParamsWithPreciseAmounts(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getSn')->willReturn('ORDER123');

        // Mock 价格计算
        $price = $this->createMock(OrderPrice::class);
        $price->method('getMoney')->willReturn('99.99');
        $price->method('isRefund')->willReturn(false);

        $contract->method('getPrices')->willReturn(new ArrayCollection([$price]));

        // 测试微信支付的分单位转换
        $params = $this->paymentService->getPaymentParams($contract, 'wechat_pay');

        // 验证金额转换为分
        $this->assertSame(9999, $params['total_fee']); // 99.99 元 = 9999 分
        $this->assertSame('ORDER123', $contract->getSn());
    }

    public function testFloatingPointDemonstration(): void
    {
        // 这个测试演示为什么需要精确计算
        $a = 0.1;
        $b = 0.2;
        $floatResult = $a + $b;
        
        // 浮点数精度问题
        $this->assertNotSame(0.3, $floatResult);
        $this->assertTrue(abs($floatResult - 0.3) > 0); // 有微小差异
        
        // 使用 sprintf 格式化后的比较
        $this->assertSame('0.30', sprintf('%.2f', $floatResult));
        
        // 但在某些复杂计算中，即使 sprintf 也可能不够精确
        $complexFloat = (0.1 + 0.2) * 100;
        $this->assertNotSame(30.0, $complexFloat); // 证明精度问题的存在
    }
}