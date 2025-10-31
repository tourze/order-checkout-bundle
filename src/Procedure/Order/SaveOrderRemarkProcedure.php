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
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '保存订单备注信息，支持emoji表情和敏感词过滤')]
#[MethodExpose(method: 'SaveOrderRemark')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
class SaveOrderRemarkProcedure extends LockableProcedure
{
    #[MethodParam(description: '订单ID')]
    #[Assert\NotBlank(message: '订单ID不能为空')]
    #[Assert\Positive(message: '订单ID必须为正整数')]
    public int $orderId;

    #[MethodParam(description: '备注内容，最多200个字符，支持emoji表情')]
    #[Assert\NotBlank(message: '备注内容不能为空')]
    #[Assert\Length(
        max: 200,
        maxMessage: '备注内容不能超过200个字符'
    )]
    public string $remark;

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

        $input = new SaveOrderRemarkInput($this->orderId, $this->remark);

        try {
            $result = $this->orderRemarkService->saveOrderRemark($input, (int) $user->getUserIdentifier());

            return [
                '__message' => '订单备注保存成功',
                'orderId' => $result->orderId,
                'remark' => $result->remark,
                'filteredRemark' => $result->filteredRemark,
                'hasFilteredContent' => $result->hasFilteredContent,
                'savedAt' => $result->savedAt->format('Y-m-d H:i:s'),
            ];
        } catch (OrderException $e) {
            throw new ApiException($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    public function getLockResource(JsonRpcParams $params): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        return [sprintf('order_remark:%d:%s', $this->orderId, $user->getUserIdentifier())];
    }

    public static function getMockResult(): ?array
    {
        return [
            '__message' => '订单备注保存成功',
            'orderId' => 12345,
            'remark' => '请尽快发货，谢谢！😊',
            'filteredRemark' => '请尽快发货，谢谢！😊',
            'hasFilteredContent' => false,
            'savedAt' => '2024-01-01 12:00:00',
        ];
    }
}
