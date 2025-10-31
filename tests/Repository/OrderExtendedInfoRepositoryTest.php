<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\OrderCheckoutBundle\Repository\OrderExtendedInfoRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(OrderExtendedInfoRepository::class)]
#[RunTestsInSeparateProcesses]
final class OrderExtendedInfoRepositoryTest extends AbstractRepositoryTestCase
{
    private OrderExtendedInfoRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(OrderExtendedInfoRepository::class);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(OrderExtendedInfoRepository::class, $this->repository);
    }

    public function testFindByOrderIdAndType(): void
    {
        $orderId = 123;
        $infoType = 'remark';

        // 创建测试数据
        $info1 = new OrderExtendedInfo();
        $info1->setOrderId($orderId);
        $info1->setInfoType($infoType);
        $info1->setInfoKey('customer_remark');
        $info1->setInfoValue('第一个备注');
        $info1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $info2 = new OrderExtendedInfo();
        $info2->setOrderId($orderId);
        $info2->setInfoType($infoType);
        $info2->setInfoKey('seller_remark');
        $info2->setInfoValue('第二个备注');
        $info2->setCreateTime(new \DateTimeImmutable('2024-01-02 10:00:00'));

        // 创建其他订单的备注，应该不被查询到
        $info3 = new OrderExtendedInfo();
        $info3->setOrderId(456);
        $info3->setInfoType($infoType);
        $info3->setInfoKey('customer_remark');
        $info3->setInfoValue('其他订单备注');

        // 创建不同类型的信息，应该不被查询到
        $info4 = new OrderExtendedInfo();
        $info4->setOrderId($orderId);
        $info4->setInfoType('other_type');
        $info4->setInfoKey('other_key');
        $info4->setInfoValue('其他类型信息');

        $this->persistAndFlush($info1);
        $this->persistAndFlush($info2);
        $this->persistAndFlush($info3);
        $this->persistAndFlush($info4);

        // 执行查询
        $results = $this->repository->findByOrderIdAndType($orderId, $infoType);

        // 验证结果
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(OrderExtendedInfo::class, $results);

        // 验证排序（按创建时间倒序）
        $this->assertSame('seller_remark', $results[0]->getInfoKey());
        $this->assertSame('customer_remark', $results[1]->getInfoKey());

        // 验证内容
        foreach ($results as $result) {
            $this->assertSame($orderId, $result->getOrderId());
            $this->assertSame($infoType, $result->getInfoType());
        }
    }

    public function testFindByOrderIdAndTypeReturnsEmptyArrayWhenNoResults(): void
    {
        $orderId = 999;
        $infoType = 'nonexistent';

        $results = $this->repository->findByOrderIdAndType($orderId, $infoType);

        $this->assertEmpty($results);
    }

    public function testFindLatestByOrderIdAndKey(): void
    {
        $orderId = 123;
        $infoType = 'remark';
        $infoKey = 'customer_remark';

        // 创建多个相同key的记录
        $info1 = new OrderExtendedInfo();
        $info1->setOrderId($orderId);
        $info1->setInfoType($infoType);
        $info1->setInfoKey($infoKey);
        $info1->setInfoValue('旧备注');
        $info1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $this->persistAndFlush($info1);

        $info2 = new OrderExtendedInfo();
        $info2->setOrderId($orderId);
        $info2->setInfoType($infoType);
        $info2->setInfoKey($infoKey);
        $info2->setInfoValue('新备注');
        $info2->setCreateTime(new \DateTimeImmutable('2024-01-02 10:00:00'));

        $this->persistAndFlush($info2);

        // 创建不同key的记录，不应该被查询到
        $info3 = new OrderExtendedInfo();
        $info3->setOrderId($orderId);
        $info3->setInfoType($infoType);
        $info3->setInfoKey('seller_remark');
        $info3->setInfoValue('卖家备注');

        $this->persistAndFlush($info3);

        // 执行查询
        $result = $this->repository->findLatestByOrderIdAndKey($orderId, $infoType, $infoKey);

        // 验证结果
        $this->assertInstanceOf(OrderExtendedInfo::class, $result);
        $this->assertSame($orderId, $result->getOrderId());
        $this->assertSame($infoType, $result->getInfoType());
        $this->assertSame($infoKey, $result->getInfoKey());
        $this->assertSame('新备注', $result->getInfoValue());
    }

    public function testFindLatestByOrderIdAndKeyReturnsNullWhenNoResults(): void
    {
        $orderId = 999;
        $infoType = 'nonexistent';
        $infoKey = 'nonexistent_key';

        $result = $this->repository->findLatestByOrderIdAndKey($orderId, $infoType, $infoKey);

        $this->assertNull($result);
    }

    public function testFindRemarkHistoryByOrderId(): void
    {
        $orderId = 123;

        // 创建客户备注历史
        $remark1 = new OrderExtendedInfo();
        $remark1->setOrderId($orderId);
        $remark1->setInfoType('remark');
        $remark1->setInfoKey('customer_remark');
        $remark1->setInfoValue('第一次备注');
        $remark1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $this->persistAndFlush($remark1);

        $remark2 = new OrderExtendedInfo();
        $remark2->setOrderId($orderId);
        $remark2->setInfoType('remark');
        $remark2->setInfoKey('customer_remark');
        $remark2->setInfoValue('第二次备注');
        $remark2->setCreateTime(new \DateTimeImmutable('2024-01-02 10:00:00'));

        $this->persistAndFlush($remark2);

        // 创建不相关的记录，不应该被查询到
        $otherInfo = new OrderExtendedInfo();
        $otherInfo->setOrderId($orderId);
        $otherInfo->setInfoType('remark');
        $otherInfo->setInfoKey('seller_remark');
        $otherInfo->setInfoValue('卖家备注');

        $this->persistAndFlush($otherInfo);

        $otherOrderInfo = new OrderExtendedInfo();
        $otherOrderInfo->setOrderId(456);
        $otherOrderInfo->setInfoType('remark');
        $otherOrderInfo->setInfoKey('customer_remark');
        $otherOrderInfo->setInfoValue('其他订单备注');

        $this->persistAndFlush($otherOrderInfo);

        // 执行查询
        $results = $this->repository->findRemarkHistoryByOrderId($orderId);

        // 验证结果
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(OrderExtendedInfo::class, $results);

        // 验证排序（按创建时间倒序）
        $this->assertSame('第二次备注', $results[0]->getInfoValue());
        $this->assertSame('第一次备注', $results[1]->getInfoValue());

        // 验证所有结果都是指定订单的客户备注
        foreach ($results as $result) {
            $this->assertSame($orderId, $result->getOrderId());
            $this->assertSame('remark', $result->getInfoType());
            $this->assertSame('customer_remark', $result->getInfoKey());
        }
    }

    public function testFindRemarkHistoryByOrderIdReturnsEmptyArrayWhenNoResults(): void
    {
        $orderId = 999;

        $results = $this->repository->findRemarkHistoryByOrderId($orderId);

        $this->assertEmpty($results);
    }

    public function testCompleteWorkflow(): void
    {
        $orderId = 123;
        $infoType = 'remark';
        $infoKey = 'customer_remark';

        // 1. 创建初始备注
        $initialRemark = new OrderExtendedInfo();
        $initialRemark->setOrderId($orderId);
        $initialRemark->setInfoType($infoType);
        $initialRemark->setInfoKey($infoKey);
        $initialRemark->setInfoValue('初始备注');
        $initialRemark->setOriginalValue('初始备注（原始）');
        $initialRemark->setIsFiltered(false);
        $initialRemark->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $this->repository->save($initialRemark, true);

        // 2. 验证初始备注被保存
        $found = $this->repository->findLatestByOrderIdAndKey($orderId, $infoType, $infoKey);
        $this->assertNotNull($found);
        $this->assertSame('初始备注', $found->getInfoValue());
        $this->assertFalse($found->isFiltered());

        // 3. 创建更新的备注
        $updatedRemark = new OrderExtendedInfo();
        $updatedRemark->setOrderId($orderId);
        $updatedRemark->setInfoType($infoType);
        $updatedRemark->setInfoKey($infoKey);
        $updatedRemark->setInfoValue('更新后的备注');
        $updatedRemark->setOriginalValue('更更新后的备注（包含敏感词）');
        $updatedRemark->setIsFiltered(true);
        $updatedRemark->setFilteredWords(['敏感词']);
        $updatedRemark->setCreateTime(new \DateTimeImmutable('2024-01-02 10:00:00'));

        $this->repository->save($updatedRemark, true);

        // 4. 验证能查询到最新的备注
        $latest = $this->repository->findLatestByOrderIdAndKey($orderId, $infoType, $infoKey);
        $this->assertNotNull($latest);
        $this->assertSame('更新后的备注', $latest->getInfoValue());
        $this->assertTrue($latest->isFiltered());
        $this->assertSame(['敏感词'], $latest->getFilteredWords());

        // 5. 验证历史记录查询
        $history = $this->repository->findRemarkHistoryByOrderId($orderId);
        $this->assertCount(2, $history);
        $this->assertSame('更新后的备注', $history[0]->getInfoValue()); // 最新的在前
        $this->assertSame('初始备注', $history[1]->getInfoValue());

        // 6. 验证按类型查询
        $allRemarks = $this->repository->findByOrderIdAndType($orderId, $infoType);
        $this->assertCount(2, $allRemarks);
    }

    protected function createNewEntity(): object
    {
        $entity = new OrderExtendedInfo();
        $entity->setOrderId(mt_rand(1, 999999));
        $entity->setInfoType('test_type_' . uniqid());
        $entity->setInfoKey('test_key_' . uniqid());
        $entity->setInfoValue('test_value_' . uniqid());
        $entity->setOriginalValue('original_' . uniqid());
        $entity->setIsFiltered(false);
        $entity->setFilteredWords([]);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<OrderExtendedInfo>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    /**
     * 覆盖父类的数据装置测试，创建测试数据以确保计数大于0
     */
    public function testCountWithDataFixturesShouldReturnGreaterThanZero(): void
    {
        // 创建测试数据以确保计数不为0
        $testEntity = $this->createNewEntity();
        $this->persistAndFlush($testEntity);

        $count = $this->repository->count();
        $this->assertGreaterThan(0, $count,
            'OrderExtendedInfo 实体的数据库记录数应该大于0');
    }
}
