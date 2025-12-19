<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Controller\Admin\OrderExtendedInfoCrudController;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(OrderExtendedInfoCrudController::class)]
#[RunTestsInSeparateProcesses]
final class OrderExtendedInfoCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function getControllerFqcn(): string
    {
        return OrderExtendedInfoCrudController::class;
    }

    public function getEntityFqcn(): string
    {
        return OrderExtendedInfo::class;
    }

    protected function getControllerService(): OrderExtendedInfoCrudController
    {
        return new OrderExtendedInfoCrudController();
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'ID' => ['ID'],
            '订单ID' => ['订单ID'],
            '信息类型' => ['信息类型'],
            '信息键名' => ['信息键名'],
            '信息内容' => ['信息内容'],
            '已过滤' => ['已过滤'],
            '创建时间' => ['创建时间'],
            '更新时间' => ['更新时间'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'orderId' => ['orderId'];
        yield 'infoType' => ['infoType'];
        yield 'infoKey' => ['infoKey'];
        yield 'infoValue' => ['infoValue'];
        yield 'originalValue' => ['originalValue'];
        yield 'isFiltered' => ['isFiltered'];
        yield 'filteredWords' => ['filteredWords'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'orderId' => ['orderId'];
        yield 'infoType' => ['infoType'];
        yield 'infoKey' => ['infoKey'];
        yield 'infoValue' => ['infoValue'];
        yield 'originalValue' => ['originalValue'];
        yield 'isFiltered' => ['isFiltered'];
        yield 'filteredWords' => ['filteredWords'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        // 访问创建页面
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));

        // 获取表单
        $form = $crawler->selectButton('Create')->form();

        // 提交空表单（不填写任何必填字段）
        $crawler = $client->submit($form);

        // 验证响应状态码为422（表单验证失败）
        $this->assertResponseStatusCodeSame(422);

        // 验证包含错误信息（支持中英文错误消息）
        $errorFeedback = $crawler->filter('.invalid-feedback');
        if ($errorFeedback->count() > 0) {
            $errorText = $errorFeedback->text();
            // 验证包含"不能为空"或"should not be blank"
            $hasValidationError = str_contains($errorText, 'should not be blank') || str_contains($errorText, '不能为空');
            $this->assertTrue($hasValidationError, '应该包含表单验证错误信息');
        }
    }
}
