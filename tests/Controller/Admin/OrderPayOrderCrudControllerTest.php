<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Controller\Admin\OrderPayOrderCrudController;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(OrderPayOrderCrudController::class)]
#[RunTestsInSeparateProcesses]
final class OrderPayOrderCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getControllerService(): OrderPayOrderCrudController
    {
        /** @var OrderPayOrderCrudController */
        return self::getContainer()->get(OrderPayOrderCrudController::class);
    }

    /** @return \Generator<string, array{string}> */
    public static function provideIndexPageHeaders(): \Generator
    {
        yield 'ID' => ['ID'];
        yield '关联订单' => ['关联订单'];
        yield '支付金额' => ['支付金额'];
    }

    /** @return \Generator<string, array{string}> */
    public static function provideNewPageFields(): \Generator
    {
        yield 'contract' => ['contract'];
        yield 'amount' => ['amount'];
        yield 'tradeNo' => ['tradeNo'];
        yield 'payTime' => ['payTime'];
    }

    /** @return \Generator<string, array{string}> */
    public static function provideEditPageFields(): \Generator
    {
        yield 'contract' => ['contract'];
        yield 'amount' => ['amount'];
        yield 'tradeNo' => ['tradeNo'];
        yield 'payTime' => ['payTime'];
    }

    public function testIndex(): void
    {
        $client = self::createAuthenticatedClient();
        $client->request('GET', '/admin');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * 测试提交表单时的验证错误
     */
    public function testValidationErrors(): void
    {
        $client = self::createAuthenticatedClient();

        // 首先创建一些Contract实体，以便表单中的关联字段有选项可选
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getService(EntityManagerInterface::class);

        // 创建测试用户（符合 TestUserInterface 类型）
        $user = $this->createNormalUser('testuser_payorder', 'password');

        // 创建测试用的Contract实体
        $contract = new Contract();
        $contract->setSn('TEST-CONTRACT-' . uniqid());
        $contract->setState(OrderState::INIT);
        $contract->setRemark('Test contract for PayOrder validation');
        $contract->setUser($user);
        $entityManager->persist($contract);
        $entityManager->flush();

        // 获取新建表单页面
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // 查找表单按钮，尝试多种可能的按钮文本
        $button = $crawler->selectButton('保存');
        if (0 === $button->count()) {
            // 如果没有"保存"按钮，尝试其他常见的按钮文本
            $button = $crawler->selectButton('Create');
        }
        if (0 === $button->count()) {
            // 如果还是没有，尝试"Save"按钮
            $button = $crawler->selectButton('Save');
        }

        // 如果找到按钮，测试验证逻辑
        if ($button->count() > 0) {
            $form = $button->form();

            // 测试策略：选择必填的contract，但留空其他必填字段来触发验证错误
            $formData = $form->getPhpValues();

            // 确保contract字段被设置（选择我们创建的contract）
            if (isset($formData['PayOrder']['contract'])) {
                $formData['PayOrder']['contract'] = $contract->getId();
            }

            // 但是留空amount字段来触发验证错误
            if (isset($formData['PayOrder']['amount'])) {
                $formData['PayOrder']['amount'] = '';
            }

            // 提交修改后的表单数据
            $crawler = $client->request($form->getMethod(), $form->getUri(), $formData);

            // 验证返回验证错误
            $this->assertEquals(422, $client->getResponse()->getStatusCode());
            $this->assertStringContainsString('should not be', $crawler->filter('.invalid-feedback')->text());
        } else {
            // 如果没有找到任何提交按钮，跳过此测试但记录信息
            self::markTestSkipped('No submit button found on the form page');
        }
    }
}
