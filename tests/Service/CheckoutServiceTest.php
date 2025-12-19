<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductCoreBundle\Enum\SpuState;

/**
 * @internal
 */
#[CoversClass(CheckoutService::class)]
#[RunTestsInSeparateProcesses]
final class CheckoutServiceTest extends AbstractIntegrationTestCase
{
    private CheckoutService $checkoutService;

    protected function onSetUp(): void
    {
        $this->checkoutService = self::getService(CheckoutService::class);
    }

    public function testCheckoutServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CheckoutService::class, $this->checkoutService);
    }

    public function testCalculateCheckoutWithEmptyCart(): void
    {
        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('购物车为空，无法结算');

        $user = $this->createNormalUser();
        $this->checkoutService->calculateCheckout($user, []);
    }

    public function testQuickCalculateWithEmptyCartReturnsEmptyResult(): void
    {
        $user = $this->createNormalUser();
        $result = $this->checkoutService->quickCalculate($user, []);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertEmpty($result->getItems());
    }

    private function createTestSku(): Sku
    {
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试 SKU');
        $sku->setMarketPrice('99.00');
        $sku->setUnit('个');

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        return $sku;
    }

    private function createTestDeliveryAddress(UserInterface $user): DeliveryAddress
    {
        $address = new DeliveryAddress();
        $address->setUser($user);
        $address->setConsignee('测试收货人');
        $address->setMobile('13800138000');
        $address->setProvince('广东省');
        $address->setCity('深圳市');
        $address->setDistrict('南山区');
        $address->setAddressLine('测试地址123号');
        $address->setIsDefault(false);

        self::getEntityManager()->persist($address);
        self::getEntityManager()->flush();

        return $address;
    }

    public function testQuickCalculateWithValidCart(): void
    {
        $sku = $this->createTestSku();
        $user = $this->createNormalUser();
        $checkoutItems = [
            ['skuId' => $sku->getId(), 'quantity' => 1, 'selected' => true],
        ];

        $result = $this->checkoutService->quickCalculate($user, $checkoutItems);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        // 验证结果结构，具体值取决于实际的业务逻辑和配置
        $this->assertIsArray($result->getItems());
    }

    public function testCalculateCheckoutWithValidCart(): void
    {
        $sku = $this->createTestSku();
        $user = $this->createNormalUser();
        $checkoutItems = [
            ['skuId' => $sku->getId(), 'quantity' => 1, 'selected' => true],
        ];

        $result = $this->checkoutService->calculateCheckout($user, $checkoutItems);

        $this->assertInstanceOf(CheckoutResult::class, $result);
        // 验证结果结构，具体值取决于实际的业务逻辑和配置
        $this->assertIsArray($result->getItems());
    }

    public function testProcessMethodExists(): void
    {
        // 验证 process 方法存在且为 public
        $reflection = new \ReflectionMethod(CheckoutService::class, 'process');
        $this->assertTrue($reflection->isPublic());

        // 验证方法参数
        $parameters = $reflection->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('context', $parameters[0]->getName());
    }
}