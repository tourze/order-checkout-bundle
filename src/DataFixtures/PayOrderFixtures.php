<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;

/**
 * PayOrder 数据填充
 */
final class PayOrderFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 先创建测试合同数据
        for ($i = 1; $i <= 5; ++$i) {
            $contract = new Contract();
            $contract->setSn('CONTRACT-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT));
            $contract->setState(OrderState::INIT);

            $manager->persist($contract);
            $this->addReference('contract_' . $i, $contract);
        }

        // 创建测试支付订单数据
        for ($i = 1; $i <= 5; ++$i) {
            $payOrder = new PayOrder();
            $payOrder->setAmount(sprintf('%.2f', 100.00 * $i));
            $payOrder->setTradeNo('TXN' . str_pad((string) $i, 12, '0', STR_PAD_LEFT));
            $payOrder->setPayTime(new \DateTimeImmutable(sprintf('2025-09-%02d 12:00:00', $i)));

            // 关联对应的合同
            $contract = $this->getReference('contract_' . $i, Contract::class);
            $payOrder->setContract($contract);

            $manager->persist($payOrder);
            $this->addReference('pay_order_' . $i, $payOrder);
        }

        $manager->flush();
    }
}
