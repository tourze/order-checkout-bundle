<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Event\OrderPaidEvent;
use OrderCoreBundle\Repository\ContractRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;
use Tourze\OrderCheckoutBundle\Repository\PayOrderRepository;
use Tourze\PaymentContracts\Event\PaymentFailedEvent;
use Tourze\PaymentContracts\Event\PaymentSuccessEvent;

/**
 * 支付状态订阅器
 *
 * 监听通用支付事件，更新订单状态，实现支付模块与订单模块的解耦
 */
#[WithMonologChannel(channel: 'order_payment')]
readonly final class PaymentSubscriber
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContractRepository $contractRepository,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
        private PayOrderRepository $payOrderRepository,
    ) {
    }

    /**
     * 处理支付成功事件
     */
    #[AsEventListener]
    public function onPaymentSuccess(PaymentSuccessEvent $event): void
    {
        $orderId = $event->getOrderId();
        $orderNumber = $event->getOrderNumber();
        $transactionId = $event->getTransactionId();
        $amount = $event->getAmount();
        $paymentType = $event->getPaymentType();

        $this->logger->info('收到支付成功事件', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'payment_type' => $paymentType->value,
        ]);

        try {
            // 查找订单
            $contract = $this->findContract($orderId, $orderNumber);
            if (null === $contract) {
                $this->logger->error('支付成功但未找到对应订单', [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                ]);

                return;
            }

            // 检查订单状态是否允许更新为已支付
            if (!$this->canMarkAsPaid($contract)) {
                $this->logger->warning('订单状态不允许标记为已支付', [
                    'order_id' => $orderId,
                    'current_state' => $contract->getState()->value,
                ]);

                return;
            }

            // 防止重复处理
            if (OrderState::PAID === $contract->getState()) {
                $this->logger->info('订单已经是已支付状态，跳过处理', [
                    'order_id' => $orderId,
                ]);

                return;
            }

            $this->entityManager->wrapInTransaction(function () use ($contract, $event, $orderId, $orderNumber, $transactionId, $amount, $paymentType) {
                $contract->setState(OrderState::PAID);
                $contract->setPayTime($event->getPayTime());
                $this->contractRepository->save($contract);
                $payOrder = new PayOrder();
                $payOrder->setPayTime($event->getPayTime());
                $payOrder->setAmount((string) $event->getAmount());
                $payOrder->setTradeNo($event->getTransactionId());
                $payOrder->setContract($contract);

                $this->payOrderRepository->save($payOrder);

                $this->logger->info('订单状态更新为已支付', [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'transaction_id' => $transactionId,
                    'payment_amount' => $amount,
                    'payment_type' => $paymentType->getLabel(),
                ]);

                try {
                    $orderPaidEvent = new OrderPaidEvent();
                    $orderPaidEvent->setContract($contract);
                    $this->eventDispatcher->dispatch($orderPaidEvent);
                    $this->logger->info('已分发订单支付成功事件', [
                        'order_id' => $orderId,
                        'event' => OrderPaidEvent::class,
                    ]);
                } catch (\Exception $exception) {
                    $this->logger->error('处理支付成功事件时OrderPaidEvent', [
                        'order_id' => $orderId,
                        'event' => OrderPaidEvent::class,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            $this->logger->error('处理支付成功事件时发生错误', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 处理支付失败事件
     */
    #[AsEventListener]
    public function onPaymentFailed(PaymentFailedEvent $event): void
    {
        $orderId = $event->getOrderId();
        $orderNumber = $event->getOrderNumber();
        $failReason = $event->getFailReason();
        $paymentType = $event->getPaymentType();

        $this->logger->info('收到支付失败事件', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'fail_reason' => $failReason,
            'payment_type' => $paymentType->value,
        ]);

        try {
            // 查找订单
            $contract = $this->findContract($orderId, $orderNumber);
            if (null === $contract) {
                $this->logger->error('支付失败但未找到对应订单', [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                ]);

                return;
            }

            // 只有支付中状态的订单才处理失败
            if (OrderState::PAYING !== $contract->getState()) {
                $this->logger->info('订单不是支付中状态，跳过失败处理', [
                    'order_id' => $orderId,
                    'current_state' => $contract->getState()->value,
                ]);

                return;
            }

            // 更新订单状态回到初始状态
            $this->entityManager->beginTransaction();
            try {
                $contract->setState(OrderState::INIT);
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->info('订单状态从支付中恢复为初始状态', [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'fail_reason' => $failReason,
                    'payment_type' => $paymentType->getLabel(),
                ]);
            } catch (\Throwable $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->logger->error('处理支付失败事件时发生错误', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 查找订单
     */
    private function findContract(int $orderId, string $orderNumber): ?Contract
    {
        // 优先通过订单ID查找
        $contract = $this->contractRepository->find($orderId);
        if (null !== $contract) {
            return $contract;
        }

        // 如果订单号不为空，尝试通过订单号查找
        if ('' !== $orderNumber) {
            return $this->contractRepository->findOneBy(['sn' => $orderNumber]);
        }

        return null;
    }

    /**
     * 检查订单是否可以标记为已支付
     */
    private function canMarkAsPaid(Contract $contract): bool
    {
        return match ($contract->getState()) {
            OrderState::INIT,
            OrderState::PAYING => true,
            default => false,
        };
    }
}
