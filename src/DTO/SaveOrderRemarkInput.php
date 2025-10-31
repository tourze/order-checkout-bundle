<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class SaveOrderRemarkInput
{
    public function __construct(
        #[Assert\NotBlank(message: '订单ID不能为空')]
        #[Assert\Positive(message: '订单ID必须为正整数')]
        public int $orderId,

        #[Assert\NotBlank(message: '备注内容不能为空')]
        #[Assert\Length(
            max: 200,
            maxMessage: '备注内容不能超过200个字符'
        )]
        public string $remark,
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
        ];
    }
}
