<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class SaveOrderRemarkResult
{
    public function __construct(
        public int $orderId,
        public string $remark,
        public string $filteredRemark,
        public bool $hasFilteredContent,
        public \DateTimeInterface $savedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'remark' => $this->remark,
            'filteredRemark' => $this->filteredRemark,
            'hasFilteredContent' => $this->hasFilteredContent,
            'savedAt' => $this->savedAt->format('Y-m-d H:i:s'),
        ];
    }
}
