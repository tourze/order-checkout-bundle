<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Service\BasicStockValidator;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SKU as SkuContract;
use Tourze\StockManageBundle\Model\StockSummary;
use Tourze\StockManageBundle\Service\StockServiceInterface;

/**
 * @internal
 */
#[CoversClass(BasicStockValidator::class)]
final class BasicStockValidatorTest extends TestCase
{
    private function createValidator(int $defaultStock = 50): BasicStockValidator
    {
        $stockService = $this->createMock(StockServiceInterface::class);
        $stockSummary = $this->createMock(StockSummary::class);
        $stockSummary->method('getAvailableQuantity')->willReturn($defaultStock);
        $stockService->method('getAvailableStock')->willReturn($stockSummary);

        $logger = $this->createMock(LoggerInterface::class);

        return new BasicStockValidator($stockService, $logger);
    }

    public function testValidatorCanBeCreated(): void
    {
        $validator = $this->createValidator();

        $this->assertInstanceOf(BasicStockValidator::class, $validator);
    }

    public function testValidate(): void
    {
        $validator = $this->createValidator(50);

        // 创建测试用的购物车商品
        $spu1 = $this->createMock(Spu::class);
        $spu1->method('isValid')->willReturn(true);

        $sku1 = $this->createMock(Sku::class);
        $sku1->method('getId')->willReturn('test-sku-1');
        $sku1->method('getGtin')->willReturn('SKU001');
        $sku1->method('getFullName')->willReturn('测试商品1');
        $sku1->method('isValid')->willReturn(true);
        $sku1->method('getSpu')->willReturn($spu1);
        $cartItem1 = new CheckoutItem('test-sku-1', 5, true, $sku1);

        $spu2 = $this->createMock(Spu::class);
        $spu2->method('isValid')->willReturn(true);

        $sku2 = $this->createMock(Sku::class);
        $sku2->method('getId')->willReturn('test-sku-2');
        $sku2->method('getGtin')->willReturn('SKU002');
        $sku2->method('getFullName')->willReturn('测试商品2');
        $sku2->method('isValid')->willReturn(true);
        $sku2->method('getSpu')->willReturn($spu2);
        $cartItem2 = new CheckoutItem('test-sku-2', 1, true, $sku2);

        $result = $validator->validate([$cartItem1, $cartItem2]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertArrayHasKey('test-sku-1', $result->getDetails());
        $this->assertArrayHasKey('test-sku-2', $result->getDetails());
    }

    public function testValidateWithUnselectedItems(): void
    {
        $validator = $this->createValidator();

        // 创建未选中的购物车商品
        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(false);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getDetails());
    }

    public function testValidateWithStockShortage(): void
    {
        // 创建一个返回0库存的验证器
        $validator = $this->createValidator(0);

        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(true);
        $cartItem->method('getQuantity')->willReturn(5);

        $spu = $this->createMock(Spu::class);
        $spu->method('isValid')->willReturn(true);

        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('out-of-stock-sku');
        $sku->method('getGtin')->willReturn('OUT001');
        $sku->method('getFullName')->willReturn('无库存商品');
        $sku->method('isValid')->willReturn(true);
        $sku->method('getSpu')->willReturn($spu);
        $cartItem->method('getSku')->willReturn($sku);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('out-of-stock-sku', $result->getErrors());
        $errors = $result->getErrors();
        $this->assertIsString($errors['out-of-stock-sku']);
        $this->assertStringContainsString('库存不足', $errors['out-of-stock-sku']);
    }

    public function testValidateWithInsufficientStock(): void
    {
        // 创建一个返回3个库存的验证器，但请求5个
        $validator = $this->createValidator(3);

        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(true);
        $cartItem->method('getQuantity')->willReturn(5);

        $spu = $this->createMock(Spu::class);
        $spu->method('isValid')->willReturn(true);

        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('insufficient-stock-sku');
        $sku->method('getGtin')->willReturn('INSUF001');
        $sku->method('getFullName')->willReturn('库存不足商品');
        $sku->method('isValid')->willReturn(true);
        $sku->method('getSpu')->willReturn($spu);
        $cartItem->method('getSku')->willReturn($sku);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('insufficient-stock-sku', $result->getErrors());
        $errors = $result->getErrors();
        $this->assertIsString($errors['insufficient-stock-sku']);
        $this->assertStringContainsString('库存不足', $errors['insufficient-stock-sku']);
    }

    public function testValidateWithStockWarning(): void
    {
        // 创建一个返回5个库存的验证器（少于10个，会触发警告）
        $validator = $this->createValidator(5);

        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(true);
        $cartItem->method('getQuantity')->willReturn(1);

        $spu = $this->createMock(Spu::class);
        $spu->method('isValid')->willReturn(true);

        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('low-stock-sku');
        $sku->method('getGtin')->willReturn('SKU-LOW');
        $sku->method('getFullName')->willReturn('低库存商品');
        $sku->method('isValid')->willReturn(true);
        $sku->method('getSpu')->willReturn($spu);
        $cartItem->method('getSku')->willReturn($sku);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertArrayHasKey('low-stock-sku', $result->getWarnings());
        $warnings = $result->getWarnings();
        $this->assertIsString($warnings['low-stock-sku']);
        $this->assertStringContainsString('库存较少', $warnings['low-stock-sku']);
    }

    public function testValidateWithMixedScenarios(): void
    {
        // 创建一个针对不同SKU返回不同库存的mock
        $stockService = $this->createMock(StockServiceInterface::class);
        $stockService->method('getAvailableStock')->willReturnCallback(function ($sku) {
            $stockSummary = $this->createMock(StockSummary::class);
            $this->assertIsObject($sku);
            $this->assertTrue(method_exists($sku, 'getId'));
            $skuId = $sku->getId();
            $this->assertIsString($skuId);

            if ('normal-sku-1' === $skuId) {
                $stockSummary->method('getAvailableQuantity')->willReturn(50);
            } elseif ('out-of-stock-sku' === $skuId) {
                $stockSummary->method('getAvailableQuantity')->willReturn(0);
            }

            return $stockSummary;
        });

        $logger = $this->createMock(LoggerInterface::class);
        $validator = new BasicStockValidator($stockService, $logger);

        // 正常商品
        $normalItem = $this->createMock(CheckoutItem::class);
        $normalItem->method('isSelected')->willReturn(true);
        $normalItem->method('getQuantity')->willReturn(2);

        $normalSpu = $this->createMock(Spu::class);
        $normalSpu->method('isValid')->willReturn(true);

        $normalSku = $this->createMock(Sku::class);
        $normalSku->method('getId')->willReturn('normal-sku-1');
        $normalSku->method('getGtin')->willReturn('NORMAL001');
        $normalSku->method('getFullName')->willReturn('正常商品');
        $normalSku->method('isValid')->willReturn(true);
        $normalSku->method('getSpu')->willReturn($normalSpu);
        $normalItem->method('getSku')->willReturn($normalSku);

        // 无库存商品
        $outOfStockItem = $this->createMock(CheckoutItem::class);
        $outOfStockItem->method('isSelected')->willReturn(true);
        $outOfStockItem->method('getQuantity')->willReturn(1);

        $outOfStockSpu = $this->createMock(Spu::class);
        $outOfStockSpu->method('isValid')->willReturn(true);

        $outOfStockSku = $this->createMock(Sku::class);
        $outOfStockSku->method('getId')->willReturn('out-of-stock-sku');
        $outOfStockSku->method('getGtin')->willReturn('OUT001');
        $outOfStockSku->method('getFullName')->willReturn('无库存商品');
        $outOfStockSku->method('isValid')->willReturn(true);
        $outOfStockSku->method('getSpu')->willReturn($outOfStockSpu);
        $outOfStockItem->method('getSku')->willReturn($outOfStockSku);

        // 未选中商品（应被忽略）
        $unselectedItem = $this->createMock(CheckoutItem::class);
        $unselectedItem->method('isSelected')->willReturn(false);

        $result = $validator->validate([$normalItem, $outOfStockItem, $unselectedItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertFalse($result->isValid()); // 因为有无库存商品
        $this->assertArrayHasKey('normal-sku-1', $result->getDetails());
        $this->assertArrayHasKey('out-of-stock-sku', $result->getDetails());
        $this->assertArrayHasKey('out-of-stock-sku', $result->getErrors());
    }

    public function testGetAvailableQuantity(): void
    {
        $validator = $this->createValidator(50);

        $sku = $this->createMock(SkuContract::class);
        $sku->method('getId')->willReturn('test-sku-1');

        $quantity = $validator->getAvailableQuantity($sku);

        $this->assertEquals(50, $quantity);
    }

    public function testGetAvailableQuantities(): void
    {
        $validator = $this->createValidator(25);

        $sku1 = $this->createMock(SkuContract::class);
        $sku1->method('getId')->willReturn('sku-1');

        $sku2 = $this->createMock(SkuContract::class);
        $sku2->method('getId')->willReturn('sku-2');

        $sku3 = $this->createMock(SkuContract::class);
        $sku3->method('getId')->willReturn('sku-3');

        $skus = [$sku1, $sku2, $sku3];

        $quantities = $validator->getAvailableQuantities($skus);

        $this->assertIsArray($quantities);
        $this->assertCount(3, $quantities);
        $this->assertArrayHasKey('sku-1', $quantities);
        $this->assertArrayHasKey('sku-2', $quantities);
        $this->assertArrayHasKey('sku-3', $quantities);

        foreach ($quantities as $quantity) {
            $this->assertEquals(25, $quantity);
            $this->assertIsInt($quantity);
        }
    }

    public function testValidateWithMissingSkuFields(): void
    {
        $validator = $this->createValidator();

        // 测试SKU缺少信息字段的情况
        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(true);
        $cartItem->method('getQuantity')->willReturn(1);

        $spu = $this->createMock(Spu::class);
        $spu->method('isValid')->willReturn(true);

        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('incomplete-sku');
        $sku->method('getGtin')->willReturn(null);
        $sku->method('getMpn')->willReturn(null);
        $sku->method('getFullName')->willReturn(''); // 返回空字符串而不是null
        $sku->method('isValid')->willReturn(true);
        $sku->method('getSpu')->willReturn($spu);
        $cartItem->method('getSku')->willReturn($sku);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $details = $result->getDetails();
        $this->assertArrayHasKey('incomplete-sku', $details);
        $this->assertArrayHasKey('incomplete-sku', $details);
        $incompleteDetails = $details['incomplete-sku'];
        $this->assertIsArray($incompleteDetails);
        $this->assertEquals('incomplete-sku', $incompleteDetails['sku_code']);
        $this->assertEquals('', $incompleteDetails['sku_name']);
    }

    public function testValidateWithZeroQuantity(): void
    {
        $validator = $this->createValidator();

        $cartItem = $this->createMock(CheckoutItem::class);
        $cartItem->method('isSelected')->willReturn(true);
        $cartItem->method('getQuantity')->willReturn(0);

        $spu = $this->createMock(Spu::class);
        $spu->method('isValid')->willReturn(true);

        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('zero-qty-sku');
        $sku->method('getGtin')->willReturn('ZERO001');
        $sku->method('getFullName')->willReturn('零数量商品');
        $sku->method('isValid')->willReturn(true);
        $sku->method('getSpu')->willReturn($spu);
        $cartItem->method('getSku')->willReturn($sku);

        $result = $validator->validate([$cartItem]);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $details = $result->getDetails();
        $this->assertArrayHasKey('zero-qty-sku', $details);
        $this->assertArrayHasKey('zero-qty-sku', $details);
        $zeroQtyDetails = $details['zero-qty-sku'];
        $this->assertIsArray($zeroQtyDetails);
        $this->assertEquals(0, $zeroQtyDetails['requested_quantity']);
    }
}
