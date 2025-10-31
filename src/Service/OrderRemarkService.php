<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\OrderCheckoutBundle\DTO\FilterResult;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkResult;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\OrderCheckoutBundle\Repository\OrderExtendedInfoRepository;

class OrderRemarkService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderExtendedInfoRepository $orderExtendedInfoRepository,
        private readonly ContentFilterService $contentFilterService,
    ) {
    }

    public function saveOrderRemark(SaveOrderRemarkInput $input, int $userId): SaveOrderRemarkResult
    {
        $sanitizedRemark = $this->contentFilterService->sanitizeRemark($input->remark);
        $filterResult = $this->contentFilterService->filterContent($sanitizedRemark);

        $this->validateOrderExists($input->orderId);

        $orderExtendedInfo = new OrderExtendedInfo();
        $orderExtendedInfo->setOrderId($input->orderId);
        $orderExtendedInfo->setInfoType('remark');
        $orderExtendedInfo->setInfoKey('customer_remark');
        $orderExtendedInfo->setInfoValue($filterResult->filteredContent);
        $orderExtendedInfo->setOriginalValue($filterResult->hasFilteredContent ? $filterResult->originalContent : null);
        $orderExtendedInfo->setIsFiltered($filterResult->hasFilteredContent);
        $orderExtendedInfo->setFilteredWords($filterResult->hasFilteredContent ? $filterResult->filteredWords : null);

        $this->orderExtendedInfoRepository->save($orderExtendedInfo, true);

        return new SaveOrderRemarkResult(
            $input->orderId,
            $filterResult->originalContent,
            $filterResult->filteredContent,
            $filterResult->hasFilteredContent,
            $orderExtendedInfo->getCreateTime() ?? new \DateTimeImmutable()
        );
    }

    public function getOrderRemark(int $orderId): ?string
    {
        $remarkInfo = $this->orderExtendedInfoRepository->findLatestByOrderIdAndKey(
            $orderId,
            'remark',
            'customer_remark'
        );

        return $remarkInfo?->getInfoValue();
    }

    /**
     * @return array<array{id: string|null, remark: string, originalRemark: string|null, isFiltered: bool, filteredWords: array<string>|null, createTime: string|null, updateTime: string|null}>
     */
    public function getOrderRemarkHistory(int $orderId): array
    {
        $history = $this->orderExtendedInfoRepository->findRemarkHistoryByOrderId($orderId);

        return array_map(function (OrderExtendedInfo $info): array {
            return [
                'id' => $info->getId(),
                'remark' => $info->getInfoValue(),
                'originalRemark' => $info->getOriginalValue(),
                'isFiltered' => $info->isFiltered(),
                'filteredWords' => $info->getFilteredWords(),
                'createTime' => $info->getCreateTime()?->format('Y-m-d H:i:s'),
                'updateTime' => $info->getUpdateTime()?->format('Y-m-d H:i:s'),
            ];
        }, $history);
    }

    private function validateOrderExists(int $orderId): void
    {
        $query = $this->entityManager->createQuery(
            'SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.id = :orderId'
        );
        $query->setParameter('orderId', $orderId);

        $count = $query->getSingleScalarResult();

        if (0 === $count) {
            throw new OrderException(sprintf('订单 %d 不存在', $orderId));
        }
    }
}
