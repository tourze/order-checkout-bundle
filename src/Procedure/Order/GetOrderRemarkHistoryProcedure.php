<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Order;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderRemarkHistoryParam;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '获取订单备注修改历史记录')]
#[MethodExpose(method: 'GetOrderRemarkHistory')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
final class GetOrderRemarkHistoryProcedure extends BaseProcedure
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRemarkService $orderRemarkService,
    ) {
    }

    /**
     * @phpstan-param GetOrderRemarkHistoryParam $param
     */
    public function execute(GetOrderRemarkHistoryParam|RpcParamInterface $param): ArrayResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        $history = $this->orderRemarkService->getOrderRemarkHistory($param->orderId);

        return new ArrayResult([
            '__message' => '获取订单备注历史成功',
            'orderId' => $param->orderId,
            'history' => $history,
            'total' => count($history),
        ]);
    }
}
