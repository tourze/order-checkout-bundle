<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<OrderExtendedInfo>
 */
#[AsRepository(entityClass: OrderExtendedInfo::class)]
final class OrderExtendedInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderExtendedInfo::class);
    }

    /**
     * @return array<OrderExtendedInfo>
     */
    public function findByOrderIdAndType(int $orderId, string $infoType): array
    {
        $result = $this->createQueryBuilder('oei')
            ->andWhere('oei.orderId = :orderId')
            ->andWhere('oei.infoType = :infoType')
            ->setParameter('orderId', $orderId)
            ->setParameter('infoType', $infoType)
            ->orderBy('oei.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<OrderExtendedInfo> $result */
        return $result;
    }

    public function findLatestByOrderIdAndKey(int $orderId, string $infoType, string $infoKey): ?OrderExtendedInfo
    {
        $result = $this->createQueryBuilder('oei')
            ->andWhere('oei.orderId = :orderId')
            ->andWhere('oei.infoType = :infoType')
            ->andWhere('oei.infoKey = :infoKey')
            ->setParameter('orderId', $orderId)
            ->setParameter('infoType', $infoType)
            ->setParameter('infoKey', $infoKey)
            ->orderBy('oei.createTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        assert($result instanceof OrderExtendedInfo || null === $result);

        return $result;
    }

    /**
     * @return array<OrderExtendedInfo>
     */
    public function findRemarkHistoryByOrderId(int $orderId): array
    {
        $result = $this->createQueryBuilder('oei')
            ->andWhere('oei.orderId = :orderId')
            ->andWhere('oei.infoType = :infoType')
            ->andWhere('oei.infoKey = :infoKey')
            ->setParameter('orderId', $orderId)
            ->setParameter('infoType', 'remark')
            ->setParameter('infoKey', 'customer_remark')
            ->orderBy('oei.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<OrderExtendedInfo> $result */
        return $result;
    }

    public function save(OrderExtendedInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderExtendedInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
