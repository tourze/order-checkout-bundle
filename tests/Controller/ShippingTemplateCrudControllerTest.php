<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Controller\ShippingTemplateCrudController;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ShippingTemplateCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function getControllerFqcn(): string
    {
        return ShippingTemplateCrudController::class;
    }

    public function getEntityFqcn(): string
    {
        return ShippingTemplate::class;
    }

    protected function getControllerService(): ShippingTemplateCrudController
    {
        return new ShippingTemplateCrudController();
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'ID' => ['ID'],
            '模板名称' => ['模板名称'],
            '计费方式' => ['计费方式'],
            '默认模板' => ['默认模板'],
            '状态' => ['状态'],
            '包邮门槛' => ['包邮门槛'],
            '首重/首件' => ['首重/首件'],
            '首重/首件运费' => ['首重/首件运费'],
            '配送区域' => ['配送区域'],
            '创建时间' => ['创建时间'],
            '更新时间' => ['更新时间'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'chargeType' => ['chargeType'];
        yield 'status' => ['status'];
        yield 'freeShippingThreshold' => ['freeShippingThreshold'];
        yield 'firstUnit' => ['firstUnit'];
        yield 'firstUnitFee' => ['firstUnitFee'];
        yield 'additionalUnit' => ['additionalUnit'];
        yield 'additionalUnitFee' => ['additionalUnitFee'];
        yield 'isDefault' => ['isDefault'];
        yield 'extendedConfig' => ['extendedConfig'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'chargeType' => ['chargeType'];
        yield 'status' => ['status'];
        yield 'freeShippingThreshold' => ['freeShippingThreshold'];
        yield 'firstUnit' => ['firstUnit'];
        yield 'firstUnitFee' => ['firstUnitFee'];
        yield 'additionalUnit' => ['additionalUnit'];
        yield 'additionalUnitFee' => ['additionalUnitFee'];
        yield 'isDefault' => ['isDefault'];
        yield 'extendedConfig' => ['extendedConfig'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        // 尝试创建一个缺少必填字段的运费模板
        // 提供部分有效数据，但省略必填字段以触发验证错误
        $client->request('POST', $this->generateAdminUrl('new'), [
            'ShippingTemplate' => [
                // name 是必填的，故意留空
                // chargeType 是必填的，故意留空
                'firstUnit' => 1,
                'firstUnitFee' => 10.00,
                'status' => 'active',
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
