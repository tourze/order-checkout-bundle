<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateRepository::class)]
#[RunTestsInSeparateProcesses]
final class ShippingTemplateRepositoryTest extends AbstractRepositoryTestCase
{
    private ShippingTemplateRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(ShippingTemplateRepository::class);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShippingTemplateRepository::class, $this->repository);
    }

    public function testFindDefault(): void
    {
        // 创建非默认模板
        $template1 = new ShippingTemplate();
        $template1->setName('非默认模板');
        $template1->setChargeType(ChargeType::WEIGHT);
        $template1->setStatus(ShippingTemplateStatus::ACTIVE);
        $template1->setIsDefault(false);

        // 创建默认模板
        $defaultTemplate = new ShippingTemplate();
        $defaultTemplate->setName('默认模板');
        $defaultTemplate->setChargeType(ChargeType::QUANTITY);
        $defaultTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $defaultTemplate->setIsDefault(true);

        // 创建禁用的默认模板，应该不被查询到
        $inactiveDefault = new ShippingTemplate();
        $inactiveDefault->setName('禁用的默认模板');
        $inactiveDefault->setChargeType(ChargeType::VOLUME);
        $inactiveDefault->setStatus(ShippingTemplateStatus::INACTIVE);
        $inactiveDefault->setIsDefault(true);

        $this->persistAndFlush($template1);
        $this->persistAndFlush($defaultTemplate);
        $this->persistAndFlush($inactiveDefault);

        // 执行查询
        $result = $this->repository->findDefault();

        // 验证结果
        $this->assertInstanceOf(ShippingTemplate::class, $result);
        $this->assertSame('默认运费模板', $result->getName());
        $this->assertTrue($result->isDefault());
        $this->assertTrue($result->isActive());
    }

    public function testFindDefaultReturnsNullWhenNoDefaultExists(): void
    {
        // 先清理所有默认模板（DataFixtures 可能已创建）
        $existingDefaults = $this->repository->findBy(['isDefault' => true]);
        foreach ($existingDefaults as $template) {
            $template->setIsDefault(false);
            $this->persistAndFlush($template);
        }

        // 创建非默认模板
        $template = new ShippingTemplate();
        $template->setName('普通模板');
        $template->setChargeType(ChargeType::WEIGHT);
        $template->setStatus(ShippingTemplateStatus::ACTIVE);
        $template->setIsDefault(false);

        $this->persistAndFlush($template);

        // 执行查询
        $result = $this->repository->findDefault();

        // 验证结果
        $this->assertNull($result);
    }

    public function testFindActiveTemplates(): void
    {
        // 获取 DataFixtures 创建的活跃模板数量
        $existingActiveCount = count($this->repository->findActiveTemplates());

        // 创建各种状态的模板
        $activeDefault = new ShippingTemplate();
        $activeDefault->setName('激活的默认模板');
        $activeDefault->setChargeType(ChargeType::WEIGHT);
        $activeDefault->setStatus(ShippingTemplateStatus::ACTIVE);
        $activeDefault->setIsDefault(true);

        $activeNormal = new ShippingTemplate();
        $activeNormal->setName('激活的普通模板');
        $activeNormal->setChargeType(ChargeType::QUANTITY);
        $activeNormal->setStatus(ShippingTemplateStatus::ACTIVE);
        $activeNormal->setIsDefault(false);

        $inactiveTemplate = new ShippingTemplate();
        $inactiveTemplate->setName('禁用的模板');
        $inactiveTemplate->setChargeType(ChargeType::VOLUME);
        $inactiveTemplate->setStatus(ShippingTemplateStatus::INACTIVE);
        $inactiveTemplate->setIsDefault(false);

        $this->persistAndFlush($activeDefault);
        $this->persistAndFlush($activeNormal);
        $this->persistAndFlush($inactiveTemplate);

        // 执行查询
        $results = $this->repository->findActiveTemplates();

        // 验证结果 - 期望的是现有数量 + 新增的2个活跃模板
        $this->assertCount($existingActiveCount + 2, $results);
        // Verify all results are ShippingTemplate instances
        foreach ($results as $result) {
            $this->assertInstanceOf(ShippingTemplate::class, $result);
        }

        // 验证包含我们创建的模板
        $templateNames = array_map(fn ($template) => $template->getName(), $results);
        $this->assertContains('激活的默认模板', $templateNames);
        $this->assertContains('激活的普通模板', $templateNames);

        // 验证我们创建的模板的属性
        $createdDefault = null;
        $createdNormal = null;
        foreach ($results as $result) {
            if ('激活的默认模板' === $result->getName()) {
                $createdDefault = $result;
            } elseif ('激活的普通模板' === $result->getName()) {
                $createdNormal = $result;
            }
        }

        $this->assertNotNull($createdDefault);
        $this->assertTrue($createdDefault->isDefault());
        $this->assertNotNull($createdNormal);
        $this->assertFalse($createdNormal->isDefault());

        // 验证所有结果都是激活状态
        foreach ($results as $result) {
            $this->assertTrue($result->isActive());
        }
    }

    public function testFindActiveTemplatesReturnsEmptyArrayWhenNoActiveTemplates(): void
    {
        // 先禁用所有活跃模板（DataFixtures 可能已创建）
        $existingActiveTemplates = $this->repository->findActiveTemplates();
        foreach ($existingActiveTemplates as $template) {
            $template->setStatus(ShippingTemplateStatus::INACTIVE);
            $this->persistAndFlush($template);
        }

        // 创建禁用模板
        $inactiveTemplate = new ShippingTemplate();
        $inactiveTemplate->setName('禁用的模板');
        $inactiveTemplate->setChargeType(ChargeType::WEIGHT);
        $inactiveTemplate->setStatus(ShippingTemplateStatus::INACTIVE);
        $inactiveTemplate->setIsDefault(false);

        $this->persistAndFlush($inactiveTemplate);

        // 执行查询
        $results = $this->repository->findActiveTemplates();

        // 验证结果
        $this->assertEmpty($results);
    }

    public function testFindByChargeType(): void
    {
        // 获取现有的按重量计费模板数量
        $existingWeightCount = count($this->repository->findByChargeType(ChargeType::WEIGHT));

        // 创建不同计费类型的模板
        $weightTemplate1 = new ShippingTemplate();
        $weightTemplate1->setName('按重量计费1');
        $weightTemplate1->setChargeType(ChargeType::WEIGHT);
        $weightTemplate1->setStatus(ShippingTemplateStatus::ACTIVE);
        $weightTemplate1->setIsDefault(true);

        $weightTemplate2 = new ShippingTemplate();
        $weightTemplate2->setName('按重量计费2');
        $weightTemplate2->setChargeType(ChargeType::WEIGHT);
        $weightTemplate2->setStatus(ShippingTemplateStatus::ACTIVE);
        $weightTemplate2->setIsDefault(false);

        $quantityTemplate = new ShippingTemplate();
        $quantityTemplate->setName('按件数计费');
        $quantityTemplate->setChargeType(ChargeType::QUANTITY);
        $quantityTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $quantityTemplate->setIsDefault(false);

        $volumeTemplate = new ShippingTemplate();
        $volumeTemplate->setName('按体积计费');
        $volumeTemplate->setChargeType(ChargeType::VOLUME);
        $volumeTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $volumeTemplate->setIsDefault(false);

        // 创建禁用的重量计费模板，应该不被查询到
        $inactiveWeight = new ShippingTemplate();
        $inactiveWeight->setName('禁用的重量计费');
        $inactiveWeight->setChargeType(ChargeType::WEIGHT);
        $inactiveWeight->setStatus(ShippingTemplateStatus::INACTIVE);
        $inactiveWeight->setIsDefault(false);

        $this->persistAndFlush($weightTemplate1);
        $this->persistAndFlush($weightTemplate2);
        $this->persistAndFlush($quantityTemplate);
        $this->persistAndFlush($volumeTemplate);
        $this->persistAndFlush($inactiveWeight);

        // 测试查询按重量计费的模板
        $weightResults = $this->repository->findByChargeType(ChargeType::WEIGHT);

        // 验证结果数量 - 期望的是现有数量 + 新增的2个按重量计费的活跃模板
        $this->assertCount($existingWeightCount + 2, $weightResults);
        // Verify all results are ShippingTemplate instances
        foreach ($weightResults as $result) {
            $this->assertInstanceOf(ShippingTemplate::class, $result);
        }

        // 验证包含我们创建的模板
        $weightTemplateNames = array_map(fn ($template) => $template->getName(), $weightResults);
        $this->assertContains('按重量计费1', $weightTemplateNames);
        $this->assertContains('按重量计费2', $weightTemplateNames);

        // 验证我们创建的模板的属性
        $createdWeightDefault = null;
        $createdWeightNormal = null;
        foreach ($weightResults as $result) {
            if ('按重量计费1' === $result->getName()) {
                $createdWeightDefault = $result;
            } elseif ('按重量计费2' === $result->getName()) {
                $createdWeightNormal = $result;
            }
        }

        $this->assertNotNull($createdWeightDefault);
        $this->assertTrue($createdWeightDefault->isDefault());
        $this->assertNotNull($createdWeightNormal);
        $this->assertFalse($createdWeightNormal->isDefault());

        // 验证所有结果都是指定计费类型和激活状态
        foreach ($weightResults as $result) {
            $this->assertSame(ChargeType::WEIGHT, $result->getChargeType());
            $this->assertTrue($result->isActive());
        }

        // 测试查询按件数计费的模板
        $quantityResults = $this->repository->findByChargeType(ChargeType::QUANTITY);
        $this->assertGreaterThanOrEqual(1, count($quantityResults));

        // 验证包含我们创建的按件数计费模板
        $quantityNames = array_map(fn ($template) => $template->getName(), $quantityResults);
        $this->assertContains('按件数计费', $quantityNames);

        // 测试查询按体积计费的模板
        $volumeResults = $this->repository->findByChargeType(ChargeType::VOLUME);

        // 验证包含我们创建的按体积计费模板
        $volumeNames = array_map(fn ($template) => $template->getName(), $volumeResults);
        $this->assertContains('按体积计费', $volumeNames);
    }

    public function testSaveAll(): void
    {
        $template1 = new ShippingTemplate();
        $template1->setName('模板1');
        $template1->setChargeType(ChargeType::WEIGHT);
        $template1->setStatus(ShippingTemplateStatus::ACTIVE);

        $template2 = new ShippingTemplate();
        $template2->setName('模板2');
        $template2->setChargeType(ChargeType::QUANTITY);
        $template2->setStatus(ShippingTemplateStatus::ACTIVE);

        $template3 = new ShippingTemplate();
        $template3->setName('模板3');
        $template3->setChargeType(ChargeType::VOLUME);
        $template3->setStatus(ShippingTemplateStatus::ACTIVE);

        $entities = [$template1, $template2, $template3];

        // 批量保存
        $this->repository->saveAll($entities, true);

        // 验证都已保存
        foreach ($entities as $entity) {
            $this->assertEntityPersisted($entity);
        }
    }

    public function testFlush(): void
    {
        $template = new ShippingTemplate();
        $template->setName('测试模板');
        $template->setChargeType(ChargeType::WEIGHT);
        $template->setStatus(ShippingTemplateStatus::ACTIVE);

        // 先persist但不flush
        $em = self::getEntityManager();
        $em->persist($template);

        // 确保实体在EntityManager中但未flush
        $this->assertTrue($em->contains($template));

        // 使用repository的flush方法
        $this->repository->flush();

        // 验证实体已被保存
        $this->assertEntityPersisted($template);
    }

    public function testClear(): void
    {
        $template = new ShippingTemplate();
        $template->setName('测试模板');
        $template->setChargeType(ChargeType::WEIGHT);
        $template->setStatus(ShippingTemplateStatus::ACTIVE);

        // 先保存实体
        $em = self::getEntityManager();
        $em->persist($template);
        $em->flush();

        // 确保实体在EntityManager中
        $this->assertTrue($em->contains($template));

        // 使用repository的clear方法
        $this->repository->clear();

        // 验证EntityManager被清空
        $this->assertFalse($em->contains($template));
    }

    public function testCompleteTemplateManagementWorkflow(): void
    {
        // 1. 创建默认模板
        $defaultTemplate = new ShippingTemplate();
        $defaultTemplate->setName('默认物流模板');
        $defaultTemplate->setChargeType(ChargeType::WEIGHT);
        $defaultTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $defaultTemplate->setIsDefault(true);
        $defaultTemplate->setFreeShippingThreshold('99.00');
        $defaultTemplate->setFirstUnit('1.0');
        $defaultTemplate->setFirstUnitFee('10.00');
        $defaultTemplate->setAdditionalUnit('0.5');
        $defaultTemplate->setAdditionalUnitFee('5.00');

        $this->repository->save($defaultTemplate, true);

        // 2. 验证默认模板查询
        $foundDefault = $this->repository->findDefault();
        $this->assertNotNull($foundDefault);
        // 由于 DataFixtures 已创建默认模板，可能返回的是 DataFixtures 的模板
        $this->assertTrue(in_array($foundDefault->getName(), ['默认物流模板', '默认运费模板'], true));
        $this->assertTrue($foundDefault->isDefault());

        // 3. 创建更多模板
        $weightTemplate = new ShippingTemplate();
        $weightTemplate->setName('按重量计费模板');
        $weightTemplate->setChargeType(ChargeType::WEIGHT);
        $weightTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $weightTemplate->setIsDefault(false);

        $quantityTemplate = new ShippingTemplate();
        $quantityTemplate->setName('按件数计费模板');
        $quantityTemplate->setChargeType(ChargeType::QUANTITY);
        $quantityTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $quantityTemplate->setIsDefault(false);

        $inactiveTemplate = new ShippingTemplate();
        $inactiveTemplate->setName('禁用模板');
        $inactiveTemplate->setChargeType(ChargeType::VOLUME);
        $inactiveTemplate->setStatus(ShippingTemplateStatus::INACTIVE);
        $inactiveTemplate->setIsDefault(false);

        $this->repository->saveAll([$weightTemplate, $quantityTemplate, $inactiveTemplate], true);

        // 4. 验证活跃模板查询
        $activeTemplates = $this->repository->findActiveTemplates();
        // 验证包含我们创建的活跃模板
        $activeNames = array_map(fn ($template) => $template->getName(), $activeTemplates);
        $this->assertContains('按重量计费模板', $activeNames);
        $this->assertContains('按件数计费模板', $activeNames);

        // 5. 验证按类型查询
        $weightTemplates = $this->repository->findByChargeType(ChargeType::WEIGHT);
        $weightNames = array_map(fn ($template) => $template->getName(), $weightTemplates);
        $this->assertContains('按重量计费模板', $weightNames);

        $quantityTemplates = $this->repository->findByChargeType(ChargeType::QUANTITY);
        $quantityNames = array_map(fn ($template) => $template->getName(), $quantityTemplates);
        $this->assertContains('按件数计费模板', $quantityNames);

        // 6. 测试模板状态切换
        $defaultTemplate->setStatus(ShippingTemplateStatus::INACTIVE);
        $this->repository->save($defaultTemplate, true);

        // 验证禁用后的查询结果
        $newDefault = $this->repository->findDefault();
        // 如果还有其他默认模板（如 DataFixtures 的），仍然可能返回默认模板
        // 只验证如果有默认模板，它必须是活跃状态
        if (null !== $newDefault) {
            $this->assertTrue($newDefault->isActive());
            $this->assertTrue($newDefault->isDefault());
        }

        $activeTemplatesAfterDisable = $this->repository->findActiveTemplates();
        // 验证我们创建的活跃模板还在
        $activeNamesAfterDisable = array_map(fn ($template) => $template->getName(), $activeTemplatesAfterDisable);
        $this->assertContains('按重量计费模板', $activeNamesAfterDisable);
        $this->assertContains('按件数计费模板', $activeNamesAfterDisable);
        // 我们禁用的默认物流模板应该不在活跃列表中
        $this->assertNotContains('默认物流模板', $activeNamesAfterDisable);

        // 7. 验证最终状态
        // 经过前面的操作，我们已经验证了主要功能：
        // - 创建默认模板
        // - 查询活跃模板
        // - 按类型查询
        // - 状态切换
        // 删除功能在单独的测试中验证，这里只确认核心工作流通过
        $this->assertTrue(true, 'Template management workflow completed successfully');
    }

    protected function createNewEntity(): object
    {
        $entity = new ShippingTemplate();
        $entity->setName('测试模板_' . uniqid());
        $entity->setChargeType(ChargeType::WEIGHT);
        $entity->setStatus(ShippingTemplateStatus::ACTIVE);
        $entity->setIsDefault(false);
        $entity->setFreeShippingThreshold('100.00');
        $entity->setFirstUnit('1.0');
        $entity->setFirstUnitFee('8.00');
        $entity->setAdditionalUnit('0.5');
        $entity->setAdditionalUnitFee('4.00');

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<ShippingTemplate>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
