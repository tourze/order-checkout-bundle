<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ShippingTemplateArea>
 */
#[AsRepository(entityClass: ShippingTemplateArea::class)]
class ShippingTemplateAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingTemplateArea::class);
    }

    /**
     * @return ShippingTemplateArea[]
     */
    public function findByLocation(ShippingTemplate $template, string $provinceCode, ?string $cityCode = null, ?string $areaCode = null): array
    {
        $qb = $this->createQueryBuilder('sta')
            ->where('sta.shippingTemplate = :template')
            ->andWhere('sta.provinceCode = :provinceCode')
            ->andWhere('sta.isDeliverable = true')
            ->setParameter('template', $template)
            ->setParameter('provinceCode', $provinceCode)
        ;

        if (null !== $areaCode) {
            $qb->andWhere('(sta.areaCode = :areaCode OR sta.areaCode IS NULL)')
                ->setParameter('areaCode', $areaCode)
            ;
        }

        if (null !== $cityCode) {
            $qb->andWhere('(sta.cityCode = :cityCode OR sta.cityCode IS NULL)')
                ->setParameter('cityCode', $cityCode)
            ;
        }

        $qb->orderBy('sta.areaCode', 'DESC')
            ->addOrderBy('sta.cityCode', 'DESC')
            ->addOrderBy('sta.provinceCode', 'ASC')
        ;

        $result = $qb->getQuery()->getResult();
        assert(is_array($result));

        /** @var array<ShippingTemplateArea> $result */
        return $result;
    }

    public function findBestMatchForLocation(ShippingTemplate $template, string $provinceCode, ?string $cityCode = null, ?string $areaCode = null): ?ShippingTemplateArea
    {
        $areas = $this->findByLocation($template, $provinceCode, $cityCode, $areaCode);

        $bestMatch = null;
        $bestLevel = 0;

        foreach ($areas as $area) {
            if ($area->matchesLocation($provinceCode, $cityCode, $areaCode)) {
                $level = $area->getLocationLevel();
                if ($level > $bestLevel) {
                    $bestMatch = $area;
                    $bestLevel = $level;
                }
            }
        }

        return $bestMatch;
    }

    public function findBestMatchForLocationByName(ShippingTemplate $template, string $province, ?string $city = null, ?string $area = null): ?ShippingTemplateArea
    {
        $qb = $this->createQueryBuilder('sta')
            ->where('sta.shippingTemplate = :template')
            ->andWhere('sta.provinceName = :province')
            ->andWhere('sta.isDeliverable = true')
            ->setParameter('template', $template)
            ->setParameter('province', $province)
        ;

        if (null !== $area) {
            $qb->andWhere('(sta.areaName = :area OR sta.areaName IS NULL)')
                ->setParameter('area', $area)
            ;
        }

        if (null !== $city) {
            $qb->andWhere('(sta.cityName = :city OR sta.cityName IS NULL)')
                ->setParameter('city', $city)
            ;
        }

        $qb->orderBy('sta.areaName', 'DESC')
            ->addOrderBy('sta.cityName', 'DESC')
            ->addOrderBy('sta.provinceName', 'ASC')
        ;

        $areas = $qb->getQuery()->getResult();
        assert(is_array($areas));

        $bestMatch = null;
        $bestLevel = 0;

        foreach ($areas as $areaRecord) {
            assert($areaRecord instanceof ShippingTemplateArea);
            if ($areaRecord->matchesLocationByName($province, $city, $area)) {
                $level = $areaRecord->getLocationLevel();
                if ($level > $bestLevel) {
                    $bestMatch = $areaRecord;
                    $bestLevel = $level;
                }
            }
        }

        return $bestMatch;
    }

    public function isLocationDeliverable(ShippingTemplate $template, string $provinceCode, ?string $cityCode = null, ?string $areaCode = null): bool
    {
        $qb = $this->createQueryBuilder('sta')
            ->select('COUNT(sta.id)')
            ->where('sta.shippingTemplate = :template')
            ->andWhere('sta.provinceCode = :provinceCode')
            ->andWhere('sta.isDeliverable = false')
            ->setParameter('template', $template)
            ->setParameter('provinceCode', $provinceCode)
        ;

        if (null !== $cityCode) {
            $qb->andWhere('(sta.cityCode = :cityCode OR sta.cityCode IS NULL)')
                ->setParameter('cityCode', $cityCode)
            ;
        }

        if (null !== $areaCode) {
            $qb->andWhere('(sta.areaCode = :areaCode OR sta.areaCode IS NULL)')
                ->setParameter('areaCode', $areaCode)
            ;
        }

        $nonDeliverableCount = (int) $qb->getQuery()->getSingleScalarResult();

        return 0 === $nonDeliverableCount;
    }

    public function isLocationDeliverableByName(ShippingTemplate $template, string $province, ?string $city = null, ?string $area = null): bool
    {
        $qb = $this->createQueryBuilder('sta')
            ->select('COUNT(sta.id)')
            ->where('sta.shippingTemplate = :template')
            ->andWhere('sta.provinceName = :province')
            ->andWhere('sta.isDeliverable = false')
            ->setParameter('template', $template)
            ->setParameter('province', $province)
        ;

        if (null !== $city) {
            $qb->andWhere('(sta.cityName = :city OR sta.cityName IS NULL)')
                ->setParameter('city', $city)
            ;
        }

        if (null !== $area) {
            $qb->andWhere('(sta.areaName = :area OR sta.areaName IS NULL)')
                ->setParameter('area', $area)
            ;
        }

        $nonDeliverableCount = (int) $qb->getQuery()->getSingleScalarResult();

        return 0 === $nonDeliverableCount;
    }

    /**
     * 保存实体
     */
    public function save(ShippingTemplateArea $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除实体
     */
    public function remove(ShippingTemplateArea $entity, bool $flush = true): void
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
     * @param array<ShippingTemplateArea> $entities
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
