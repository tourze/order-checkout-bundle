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
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\JsonRPCLogBundle\Attribute\Log;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\OrderCheckoutBundle\Param\Order\SaveOrderRemarkParam;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

#[MethodTag(name: '订单管理')]
#[MethodDoc(description: '保存订单备注信息，支持emoji表情和敏感词过滤')]
#[MethodExpose(method: 'SaveOrderRemark')]
#[IsGranted(attribute: 'ROLE_USER')]
#[Log]
final class SaveOrderRemarkProcedure extends LockableProcedure
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRemarkService $orderRemarkService,
    ) {
    }

    /**
     * @phpstan-param SaveOrderRemarkParam $param
     */
    public function execute(SaveOrderRemarkParam|RpcParamInterface $param): ArrayResult
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        $input = new SaveOrderRemarkInput($param->orderId, $param->remark);

        try {
            $result = $this->orderRemarkService->saveOrderRemark($input, (int) $user->getUserIdentifier());

            return new ArrayResult([
                '__message' => '订单备注保存成功',
                'orderId' => $result->orderId,
                'remark' => $result->remark,
                'filteredRemark' => $result->filteredRemark,
                'hasFilteredContent' => $result->hasFilteredContent,
                'savedAt' => $result->savedAt->format('Y-m-d H:i:s'),
            ]);
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

        $orderId = $params->get('orderId');
        return [sprintf('order_remark:%d:%s', $orderId, $user->getUserIdentifier())];
    }
}
