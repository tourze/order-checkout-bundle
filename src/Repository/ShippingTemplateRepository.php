<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ShippingTemplate>
 */
#[AsRepository(entityClass: ShippingTemplate::class)]
class ShippingTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingTemplate::class);
    }

    public function findDefault(): ?ShippingTemplate
    {
        return $this->findOneBy([
            'isDefault' => true,
            'status' => ShippingTemplateStatus::ACTIVE,
        ]);
    }

    /**
     * @return ShippingTemplate[]
     */
    public function findActiveTemplates(): array
    {
        return $this->findBy(
            ['status' => ShippingTemplateStatus::ACTIVE],
            ['isDefault' => 'DESC', 'name' => 'ASC']
        );
    }

    /**
     * @return ShippingTemplate[]
     */
    public function findByChargeType(ChargeType $chargeType): array
    {
        return $this->findBy([
            'chargeType' => $chargeType,
            'status' => ShippingTemplateStatus::ACTIVE,
        ], ['isDefault' => 'DESC', 'name' => 'ASC']);
    }

    /**
     * 保存实体
     */
    public function save(ShippingTemplate $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(ShippingTemplate $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 批量保存
     */
    /**
     * @param array<ShippingTemplate> $entities
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
}
