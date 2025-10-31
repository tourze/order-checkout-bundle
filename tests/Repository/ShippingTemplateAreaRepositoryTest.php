<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateAreaRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateAreaRepository::class)]
#[RunTestsInSeparateProcesses]
final class ShippingTemplateAreaRepositoryTest extends AbstractRepositoryTestCase
{
    private ShippingTemplateAreaRepository $repository;

    private ShippingTemplate $template;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(ShippingTemplateAreaRepository::class);

        // 创建测试模板
        $this->template = new ShippingTemplate();
        $this->template->setName('测试物流模板');
        $this->template->setChargeType(ChargeType::WEIGHT);
        $this->template->setStatus(ShippingTemplateStatus::ACTIVE);

        $this->persistAndFlush($this->template);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShippingTemplateAreaRepository::class, $this->repository);
    }

    public function testFindByLocation(): void
    {
        // 创建测试区域数据
        $beijingProvince = $this->createArea('11', '北京', null, null, null, null);
        $beijingCity = $this->createArea('11', '北京', '1101', '北京市', null, null);
        $chaoyang = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区');
        $haidian = $this->createArea('11', '北京', '1101', '北京市', '110108', '海淀区');

        // 创建其他省份的区域，不应该被查询到
        $shanghai = $this->createArea('31', '上海', null, null, null, null);

        $this->persistAndFlush($beijingProvince);
        $this->persistAndFlush($beijingCity);
        $this->persistAndFlush($chaoyang);
        $this->persistAndFlush($haidian);
        $this->persistAndFlush($shanghai);

        // 测试查询北京省的所有区域
        $results = $this->repository->findByLocation($this->template, '11');

        $this->assertCount(4, $results);
        // Verify all results are ShippingTemplateArea instances
        foreach ($results as $result) {
            $this->assertInstanceOf(ShippingTemplateArea::class, $result);
        }

        // 验证排序（按areaCode DESC, cityCode DESC, provinceCode ASC）
        $this->assertSame('110108', $results[0]->getAreaCode()); // 海淀区
        $this->assertSame('110105', $results[1]->getAreaCode()); // 朝阳区
        $this->assertSame('1101', $results[2]->getCityCode()); // 北京市（无区县）
        $this->assertNull($results[3]->getCityCode()); // 北京省（无市无区）

        // 测试查询北京市朝阳区
        $chaoyangResults = $this->repository->findByLocation($this->template, '11', '1101', '110105');
        $this->assertCount(3, $chaoyangResults); // 应该匹配：北京省、北京市、朝阳区（不包括海淀区）

        // 测试不存在的省份
        $emptyResults = $this->repository->findByLocation($this->template, '99');
        $this->assertEmpty($emptyResults);
    }

    public function testFindByLocationWithNonDeliverableAreas(): void
    {
        // 创建可配送和不可配送的区域
        $deliverable = $this->createArea('11', '北京', null, null, null, null, true);
        $nonDeliverable = $this->createArea('11', '北京', '1101', '北京市', null, null, false);

        $this->persistAndFlush($deliverable);
        $this->persistAndFlush($nonDeliverable);

        // 查询结果应该只包含可配送的区域
        $results = $this->repository->findByLocation($this->template, '11');

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isDeliverable());
        $this->assertNull($results[0]->getCityCode()); // 应该是省级的可配送区域
    }

    public function testFindBestMatchForLocation(): void
    {
        // 创建不同级别的区域
        $beijingProvince = $this->createArea('11', '北京', null, null, null, null);
        $beijingCity = $this->createArea('11', '北京', '1101', '北京市', null, null);
        $chaoyang = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区');

        $this->persistAndFlush($beijingProvince);
        $this->persistAndFlush($beijingCity);
        $this->persistAndFlush($chaoyang);

        // 测试精确匹配朝阳区（最高级别）
        $bestMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1101', '110105');
        $this->assertNotNull($bestMatch);
        $this->assertSame('110105', $bestMatch->getAreaCode());
        $this->assertSame('朝阳区', $bestMatch->getAreaName());
        $this->assertSame(3, $bestMatch->getLocationLevel());

        // 测试匹配北京市（应该返回最具体的匹配 - 朝阳区，因为朝阳区也属于北京市）
        $cityMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1101');
        $this->assertNotNull($cityMatch);
        // 期望返回最具体的匹配（朝阳区），因为它的级别最高
        $this->assertSame('110105', $cityMatch->getAreaCode());
        $this->assertSame('朝阳区', $cityMatch->getAreaName());
        $this->assertSame(3, $cityMatch->getLocationLevel());

        // 测试匹配北京省（应该返回最具体的匹配 - 朝阳区，因为它级别最高）
        $provinceMatch = $this->repository->findBestMatchForLocation($this->template, '11');
        $this->assertNotNull($provinceMatch);
        // 期望返回最具体的匹配（朝阳区），因为它的级别最高
        $this->assertSame('1101', $provinceMatch->getCityCode());
        $this->assertSame('朝阳区', $provinceMatch->getAreaName());
        $this->assertSame(3, $provinceMatch->getLocationLevel());

        // 测试不存在的区域
        $noMatch = $this->repository->findBestMatchForLocation($this->template, '99');
        $this->assertNull($noMatch);
    }

    public function testFindBestMatchForLocationByName(): void
    {
        // 创建基于名称的区域
        $beijingProvince = $this->createArea('11', '北京', null, null, null, null);
        $beijingCity = $this->createArea('11', '北京', '1101', '北京市', null, null);
        $chaoyang = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区');

        $this->persistAndFlush($beijingProvince);
        $this->persistAndFlush($beijingCity);
        $this->persistAndFlush($chaoyang);

        // 测试按名称精确匹配
        $bestMatch = $this->repository->findBestMatchForLocationByName($this->template, '北京', '北京市', '朝阳区');
        $this->assertNotNull($bestMatch);
        $this->assertSame('朝阳区', $bestMatch->getAreaName());
        $this->assertSame(3, $bestMatch->getLocationLevel());

        // 测试按名称匹配城市（应该返回最具体的匹配 - 朝阳区）
        $cityMatch = $this->repository->findBestMatchForLocationByName($this->template, '北京', '北京市');
        $this->assertNotNull($cityMatch);
        // 期望返回最具体的匹配（朝阳区），因为它的级别最高
        $this->assertSame('朝阳区', $cityMatch->getAreaName());
        $this->assertSame(3, $cityMatch->getLocationLevel());

        // 测试按名称匹配省份（应该返回最具体的匹配 - 朝阳区）
        $provinceMatch = $this->repository->findBestMatchForLocationByName($this->template, '北京');
        $this->assertNotNull($provinceMatch);
        // 期望返回最具体的匹配（朝阳区），因为它的级别最高
        $this->assertSame('朝阳区', $provinceMatch->getAreaName());
        $this->assertSame(3, $provinceMatch->getLocationLevel());

        // 测试不存在的地区
        $noMatch = $this->repository->findBestMatchForLocationByName($this->template, '不存在的省');
        $this->assertNull($noMatch);
    }

    public function testIsLocationDeliverable(): void
    {
        // 创建可配送和不可配送的区域
        $deliverable = $this->createArea('11', '北京', null, null, null, null, true);
        $nonDeliverableCity = $this->createArea('11', '北京', '1101', '北京市', null, null, false);
        $nonDeliverableArea = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区', false);

        $this->persistAndFlush($deliverable);
        $this->persistAndFlush($nonDeliverableCity);
        $this->persistAndFlush($nonDeliverableArea);

        // 测试省级可配送但市级不可配送的情况
        $isDeliverable = $this->repository->isLocationDeliverable($this->template, '11', '1101');
        $this->assertFalse($isDeliverable);

        // 测试省级可配送但区级不可配送的情况
        $isAreaDeliverable = $this->repository->isLocationDeliverable($this->template, '11', '1101', '110105');
        $this->assertFalse($isAreaDeliverable);

        // 测试其他区域（没有不可配送记录）
        $isOtherDeliverable = $this->repository->isLocationDeliverable($this->template, '11', '1102');
        $this->assertTrue($isOtherDeliverable);

        // 测试完全不存在的省份
        $isUnknownDeliverable = $this->repository->isLocationDeliverable($this->template, '99');
        $this->assertTrue($isUnknownDeliverable);
    }

    public function testIsLocationDeliverableByName(): void
    {
        // 创建基于名称的配送限制
        $nonDeliverableCity = $this->createArea('11', '北京', '1101', '北京市', null, null, false);
        $nonDeliverableArea = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区', false);

        $this->persistAndFlush($nonDeliverableCity);
        $this->persistAndFlush($nonDeliverableArea);

        // 测试按名称检查市级不可配送
        $isCityDeliverable = $this->repository->isLocationDeliverableByName($this->template, '北京', '北京市');
        $this->assertFalse($isCityDeliverable);

        // 测试按名称检查区级不可配送
        $isAreaDeliverable = $this->repository->isLocationDeliverableByName($this->template, '北京', '北京市', '朝阳区');
        $this->assertFalse($isAreaDeliverable);

        // 测试可配送的区域
        $isOtherDeliverable = $this->repository->isLocationDeliverableByName($this->template, '上海');
        $this->assertTrue($isOtherDeliverable);
    }

    public function testSaveAndRemoveAndFlush(): void
    {
        $area = $this->createArea('31', '上海', null, null, null, null);

        // 测试save
        $this->repository->save($area, true);
        $this->assertEntityPersisted($area);

        $id = $area->getId();

        // 重新查找实体以确保其在管理状态
        $managedArea = $this->repository->find($id);
        $this->assertNotNull($managedArea);

        // 测试remove
        $this->repository->remove($managedArea, true);
        $this->assertEntityNotExists(ShippingTemplateArea::class, $id);
    }

    public function testSaveAll(): void
    {
        $area1 = $this->createArea('31', '上海', null, null, null, null);
        $area2 = $this->createArea('32', '江苏', null, null, null, null);
        $area3 = $this->createArea('33', '浙江', null, null, null, null);

        $entities = [$area1, $area2, $area3];

        // 批量保存
        $this->repository->saveAll($entities, true);

        // 验证都已保存
        foreach ($entities as $entity) {
            $this->assertEntityPersisted($entity);
        }
    }

    public function testFlush(): void
    {
        $area = $this->createArea('31', '上海', null, null, null, null);

        // 先persist但不flush
        $em = self::getEntityManager();
        $em->persist($area);

        // 确保实体在EntityManager中但未flush
        $this->assertTrue($em->contains($area));

        // 使用repository的flush方法
        $this->repository->flush();

        // 验证实体已被保存
        $this->assertEntityPersisted($area);
    }

    public function testClear(): void
    {
        $area = $this->createArea('31', '上海', null, null, null, null);

        // 先保存实体
        $em = self::getEntityManager();
        $em->persist($area);
        $em->flush();

        // 确保实体在EntityManager中
        $this->assertTrue($em->contains($area));

        // 使用repository的clear方法
        $this->repository->clear();

        // 验证EntityManager被清空
        $this->assertFalse($em->contains($area));
    }

    public function testCompleteLocationMatchingWorkflow(): void
    {
        // 创建完整的地理层次结构
        $beijingProvince = $this->createArea('11', '北京', null, null, null, null);
        $beijingCity = $this->createArea('11', '北京', '1101', '北京市', null, null);
        $chaoyang = $this->createArea('11', '北京', '1101', '北京市', '110105', '朝阳区');
        $haidian = $this->createArea('11', '北京', '1101', '北京市', '110108', '海淀区');

        // 设置不同的运费
        $beijingProvince->setFirstUnit('1.0');
        $beijingProvince->setFirstUnitFee('10.00');
        $beijingCity->setFirstUnit('1.0');
        $beijingCity->setFirstUnitFee('8.00');
        $chaoyang->setFirstUnit('1.0');
        $chaoyang->setFirstUnitFee('6.00');
        $haidian->setFirstUnit('1.0');
        $haidian->setFirstUnitFee('5.00');

        $this->repository->saveAll([$beijingProvince, $beijingCity, $chaoyang, $haidian], true);

        // 1. 测试查找朝阳区的最佳匹配
        $bestMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1101', '110105');
        $this->assertNotNull($bestMatch);
        $this->assertSame('6.00', $bestMatch->getFirstUnitFee());
        $this->assertSame(3, $bestMatch->getLocationLevel());

        // 2. 测试查找海淀区的最佳匹配
        $bestMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1101', '110108');
        $this->assertNotNull($bestMatch);
        $this->assertSame('5.00', $bestMatch->getFirstUnitFee());

        // 3. 测试查找北京市其他区（应该匹配到市级）
        $bestMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1101', '110199');
        $this->assertNotNull($bestMatch);
        $this->assertSame('8.00', $bestMatch->getFirstUnitFee());
        $this->assertSame(2, $bestMatch->getLocationLevel());

        // 4. 测试查找北京其他市（应该匹配到省级）
        $bestMatch = $this->repository->findBestMatchForLocation($this->template, '11', '1199');
        $this->assertNotNull($bestMatch);
        $this->assertSame('10.00', $bestMatch->getFirstUnitFee());
        $this->assertSame(1, $bestMatch->getLocationLevel());

        // 5. 测试按名称查找
        $bestMatch = $this->repository->findBestMatchForLocationByName($this->template, '北京', '北京市', '朝阳区');
        $this->assertNotNull($bestMatch);
        $this->assertSame('6.00', $bestMatch->getFirstUnitFee());

        // 6. 验证查询结果的准确性
        $allBeijing = $this->repository->findByLocation($this->template, '11');
        $this->assertCount(4, $allBeijing);

        // 7. 测试配送可达性
        $this->assertTrue($this->repository->isLocationDeliverable($this->template, '11', '1101', '110105'));
        $this->assertTrue($this->repository->isLocationDeliverableByName($this->template, '北京', '北京市', '朝阳区'));
    }

    private function createArea(
        string $provinceCode,
        string $provinceName,
        ?string $cityCode = null,
        ?string $cityName = null,
        ?string $areaCode = null,
        ?string $areaName = null,
        bool $isDeliverable = true,
    ): ShippingTemplateArea {
        $area = new ShippingTemplateArea();
        $area->setShippingTemplate($this->template);
        $area->setProvinceCode($provinceCode);
        $area->setProvinceName($provinceName);
        $area->setCityCode($cityCode);
        $area->setCityName($cityName);
        $area->setAreaCode($areaCode);
        $area->setAreaName($areaName);
        $area->setIsDeliverable($isDeliverable);

        return $area;
    }

    protected function createNewEntity(): object
    {
        // 创建测试模板（如果不存在）
        if (!isset($this->template)) {
            $this->template = new ShippingTemplate();
            $this->template->setName('测试模板_' . uniqid());
            $this->template->setChargeType(ChargeType::WEIGHT);
            $this->template->setStatus(ShippingTemplateStatus::ACTIVE);
            $this->persistAndFlush($this->template);
        }

        $entity = new ShippingTemplateArea();
        $entity->setShippingTemplate($this->template);
        $entity->setProvinceCode(sprintf('%02d', mt_rand(10, 99)));
        $entity->setProvinceName('测试省份_' . uniqid());
        $entity->setCityCode(sprintf('%04d', mt_rand(1000, 9999)));
        $entity->setCityName('测试城市_' . uniqid());
        $entity->setAreaCode(sprintf('%06d', mt_rand(100000, 999999)));
        $entity->setAreaName('测试区域_' . uniqid());
        $entity->setIsDeliverable(true);
        $entity->setFirstUnit('1.0');
        $entity->setFirstUnitFee('10.00');
        $entity->setAdditionalUnit('0.5');
        $entity->setAdditionalUnitFee('5.00');

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<ShippingTemplateArea>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
