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
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderDetailParam;

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '获取订单详情')]
#[MethodExpose(method: 'GetOrderDetail')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
final class GetOrderDetailProcedure extends BaseProcedure
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @phpstan-param GetOrderDetailParam $param
     */
    public function execute(GetOrderDetailParam|RpcParamInterface $param): ArrayResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // TODO: 实现实际的订单详情获取逻辑
        // 这里暂时返回 mock 数据，实际实现需要注入订单服务并查询数据库
        // 使用 $param->orderId 访问订单ID

        return new ArrayResult([
            'orderId' => $param->orderId,
            'status' => 'pending',
            'totalAmount' => 100.00,
        ]);
    }
}
