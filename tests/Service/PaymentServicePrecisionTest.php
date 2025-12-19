<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Service\PaymentService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 支付服务精度测试 - 测试金额计算精度
 *
 * 注意：此测试类专注于精度相关测试，完整的 PaymentService 方法测试请参见 PaymentServiceTest
 *
 * @internal
 */
#[CoversClass(PaymentService::class)]
#[RunTestsInSeparateProcesses]
final class PaymentServicePrecisionTest extends AbstractIntegrationTestCase
{
    private PaymentService $paymentService;

    protected function onSetUp(): void
    {
        $this->paymentService = self::getService(PaymentService::class);
    }

    public function testCalculateOrderTotalWithPrecisionIssues(): void
    {
        // 创建一个真实的合同实体
        $contract = new Contract();
        $contract->setSn('TEST-PRECISION-001');

        // 创建价格项目，这些价格在浮点数运算中会有精度问题
        $price1 = new OrderPrice();
        $price1->setContract($contract);
        $price1->setMoney('0.1');
        $price1->setName('价格1');

        $price2 = new OrderPrice();
        $price2->setContract($contract);
        $price2->setMoney('0.2');
        $price2->setName('价格2');

        $price3 = new OrderPrice();
        $price3->setContract($contract);
        $price3->setMoney('99.99');
        $price3->setName('价格3');

        // 添加一个退款项目，应该被排除
        $refundPrice = new OrderPrice();
        $refundPrice->setContract($contract);
        $refundPrice->setMoney('5.00');
        $refundPrice->setName('退款');
        $refundPrice->setRefund(true);

        $contract->addPrice($price1);
        $contract->addPrice($price2);
        $contract->addPrice($price3);
        $contract->addPrice($refundPrice);

        // 计算总金额
        $total = $this->paymentService->calculateOrderTotal($contract);

        // 验证结果：0.1 + 0.2 + 99.99 = 100.29 (排除退款的 5.00)
        $this->assertSame(100.29, $total);
    }

    public function testCalculateOrderTotalWithComplexAmounts(): void
    {
        $contract = new Contract();
        $contract->setSn('TEST-PRECISION-002');

        // 创建复杂的价格组合
        $amounts = ['12.345', '67.890', '0.001', '0.999', '100.00'];
        foreach ($amounts as $amount) {
            $price = new OrderPrice();
            $price->setContract($contract);
            $price->setMoney($amount);
            $price->setName('价格');
            $contract->addPrice($price);
        }

        $total = $this->paymentService->calculateOrderTotal($contract);

        // 验证总金额（四舍五入到两位小数）
        $this->assertSame(181.24, $total); // 12.345 + 67.890 + 0.001 + 0.999 + 100.00 = 181.235 → 181.24
    }

    public function testCalculateOrderTotalExcludesRefunds(): void
    {
        $contract = new Contract();
        $contract->setSn('TEST-PRECISION-003');

        // 正常价格
        $normalPrice = new OrderPrice();
        $normalPrice->setContract($contract);
        $normalPrice->setMoney('100.00');
        $normalPrice->setName('正常价格');
        $contract->addPrice($normalPrice);

        // 退款价格 - 应该被排除
        $refundPrice = new OrderPrice();
        $refundPrice->setContract($contract);
        $refundPrice->setMoney('50.00');
        $refundPrice->setName('退款');
        $refundPrice->setRefund(true);
        $contract->addPrice($refundPrice);

        $total = $this->paymentService->calculateOrderTotal($contract);

        // 只计算非退款项目
        $this->assertSame(100.00, $total);
    }

    public function testCalculateOrderTotalWithZeroAmount(): void
    {
        $contract = new Contract();
        $contract->setSn('TEST-PRECISION-004');

        $total = $this->paymentService->calculateOrderTotal($contract);

        $this->assertSame(0.00, $total);
    }

    public function testGetPaymentParamsWithPreciseAmounts(): void
    {
        $contract = new Contract();
        $contract->setSn('ORDER123');

        // 创建价格
        $price = new OrderPrice();
        $price->setContract($contract);
        $price->setMoney('99.99');
        $price->setName('商品价格');
        $contract->addPrice($price);

        // 测试微信支付的分单位转换
        $params = $this->paymentService->getPaymentParams($contract, 'wechat_pay');

        // 验证金额转换为分
        $this->assertSame(9999, $params['total_fee']); // 99.99 元 = 9999 分
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

    public function testServiceCanBeInstantiatedFromContainer(): void
    {
        $this->assertInstanceOf(PaymentService::class, $this->paymentService);
    }

    public function testInitiatePayment(): void
    {
        // 详细测试请参见 PaymentServiceTest
        self::markTestSkipped('需要真实订单数据，详细测试见 PaymentServiceTest');
    }

    public function testProcessPayment(): void
    {
        // 详细测试请参见 PaymentServiceTest
        self::markTestSkipped('需要真实订单数据，详细测试见 PaymentServiceTest');
    }
}
