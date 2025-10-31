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

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '获取订单详情')]
#[MethodExpose(method: 'GetOrderDetail')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
class GetOrderDetailProcedure extends BaseProcedure
{
    #[MethodParam(description: '订单ID')]
    #[Assert\NotBlank(message: '订单ID不能为空')]
    #[Assert\Positive(message: '订单ID必须为正整数')]
    public int $orderId;

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // TODO: 实现实际的订单详情获取逻辑
        // 这里暂时返回 mock 数据，实际实现需要注入订单服务并查询数据库

        return self::getMockResult();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMockResult(): array
    {
        return [
            '__message' => '获取订单详情成功',
            'orderId' => 12345,
            'orderNumber' => 'ORD20240101123456',
            'status' => 'paid',
            'totalAmount' => 199.90,
            'createTime' => '2024-01-01 12:00:00',
            'paymentMethod' => 'alipay',
            'shippingAddress' => [
                'name' => '张三',
                'phone' => '13800138000',
                'address' => '北京市朝阳区某某街道123号',
            ],
            'items' => [
                [
                    'skuId' => 1001,
                    'productName' => '商品A',
                    'quantity' => 2,
                    'price' => 99.95,
                    'totalPrice' => 199.90,
                ],
            ],
            'customerRemark' => '请尽快发货，谢谢！',
            'hasRemark' => true,
        ];
    }
}
