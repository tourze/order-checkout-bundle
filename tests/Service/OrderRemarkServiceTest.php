<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\FilterResult;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkResult;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\OrderCheckoutBundle\Repository\OrderExtendedInfoRepository;
use Tourze\OrderCheckoutBundle\Service\ContentFilterService;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

/**
 * @internal
 */
#[CoversClass(OrderRemarkService::class)]
final class OrderRemarkServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private OrderExtendedInfoRepository&MockObject $repository;

    private ContentFilterService&MockObject $contentFilterService;

    private OrderRemarkService $orderRemarkService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(OrderExtendedInfoRepository::class);
        $this->contentFilterService = $this->createMock(ContentFilterService::class);

        $this->orderRemarkService = new OrderRemarkService(
            $this->entityManager,
            $this->repository,
            $this->contentFilterService
        );
    }

    public function testSaveOrderRemarkWithCleanContent(): void
    {
        $input = new SaveOrderRemarkInput(12345, '请尽快发货，谢谢！😊');
        $userId = 100;

        $this->contentFilterService
            ->expects($this->once())
            ->method('sanitizeRemark')
            ->with('请尽快发货，谢谢！😊')
            ->willReturn('请尽快发货，谢谢！😊')
        ;

        $filterResult = new FilterResult(
            '请尽快发货，谢谢！😊',
            '请尽快发货，谢谢！😊',
            false,
            []
        );

        $this->contentFilterService
            ->expects($this->once())
            ->method('filterContent')
            ->with('请尽快发货，谢谢！😊')
            ->willReturn($filterResult)
        ;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getSingleScalarResult')->willReturn(1);

        $this->entityManager
            ->expects($this->once())
            ->method('createQuery')
            ->willReturn($query)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with(self::isInstanceOf(OrderExtendedInfo::class), true)
        ;

        $result = $this->orderRemarkService->saveOrderRemark($input, $userId);

        $this->assertInstanceOf(SaveOrderRemarkResult::class, $result);
        $this->assertEquals(12345, $result->orderId);
        $this->assertEquals('请尽快发货，谢谢！😊', $result->remark);
        $this->assertEquals('请尽快发货，谢谢！😊', $result->filteredRemark);
        $this->assertFalse($result->hasFilteredContent);
    }

    public function testSaveOrderRemarkWithFilteredContent(): void
    {
        $input = new SaveOrderRemarkInput(12345, '请尽快发货，有色情内容');
        $userId = 100;

        $this->contentFilterService
            ->expects($this->once())
            ->method('sanitizeRemark')
            ->with('请尽快发货，有色情内容')
            ->willReturn('请尽快发货，有色情内容')
        ;

        $filterResult = new FilterResult(
            '请尽快发货，有色情内容',
            '请尽快发货，有**内容',
            true,
            ['色情']
        );

        $this->contentFilterService
            ->expects($this->once())
            ->method('filterContent')
            ->with('请尽快发货，有色情内容')
            ->willReturn($filterResult)
        ;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getSingleScalarResult')->willReturn(1);

        $this->entityManager
            ->expects($this->once())
            ->method('createQuery')
            ->willReturn($query)
        ;

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with(
                self::callback(function (OrderExtendedInfo $entity): bool {
                    return 12345 === $entity->getOrderId()
                        && 'remark' === $entity->getInfoType()
                        && 'customer_remark' === $entity->getInfoKey()
                        && '请尽快发货，有**内容' === $entity->getInfoValue()
                        && '请尽快发货，有色情内容' === $entity->getOriginalValue()
                        && true === $entity->isFiltered()
                        && $entity->getFilteredWords() === ['色情'];
                }),
                true
            )
        ;

        $result = $this->orderRemarkService->saveOrderRemark($input, $userId);

        $this->assertInstanceOf(SaveOrderRemarkResult::class, $result);
        $this->assertEquals(12345, $result->orderId);
        $this->assertEquals('请尽快发货，有色情内容', $result->remark);
        $this->assertEquals('请尽快发货，有**内容', $result->filteredRemark);
        $this->assertTrue($result->hasFilteredContent);
    }

    public function testSaveOrderRemarkWithNonExistentOrder(): void
    {
        $input = new SaveOrderRemarkInput(99999, '备注内容');
        $userId = 100;

        $this->contentFilterService
            ->expects($this->once())
            ->method('sanitizeRemark')
            ->willReturn('备注内容')
        ;

        $filterResult = new FilterResult('备注内容', '备注内容', false, []);
        $this->contentFilterService
            ->expects($this->once())
            ->method('filterContent')
            ->willReturn($filterResult)
        ;

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getSingleScalarResult')->willReturn(0);

        $this->entityManager
            ->expects($this->once())
            ->method('createQuery')
            ->willReturn($query)
        ;

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('订单 99999 不存在');

        $this->orderRemarkService->saveOrderRemark($input, $userId);
    }

    public function testGetOrderRemarkExists(): void
    {
        $orderId = 12345;
        $remarkInfo = $this->createMock(OrderExtendedInfo::class);
        $remarkInfo->method('getInfoValue')->willReturn('请尽快发货，谢谢！😊');

        $this->repository
            ->expects($this->once())
            ->method('findLatestByOrderIdAndKey')
            ->with($orderId, 'remark', 'customer_remark')
            ->willReturn($remarkInfo)
        ;

        $result = $this->orderRemarkService->getOrderRemark($orderId);

        $this->assertEquals('请尽快发货，谢谢！😊', $result);
    }

    public function testGetOrderRemarkNotExists(): void
    {
        $orderId = 12345;

        $this->repository
            ->expects($this->once())
            ->method('findLatestByOrderIdAndKey')
            ->with($orderId, 'remark', 'customer_remark')
            ->willReturn(null)
        ;

        $result = $this->orderRemarkService->getOrderRemark($orderId);

        $this->assertNull($result);
    }

    public function testGetOrderRemarkHistory(): void
    {
        $orderId = 12345;
        $info1 = $this->createMock(OrderExtendedInfo::class);
        $info2 = $this->createMock(OrderExtendedInfo::class);

        $info1->method('getId')->willReturn('1');
        $info1->method('getInfoValue')->willReturn('最新备注');
        $info1->method('getOriginalValue')->willReturn(null);
        $info1->method('isFiltered')->willReturn(false);
        $info1->method('getFilteredWords')->willReturn(null);
        $info1->method('getCreateTime')->willReturn(new \DateTimeImmutable('2024-01-01 15:30:00'));

        $info1->method('getCreatedBy')->willReturn('100');

        $info2->method('getId')->willReturn('2');
        $info2->method('getInfoValue')->willReturn('旧备注');
        $info2->method('getOriginalValue')->willReturn('旧备注，有敏感词');
        $info2->method('isFiltered')->willReturn(true);
        $info2->method('getFilteredWords')->willReturn(['敏感词']);
        $info2->method('getCreateTime')->willReturn(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $info2->method('getCreatedBy')->willReturn('100');

        $this->repository
            ->expects($this->once())
            ->method('findRemarkHistoryByOrderId')
            ->with($orderId)
            ->willReturn([$info1, $info2])
        ;

        $result = $this->orderRemarkService->getOrderRemarkHistory($orderId);

        $this->assertCount(2, $result);
        $this->assertEquals([
            [
                'id' => '1',
                'remark' => '最新备注',
                'originalRemark' => null,
                'isFiltered' => false,
                'filteredWords' => null,
                'createTime' => '2024-01-01 15:30:00',
                'updateTime' => null,
            ],
            [
                'id' => '2',
                'remark' => '旧备注',
                'originalRemark' => '旧备注，有敏感词',
                'isFiltered' => true,
                'filteredWords' => ['敏感词'],
                'createTime' => '2024-01-01 12:00:00',
                'updateTime' => null,
            ],
        ], $result);
    }

    public function testSaveOrderRemarkWithInvalidContent(): void
    {
        $input = new SaveOrderRemarkInput(12345, 'invalid content');
        $userId = 100;

        $this->contentFilterService
            ->expects($this->once())
            ->method('sanitizeRemark')
            ->with('invalid content')
            ->willThrowException(new \InvalidArgumentException('备注内容包含无效字符'))
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('备注内容包含无效字符');

        $this->orderRemarkService->saveOrderRemark($input, $userId);
    }
}
