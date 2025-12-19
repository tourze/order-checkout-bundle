<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceCalculationItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;

/**
 * @internal
 */
#[CoversClass(BasePriceCalculator::class)]
#[RunTestsInSeparateProcesses]
final class BasePriceCalculatorTest extends AbstractIntegrationTestCase
{
    private BasePriceCalculator $calculator;

    protected function onSetUp(): void
    {
        $this->calculator = self::getService(BasePriceCalculator::class);
    }

    public function testBasePriceCalculatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(BasePriceCalculator::class, $this->calculator);
    }

    public function testCalculateReturnsPriceResult(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new CalculationContext($user, [], []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
    }

    public function testSupportsWithEmptyItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new CalculationContext($user, [], []);

        $this->assertFalse($this->calculator->supports($context));
    }

    public function testSupportsWithItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // 创建真实的 Sku 对象
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试 SKU');
        $sku->setMarketPrice('100.00');
        $sku->setUnit('个');

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        $items = [
            new PriceCalculationItem(
                skuId: $sku->getId(),
                quantity: 1,
                selected: true,
                sku: $sku
            ),
        ];
        $context = new CalculationContext($user, $items, []);

        $this->assertTrue($this->calculator->supports($context));
    }

    public function testGetPriority(): void
    {
        $this->assertSame(1000, $this->calculator->getPriority());
    }

    public function testGetType(): void
    {
        $this->assertSame('base_price', $this->calculator->getType());
    }

    public function testCalculateWithProductsInfo(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // 创建真实的商品数据
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试商品 - 红色');
        $sku->setMarketPrice('99.99');
        $sku->setUnit('个');

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        $item = new PriceCalculationItem(
            skuId: $sku->getId(),
            quantity: 2,
            selected: true,
            sku: $sku
        );

        $context = new CalculationContext($user, [$item], []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame('199.98', $result->getOriginalPrice());
        $this->assertSame('199.98', $result->getFinalPrice());

        // 检验 products属性
        $products = $result->getProducts();
        $this->assertCount(1, $products);

        $product = $products[0];
        $this->assertSame($sku->getId(), $product['skuId']);
        $this->assertSame($spu->getId(), $product['spuId']);
        $this->assertSame(2, $product['quantity']);
        $this->assertSame('199.98', $product['payablePrice']);
        $this->assertSame('99.99', $product['unitPrice']);
        // productName 是 getFullName() 返回的，格式为 "SPU标题 - SKU标题"
        $this->assertSame('测试商品 - 测试商品 - 红色', $product['productName']);
    }

    public function testCalculateWithUnselectedItemsExcludesFromProducts(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // 创建真实的 Sku 对象
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setState(\Tourze\ProductCoreBundle\Enum\SpuState::ONLINE);
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setTitle('测试 SKU');
        $sku->setMarketPrice('100.00');
        $sku->setUnit('个');

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        $item = new PriceCalculationItem(
            skuId: $sku->getId(),
            quantity: 1,
            selected: false,
            sku: $sku
        );
        $context = new CalculationContext($user, [$item], []);

        $result = $this->calculator->calculate($context);

        $this->assertSame([], $result->getProducts());
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
    }
}