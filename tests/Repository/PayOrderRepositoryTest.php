<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Enum\OrderType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;
use Tourze\OrderCheckoutBundle\Repository\PayOrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(PayOrderRepository::class)]
#[RunTestsInSeparateProcesses]
final class PayOrderRepositoryTest extends AbstractRepositoryTestCase
{
    private PayOrderRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(PayOrderRepository::class);
    }

    protected function createNewEntity(): PayOrder
    {
        // 创建关联的Contract实体
        $contract = new Contract();
        $contract->setSn('TEST-PAY-CONTRACT-' . uniqid());
        $contract->setState(OrderState::PAID);
        $contract->setType(OrderType::DEFAULT->value);
        $contract->setRemark('Test Pay Contract');

        // 添加价格信息
        $orderPrice = new OrderPrice();
        $orderPrice->setName('支付价格');
        $orderPrice->setCurrency('CNY');
        $orderPrice->setMoney('200.00');
        $orderPrice->setContract($contract);

        $contract->addPrice($orderPrice);

        self::getEntityManager()->persist($contract);
        self::getEntityManager()->persist($orderPrice);
        self::getEntityManager()->flush();

        // 创建PayOrder实体
        $entity = new PayOrder();
        $entity->setContract($contract);
        $entity->setAmount('200.00');
        $entity->setTradeNo('TEST-TRADE-' . uniqid());
        $entity->setPayTime(new \DateTimeImmutable());

        return $entity;
    }

    /** @return ServiceEntityRepository<PayOrder> */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    public function testFindByTradeNo(): void
    {
        $entity = $this->createNewEntity();
        $this->assertInstanceOf(PayOrder::class, $entity);
        $this->repository->save($entity, true);

        $tradeNo = $entity->getTradeNo();
        $this->assertNotNull($tradeNo);

        $result = $this->repository->findByTradeNo($tradeNo);

        $this->assertInstanceOf(PayOrder::class, $result);
        $this->assertSame($tradeNo, $result->getTradeNo());
    }

    public function testFindByContractId(): void
    {
        $entity = $this->createNewEntity();
        $this->assertInstanceOf(PayOrder::class, $entity);
        $this->repository->save($entity, true);

        $contract = $entity->getContract();
        $this->assertNotNull($contract);

        $contractId = $contract->getId();
        $this->assertNotNull($contractId);

        $result = $this->repository->findByContractId($contractId);

        $this->assertInstanceOf(PayOrder::class, $result);
        $resultContract = $result->getContract();
        $this->assertNotNull($resultContract);
        $this->assertSame($contractId, $resultContract->getId());
    }

    public function testFindByTradeNoReturnsNull(): void
    {
        $result = $this->repository->findByTradeNo('NONEXISTENT-TRADE-NO-' . uniqid());

        $this->assertNull($result);
    }

    public function testFindByContractIdReturnsNull(): void
    {
        $result = $this->repository->findByContractId(999999999);

        $this->assertNull($result);
    }

    public function testClear(): void
    {
        $entity = $this->createNewEntity();
        $this->repository->save($entity, false);

        $this->repository->clear();

        // 验证实体管理器已清空
        $this->assertFalse(self::getEntityManager()->contains($entity));
    }

    public function testFlush(): void
    {
        $entity = $this->createNewEntity();
        self::getEntityManager()->persist($entity);

        $this->repository->flush();

        // 验证实体已保存到数据库
        $this->assertGreaterThan(0, $entity->getId(), 'Entity should have a positive ID after persistence');
    }

    public function testSaveAll(): void
    {
        $entity1 = $this->createNewEntity();
        $entity2 = $this->createNewEntity();
        $entities = [$entity1, $entity2];

        $this->repository->saveAll($entities);

        // 验证所有实体都已保存
        $this->assertGreaterThan(0, $entity1->getId(), 'First entity should have a positive ID');
        $this->assertGreaterThan(0, $entity2->getId(), 'Second entity should have a positive ID');
    }
}
