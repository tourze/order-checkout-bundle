<?php

namespace Tourze\OrderCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<PayOrder>
 */
#[AsRepository(entityClass: PayOrder::class)]
final class PayOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayOrder::class);
    }

    /**
     * 保存实体
     */
    public function save(PayOrder $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(PayOrder $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 批量保存
     * @param array<PayOrder> $entities
     */
    public function saveAll(array $entities, bool $flush = true): void
    {
        foreach ($entities as $entity) {
            $this->getEntityManager()->persist($entity);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 刷新实体管理器
     */
    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * 清空实体管理器
     */
    public function clear(): void
    {
        $this->getEntityManager()->clear();
    }

    /**
     * 根据交易号查找支付订单
     */
    public function findByTradeNo(string $tradeNo): ?PayOrder
    {
        return $this->findOneBy(['tradeNo' => $tradeNo]);
    }

    /**
     * 根据合同ID查找支付订单
     */
    public function findByContractId(int $contractId): ?PayOrder
    {
        return $this->findOneBy(['contract' => $contractId]);
    }
}
