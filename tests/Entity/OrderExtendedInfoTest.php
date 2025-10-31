<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(OrderExtendedInfo::class)]
final class OrderExtendedInfoTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new OrderExtendedInfo();
    }

    public function testEntityCanBeInstantiated(): void
    {
        $entity = new OrderExtendedInfo();

        $this->assertInstanceOf(OrderExtendedInfo::class, $entity);
    }

    public function testOrderIdGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $orderId = 12345;

        $entity->setOrderId($orderId);

        $this->assertEquals($orderId, $entity->getOrderId());
    }

    public function testInfoTypeGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $infoType = 'remark';

        $entity->setInfoType($infoType);

        $this->assertEquals($infoType, $entity->getInfoType());
    }

    public function testInfoKeyGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $infoKey = 'customer_remark';

        $entity->setInfoKey($infoKey);

        $this->assertEquals($infoKey, $entity->getInfoKey());
    }

    public function testInfoValueGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $infoValue = '请尽快发货，谢谢！😊';

        $entity->setInfoValue($infoValue);

        $this->assertEquals($infoValue, $entity->getInfoValue());
    }

    public function testOriginalValueGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $originalValue = '原始备注内容';

        $entity->setOriginalValue($originalValue);

        $this->assertEquals($originalValue, $entity->getOriginalValue());
    }

    public function testOriginalValueCanBeNull(): void
    {
        $entity = new OrderExtendedInfo();

        $entity->setOriginalValue(null);

        $this->assertNull($entity->getOriginalValue());
    }

    public function testIsFilteredGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();

        $this->assertFalse($entity->isFiltered());

        $entity->setIsFiltered(true);

        $this->assertTrue($entity->isFiltered());
    }

    public function testFilteredWordsGetterAndSetter(): void
    {
        $entity = new OrderExtendedInfo();
        $filteredWords = ['色情', '暴力'];

        $entity->setFilteredWords($filteredWords);

        $this->assertEquals($filteredWords, $entity->getFilteredWords());
    }

    public function testFilteredWordsCanBeNull(): void
    {
        $entity = new OrderExtendedInfo();

        $entity->setFilteredWords(null);

        $this->assertNull($entity->getFilteredWords());
    }

    public function testCompleteRemarkDataFlow(): void
    {
        $entity = new OrderExtendedInfo();

        $entity->setOrderId(12345);
        $entity->setInfoType('remark');
        $entity->setInfoKey('customer_remark');
        $entity->setInfoValue('请尽快发货');
        $entity->setOriginalValue('请尽快发货，有色情内容');
        $entity->setIsFiltered(true);
        $entity->setFilteredWords(['色情']);

        $this->assertEquals(12345, $entity->getOrderId());
        $this->assertEquals('remark', $entity->getInfoType());
        $this->assertEquals('customer_remark', $entity->getInfoKey());
        $this->assertEquals('请尽快发货', $entity->getInfoValue());
        $this->assertEquals('请尽快发货，有色情内容', $entity->getOriginalValue());
        $this->assertTrue($entity->isFiltered());
        $this->assertEquals(['色情'], $entity->getFilteredWords());
    }

    public function testEntityWithoutFilteringFlow(): void
    {
        $entity = new OrderExtendedInfo();

        $entity->setOrderId(54321);
        $entity->setInfoType('remark');
        $entity->setInfoKey('customer_remark');
        $entity->setInfoValue('正常的备注内容😊');
        $entity->setOriginalValue(null);
        $entity->setIsFiltered(false);
        $entity->setFilteredWords(null);

        $this->assertEquals(54321, $entity->getOrderId());
        $this->assertEquals('remark', $entity->getInfoType());
        $this->assertEquals('customer_remark', $entity->getInfoKey());
        $this->assertEquals('正常的备注内容😊', $entity->getInfoValue());
        $this->assertNull($entity->getOriginalValue());
        $this->assertFalse($entity->isFiltered());
        $this->assertNull($entity->getFilteredWords());
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield ['orderId', 12345];
        yield ['infoType', 'remark'];
        yield ['infoKey', 'customer_remark'];
        yield ['infoValue', '请尽快发货，谢谢！😊'];
        yield ['originalValue', '原始备注内容'];
        yield ['filteredWords', ['色情', '暴力']];
        // Note: isFiltered 属性跳过自动测试，因为方法命名模式与 AbstractEntityTestCase 不兼容
        // 该属性已在专用测试方法中充分测试
    }
}
