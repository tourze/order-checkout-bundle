<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service\Order;

use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\DeliveryAddressBundle\Service\DeliveryAddressService;
use Tourze\GBT2261\Gender;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Service\Order\OrderContactBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

#[CoversClass(OrderContactBuilder::class)]
#[RunTestsInSeparateProcesses]
final class OrderContactBuilderTest extends AbstractIntegrationTestCase
{
    private OrderContactBuilder $builder;
    private DeliveryAddressService $deliveryAddressService;

    protected function onSetUp(): void
    {
        $this->deliveryAddressService = self::getService(DeliveryAddressService::class);
        $this->builder = self::getService(OrderContactBuilder::class);
    }

    private function buildTestUser(): UserInterface
    {
        return new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test-user';
            }
        };
    }

    /**
     * 创建用于数据库交互的真实用户
     */
    private function createDbUser(): UserInterface
    {
        return $this->createUserWithRoles(['ROLE_USER'], 'test_user_' . uniqid());
    }

    #[Test]
    public function testCreateOrderContactWithRedeemOrderTypeSkipsContactCreation(): void
    {
        $user = $this->buildTestUser();
        $context = new CalculationContext($user, [], [], ['orderType' => 'redeem']);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-001');
        $contract->setUser($user);

        $this->builder->createOrderContact($contract, $context);

        self::assertCount(0, $contract->getContacts());
    }

    #[Test]
    public function testCreateOrderContactWithMissingAddressIdThrowsException(): void
    {
        $user = $this->buildTestUser();
        $context = new CalculationContext($user, [], [], []);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-002');
        $contract->setUser($user);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('收货地址ID不能为空');

        $this->builder->createOrderContact($contract, $context);
    }

    #[Test]
    public function testCreateOrderContactWithInvalidAddressIdThrowsException(): void
    {
        $user = $this->buildTestUser();
        $context = new CalculationContext($user, [], [], ['addressId' => []]);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-003');
        $contract->setUser($user);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('收货地址ID格式无效');

        $this->builder->createOrderContact($contract, $context);
    }

    #[Test]
    public function testCreateOrderContactWithEmptyAddressIdThrowsException(): void
    {
        $user = $this->buildTestUser();
        $context = new CalculationContext($user, [], [], ['addressId' => '']);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-004');
        $contract->setUser($user);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('收货地址ID格式无效');

        $this->builder->createOrderContact($contract, $context);
    }

    #[Test]
    public function testCreateOrderContactWithNullContractUserThrowsException(): void
    {
        $user = $this->buildTestUser();
        $context = new CalculationContext($user, [], [], ['addressId' => '123']);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-005');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('订单用户信息无效');

        $this->builder->createOrderContact($contract, $context);
    }

    #[Test]
    public function testCreateOrderContactWithAddressNotFoundThrowsException(): void
    {
        // 使用真实用户，因为需要进行数据库查询
        $user = $this->createDbUser();
        // 使用一个不存在的ID
        $context = new CalculationContext($user, [], [], ['addressId' => '999999999']);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-006');
        $contract->setUser($user);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('收货地址无效');

        $this->builder->createOrderContact($contract, $context);
    }

    #[Test]
    public function testCreateOrderContactWithValidAddressCreatesContact(): void
    {
        // 使用真实用户
        $user = $this->createDbUser();

        // 创建真实的地址记录
        $address = new DeliveryAddress();
        $address->setConsignee('张三');
        $address->setMobile('13800138000');
        $address->setProvince('北京市');
        $address->setProvinceCode('110000');
        $address->setCity('北京市');
        $address->setCityCode('110100');
        $address->setDistrict('朝阳区');
        $address->setDistrictCode('110105');
        $address->setAddressLine('某某街道123号');
        $address->setGender(Gender::MAN);
        $address->setUser($user);

        $em = self::getEntityManager();
        $em->persist($address);
        $em->flush();

        $context = new CalculationContext($user, [], [], ['addressId' => (string) $address->getId()]);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-007');
        $contract->setUser($user);
        $contract->setState(OrderState::INIT);

        $this->builder->createOrderContact($contract, $context);

        $contacts = $contract->getContacts();
        self::assertCount(1, $contacts);

        $contact = $contacts->first();
        self::assertSame('张三', $contact->getRealname());
        self::assertSame('13800138000', $contact->getMobile());
        self::assertSame('北京市', $contact->getProvinceName());
        self::assertSame('110000', $contact->getProvinceCode());
        self::assertSame('北京市', $contact->getCityName());
        self::assertSame('110100', $contact->getCityCode());
        self::assertSame('朝阳区', $contact->getAreaName());
        self::assertSame('110105', $contact->getDistrictCode());
        self::assertSame(Gender::MAN, $contact->getGender());
    }

    #[Test]
    public function testCreateOrderContactWithEmptyConsigneeSkipsContactCreation(): void
    {
        $user = $this->createDbUser();

        // 创建真实的地址记录，但收件人为空
        $address = new DeliveryAddress();
        $address->setConsignee('');
        $address->setMobile('13800138000');
        $address->setProvince('北京市');
        $address->setCity('北京市');
        $address->setDistrict('朝阳区');
        $address->setAddressLine('某某街道123号');
        $address->setUser($user);

        $em = self::getEntityManager();
        $em->persist($address);
        $em->flush();

        $context = new CalculationContext($user, [], [], ['addressId' => (string) $address->getId()]);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-008');
        $contract->setUser($user);
        $contract->setState(OrderState::INIT);

        $this->builder->createOrderContact($contract, $context);

        self::assertCount(0, $contract->getContacts());
    }

    #[Test]
    public function testCreateOrderContactWithEmptyPhoneSkipsContactCreation(): void
    {
        $user = $this->createDbUser();

        // 创建真实的地址记录，但电话为空
        $address = new DeliveryAddress();
        $address->setConsignee('张三');
        $address->setMobile('');
        $address->setProvince('北京市');
        $address->setCity('北京市');
        $address->setDistrict('朝阳区');
        $address->setAddressLine('某某街道123号');
        $address->setUser($user);

        $em = self::getEntityManager();
        $em->persist($address);
        $em->flush();

        $context = new CalculationContext($user, [], [], ['addressId' => (string) $address->getId()]);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-009');
        $contract->setUser($user);
        $contract->setState(OrderState::INIT);

        $this->builder->createOrderContact($contract, $context);

        self::assertCount(0, $contract->getContacts());
    }

    #[Test]
    public function testCreateOrderContactWithIntegerAddressIdWorks(): void
    {
        $user = $this->createDbUser();

        // 创建真实的地址记录
        $address = new DeliveryAddress();
        $address->setConsignee('李四');
        $address->setMobile('13900139000');
        $address->setProvince('北京市');
        $address->setCity('北京市');
        $address->setDistrict('朝阳区');
        $address->setAddressLine('某某街道456号');
        $address->setUser($user);

        $em = self::getEntityManager();
        $em->persist($address);
        $em->flush();

        // 使用整数类型的 addressId
        $context = new CalculationContext($user, [], [], ['addressId' => $address->getId()]);

        $contract = new Contract();
        $contract->setSn('TEST-CONTACT-010');
        $contract->setUser($user);
        $contract->setState(OrderState::INIT);

        $this->builder->createOrderContact($contract, $context);

        $contacts = $contract->getContacts();
        self::assertCount(1, $contacts);
        self::assertSame('李四', $contacts->first()->getRealname());
    }
}
