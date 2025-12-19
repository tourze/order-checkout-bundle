<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<OrderIntegralInfo>
 */
#[AsRepository(entityClass: OrderIntegralInfo::class)]
final class OrderIntegralInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderIntegralInfo::class);
    }

    /**
     * 根据订单ID查询积分信息
     */
    public function findByOrderId(int $orderId): ?OrderIntegralInfo
    {
        return $this->findOneBy(['orderId' => $orderId]);
    }

    /**
     * 保存积分信息（支持新增和更新）
     */
    public function save(OrderIntegralInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除积分信息
     */
    public function remove(OrderIntegralInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 批量查询用户的积分订单（用于审计）
     *
     * @return OrderIntegralInfo[]
     */
    public function findByUserId(int $userId, int $limit = 100): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('o.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计未退还的积分订单数量（监控指标）
     */
    public function countUnrefunded(): int
    {
        return $this->count(['isRefunded' => false]);
    }
}
