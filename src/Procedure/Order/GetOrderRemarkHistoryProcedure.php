<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Order;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '获取订单备注修改历史记录')]
#[MethodExpose(method: 'GetOrderRemarkHistory')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
class GetOrderRemarkHistoryProcedure extends BaseProcedure
{
    #[MethodParam(description: '订单ID')]
    #[Assert\NotBlank(message: '订单ID不能为空')]
    #[Assert\Positive(message: '订单ID必须为正整数')]
    public int $orderId;

    public function __construct(
        private readonly Security $security,
        private readonly OrderRemarkService $orderRemarkService,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        $history = $this->orderRemarkService->getOrderRemarkHistory($this->orderId);

        return [
            '__message' => '获取订单备注历史成功',
            'orderId' => $this->orderId,
            'history' => $history,
            'total' => count($history),
        ];
    }

    public static function getMockResult(): ?array
    {
        return [
            '__message' => '获取订单备注历史成功',
            'orderId' => 12345,
            'history' => [
                [
                    'id' => 1,
                    'remark' => '请尽快发货，谢谢！😊',
                    'originalRemark' => null,
                    'isFiltered' => false,
                    'filteredWords' => null,
                    'createdAt' => '2024-01-01 15:30:00',
                    'createdBy' => 100,
                ],
                [
                    'id' => 2,
                    'remark' => '请尽快发货',
                    'originalRemark' => null,
                    'isFiltered' => false,
                    'filteredWords' => null,
                    'createdAt' => '2024-01-01 12:00:00',
                    'createdBy' => 100,
                ],
            ],
            'total' => 2,
        ];
    }
}
