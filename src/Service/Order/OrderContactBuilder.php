<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Order;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderContact;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\DeliveryAddressBundle\Service\DeliveryAddressService;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;

/**
 * 订单联系人构建器
 * 负责创建和管理订单联系人实体
 */
final class OrderContactBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryAddressService $deliveryAddressService,
    ) {
    }

    public function createOrderContact(Contract $contract, CalculationContext $context): void
    {
        $orderType = $context->getMetadataValue('orderType');
        if ('redeem' === $orderType) {
            return;
        }

        $address = $this->getValidatedDeliveryAddress($contract, $context);
        $this->createContactFromAddress($contract, $address);
    }

    private function getValidatedDeliveryAddress(Contract $contract, CalculationContext $context): DeliveryAddress
    {
        $addressId = $this->extractAddressId($context);
        $user = $this->validateUser($contract);
        $addressIdString = $this->validateAddressIdFormat($addressId);

        return $this->findAddress($addressIdString, $user);
    }

    private function extractAddressId(CalculationContext $context): mixed
    {
        $addressId = $context->getMetadataValue('addressId');
        if (null === $addressId) {
            throw new CheckoutException('收货地址ID不能为空');
        }

        return $addressId;
    }

    private function validateUser(Contract $contract): UserInterface
    {
        $user = $contract->getUser();
        if (null === $user) {
            throw new CheckoutException('订单用户信息无效');
        }

        return $user;
    }

    private function validateAddressIdFormat(mixed $addressId): string
    {
        if (!is_scalar($addressId)) {
            throw new CheckoutException('收货地址ID格式无效');
        }

        $addressIdString = (string) $addressId;
        if ('' === $addressIdString) {
            throw new CheckoutException('收货地址ID格式无效');
        }

        return $addressIdString;
    }

    private function findAddress(string $addressIdString, UserInterface $user): DeliveryAddress
    {
        $address = $this->deliveryAddressService->getAddressByIdAndUser($addressIdString, $user);
        if (null === $address) {
            throw new CheckoutException('收货地址无效');
        }

        return $address;
    }

    private function createContactFromAddress(Contract $contract, DeliveryAddress $address): void
    {
        $contactInfo = $this->extractContactInfo($address);

        if ($this->isValidContactInfo($contactInfo)) {
            $orderContact = $this->buildOrderContact($contract, $address, $contactInfo);
            $contract->addContact($orderContact);
            $this->entityManager->persist($orderContact);
        }
    }

    /**
     * @return array{name: string, phone: string, address: string}
     */
    private function extractContactInfo(DeliveryAddress $address): array
    {
        return [
            'name' => $address->getConsignee(),
            'phone' => $address->getMobile(),
            'address' => $address->getAddressLine(),
        ];
    }

    /**
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function isValidContactInfo(array $contactInfo): bool
    {
        return '' !== $contactInfo['name'] && '' !== $contactInfo['phone'];
    }

    /**
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function buildOrderContact(Contract $contract, DeliveryAddress $address, array $contactInfo): OrderContact
    {
        $orderContact = $this->createOrderContactBase($contract, $address, $contactInfo);
        $this->setOrderContactRegionInfo($orderContact, $address);
        $orderContact->setGender($address->getGender());

        return $orderContact;
    }

    /**
     * @param array{name: string, phone: string, address: string} $contactInfo
     */
    private function createOrderContactBase(Contract $contract, DeliveryAddress $address, array $contactInfo): OrderContact
    {
        $orderContact = new OrderContact();
        $orderContact->setDeliveryAddressId($address->getId());
        $orderContact->setContract($contract);
        $orderContact->setRealname($contactInfo['name']);
        $orderContact->setMobile($contactInfo['phone']);
        $orderContact->setAddress($contactInfo['address']);

        return $orderContact;
    }

    private function setOrderContactRegionInfo(OrderContact $orderContact, DeliveryAddress $address): void
    {
        $orderContact->setProvinceName($address->getProvince());
        $orderContact->setProvinceCode($address->getProvinceCode());
        $orderContact->setCityName($address->getCity());
        $orderContact->setCityCode($address->getCityCode());
        $orderContact->setAreaName($address->getDistrict());
        $orderContact->setDistrictCode($address->getDistrictCode());
    }
}
