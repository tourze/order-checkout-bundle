<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Controller\Admin\ShippingTemplateAreaCrudController;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateAreaCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ShippingTemplateAreaCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function getControllerFqcn(): string
    {
        return ShippingTemplateAreaCrudController::class;
    }

    public function getEntityFqcn(): string
    {
        return ShippingTemplateArea::class;
    }

    protected function getControllerService(): ShippingTemplateAreaCrudController
    {
        return new ShippingTemplateAreaCrudController();
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'ID' => ['ID'],
            '配送模板' => ['配送模板'],
            '省份代码' => ['省份代码'],
            '省份名称' => ['省份名称'],
            '城市代码' => ['城市代码'],
            '城市名称' => ['城市名称'],
            '区域首重/首件' => ['区域首重/首件'],
            '区域首重/首件运费' => ['区域首重/首件运费'],
            '支持配送' => ['支持配送'],
            '创建时间' => ['创建时间'],
            '更新时间' => ['更新时间'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'provinceCode' => ['provinceCode'];
        yield 'provinceName' => ['provinceName'];
        yield 'cityCode' => ['cityCode'];
        yield 'cityName' => ['cityName'];
        yield 'areaCode' => ['areaCode'];
        yield 'areaName' => ['areaName'];
        yield 'firstUnit' => ['firstUnit'];
        yield 'firstUnitFee' => ['firstUnitFee'];
        yield 'additionalUnit' => ['additionalUnit'];
        yield 'additionalUnitFee' => ['additionalUnitFee'];
        yield 'freeShippingThreshold' => ['freeShippingThreshold'];
        yield 'isDeliverable' => ['isDeliverable'];
        yield 'extendedConfig' => ['extendedConfig'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'provinceCode' => ['provinceCode'];
        yield 'provinceName' => ['provinceName'];
        yield 'cityCode' => ['cityCode'];
        yield 'cityName' => ['cityName'];
        yield 'areaCode' => ['areaCode'];
        yield 'areaName' => ['areaName'];
        yield 'firstUnit' => ['firstUnit'];
        yield 'firstUnitFee' => ['firstUnitFee'];
        yield 'additionalUnit' => ['additionalUnit'];
        yield 'additionalUnitFee' => ['additionalUnitFee'];
        yield 'freeShippingThreshold' => ['freeShippingThreshold'];
        yield 'isDeliverable' => ['isDeliverable'];
        yield 'extendedConfig' => ['extendedConfig'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        // 尝试创建一个缺少必填字段的区域配置
        // 提供部分有效数据，但省略必填字段以触发验证错误
        $client->request('POST', $this->generateAdminUrl('new'), [
            'ShippingTemplateArea' => [
                // provinceCode 是必填的，故意留空
                // provinceName 是必填的，故意留空
                'firstUnit' => 1,
                'firstUnitFee' => 10.00,
                'isDeliverable' => true,
            ],
            'ea' => [
                'newForm' => [
                    'btn' => 'saveAndReturn',
                ],
            ],
        ]);

        // 验证响应状态码为422（表单验证失败）
        $this->assertResponseStatusCodeSame(422);

        // 验证响应包含错误信息
        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);

        // 验证包含验证错误的迹象（可能是中文或英文）
        $hasError = str_contains($content, 'error') ||
                    str_contains($content, 'invalid') ||
                    str_contains($content, '错误') ||
                    str_contains($content, '不能为空') ||
                    str_contains($content, 'should not be blank');
        $this->assertTrue($hasError, '应该包含表单验证错误信息');
    }
}
