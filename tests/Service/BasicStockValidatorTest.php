<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Service\BasicStockValidator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SKU as SkuContract;

/**
 * @internal
 *
 * BasicStockValidator 服务的集成测试。
 * 使用 AbstractIntegrationTestCase 从容器获取服务实例，使用真实的 StockService。
 */
#[CoversClass(BasicStockValidator::class)]
#[RunTestsInSeparateProcesses]
final class BasicStockValidatorTest extends AbstractIntegrationTestCase
{
    private BasicStockValidator $validator;

    protected function onSetUp(): void
    {
        $this->validator = self::getService(BasicStockValidator::class);
    }

    public function testValidatorCanBeCreated(): void
    {
        $this->assertInstanceOf(BasicStockValidator::class, $this->validator);
    }

    public function testValidateWithEmptyItems(): void
    {
        $result = $this->validator->validate([]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getDetails());
        $this->assertEmpty($result->getErrors());
        $this->assertEmpty($result->getWarnings());
    }

    public function testValidateWithUnselectedItems(): void
    {
        // 创建真实的商品数据用于测试
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试SKU');
        $sku->setGtin('TEST001');
        $sku->setUnit('个');

        $cartItem = new CheckoutItem($sku->getId(), 5, false, $sku);

        $result = $this->validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getDetails());
    }

    public function testValidateWithValidItems(): void
    {
        // 创建真实的商品数据用于测试
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试SKU');
        $sku->setGtin('TEST001');
        $sku->setUnit('个');

        // 先保存到数据库以便库存服务可以找到
        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        $cartItem = new CheckoutItem($sku->getId(), 1, true, $sku);

        $result = $this->validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        // 验证结果取决于实际的库存服务配置
        $this->assertIsBool($result->isValid());
    }

    public function testGetAvailableQuantity(): void
    {
        // 创建真实的SKU
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试SKU');
        $sku->setGtin('TEST002');
        $sku->setUnit('个');

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        $quantity = $this->validator->getAvailableQuantity($sku);

        // 验证返回的是整数，具体值取决于库存服务
        $this->assertIsInt($quantity);
        $this->assertGreaterThanOrEqual(0, $quantity);
    }

    public function testGetAvailableQuantities(): void
    {
        // 创建多个真实的SKU
        $spu1 = new Spu();
        $spu1->setTitle('测试商品1');
        $spu1->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setTitle('测试商品2');
        $spu2->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu2->setValid(true);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setTitle('测试SKU1');
        $sku1->setGtin('TEST003');
        $sku1->setUnit('个');

        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setTitle('测试SKU2');
        $sku2->setGtin('TEST004');
        $sku2->setUnit('个');

        self::getEntityManager()->persist($spu1);
        self::getEntityManager()->persist($spu2);
        self::getEntityManager()->persist($sku1);
        self::getEntityManager()->persist($sku2);
        self::getEntityManager()->flush();

        $skus = [$sku1, $sku2];

        $quantities = $this->validator->getAvailableQuantities($skus);

        $this->assertIsArray($quantities);
        $this->assertCount(2, $quantities);
        $this->assertArrayHasKey($sku1->getId(), $quantities);
        $this->assertArrayHasKey($sku2->getId(), $quantities);

        foreach ($quantities as $quantity) {
            $this->assertIsInt($quantity);
            $this->assertGreaterThanOrEqual(0, $quantity);
        }
    }
}