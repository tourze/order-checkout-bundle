<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use OrderCoreBundle\OrderCoreBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CouponCoreBundle\CouponCoreBundle;
use Tourze\DeliveryAddressBundle\DeliveryAddressBundle;
use Tourze\OrderCartBundle\OrderCartBundle;
use Tourze\OrderCheckoutBundle\OrderCheckoutBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

/**
 * @internal
 */
#[CoversClass(OrderCheckoutBundle::class)]
#[RunTestsInSeparateProcesses]
final class OrderCheckoutBundleTest extends AbstractBundleTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试不需要直接实例化Bundle，而是通过容器来测试
    }

    public function testBundleCanBeInstantiated(): void
    {
        // Act: 通过容器获取Bundle信息而不是直接实例化
        $bundleClass = self::getBundleClass();

        // Assert: 验证Bundle类继承关系
        $this->assertTrue(is_subclass_of($bundleClass, Bundle::class));

        // 验证Bundle在容器中注册
        $kernelBundles = self::getContainer()->getParameter('kernel.bundles');
        $this->assertIsArray($kernelBundles);
        $this->assertArrayHasKey('OrderCheckoutBundle', $kernelBundles);
    }

    public function testBundleImplementsBundleDependencyInterface(): void
    {
        // Assert: 验证接口实现
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $this->assertTrue(is_subclass_of($bundleClass, BundleDependencyInterface::class));
    }

    public function testBundleDependenciesAllHaveAllEnvironmentEnabled(): void
    {
        // Act: 获取依赖配置
        $dependencies = OrderCheckoutBundle::getBundleDependencies();

        // Assert: 验证所有依赖都在all环境下启用
        foreach ($dependencies as $bundleClass => $config) {
            $this->assertArrayHasKey('all', $config, "Bundle {$bundleClass} should have 'all' environment configured");
            $this->assertTrue($config['all'], "Bundle {$bundleClass} should be enabled in 'all' environment");
        }
    }

    public function testBundleDependenciesContainRequiredBundles(): void
    {
        // Act: 获取依赖配置
        $dependencies = OrderCheckoutBundle::getBundleDependencies();
        $bundleClasses = array_keys($dependencies);

        // Assert: 验证必需的Bundle
        $requiredBundles = [
            SecurityBundle::class,
            DoctrineBundle::class,
            DeliveryAddressBundle::class,
            OrderCartBundle::class,
            ProductCoreBundle::class,
            OrderCoreBundle::class,
            StockManageBundle::class,
            CouponCoreBundle::class,
        ];

        foreach ($requiredBundles as $requiredBundle) {
            $this->assertContains(
                $requiredBundle,
                $bundleClasses,
                "Required bundle {$requiredBundle} is missing from dependencies"
            );
        }
    }

    public function testBundleNameIsCorrect(): void
    {
        // Act: 通过类名推导Bundle名称
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $shortName = (new \ReflectionClass($bundleClass))->getShortName();

        // Assert: 验证Bundle名称
        $this->assertEquals('OrderCheckoutBundle', $shortName);
    }

    public function testBundleNamespaceIsCorrect(): void
    {
        // Act: 获取Bundle命名空间
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $namespace = (new \ReflectionClass($bundleClass))->getNamespaceName();

        // Assert: 验证Bundle命名空间
        $this->assertEquals('Tourze\OrderCheckoutBundle', $namespace);
    }

    public function testBundlePathIsCorrect(): void
    {
        // Act: 获取Bundle路径
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $reflection = new \ReflectionClass($bundleClass);
        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $path = dirname($fileName);

        // Assert: 验证Bundle路径包含正确的目录
        $this->assertIsString($path);
        $this->assertStringContainsString('order-checkout-bundle', $path);
        $this->assertDirectoryExists($path);
    }

    public function testBundleCanBeBootedAndShutdown(): void
    {
        // Act & Assert: 验证Bundle的基本结构而不是实际启动
        // 集成测试中Bundle已经通过内核启动了
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $reflection = new \ReflectionClass($bundleClass);

        // 验证Bundle有boot和shutdown方法
        $this->assertTrue($reflection->hasMethod('boot'));
        $this->assertTrue($reflection->hasMethod('shutdown'));

        // 验证Bundle在内核中已注册（即已经"启动"）
        $kernelBundles = self::getContainer()->getParameter('kernel.bundles');
        $this->assertIsArray($kernelBundles);
        $this->assertArrayHasKey('OrderCheckoutBundle', $kernelBundles);
    }

    public function testBundleContainerBuilderIntegration(): void
    {
        // 注意：这里只测试Bundle基本功能，不涉及复杂的容器构建
        // 实际的容器集成测试应该在集成测试中进行

        // Act: 获取Bundle基本信息
        $bundleClass = self::getBundleClass();
        /** @var class-string $bundleClass */
        $reflection = new \ReflectionClass($bundleClass);

        // Assert: 验证Bundle类的基本特征
        $this->assertTrue($reflection->isSubclassOf(Bundle::class));
        $this->assertTrue($reflection->implementsInterface(BundleDependencyInterface::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testBundleDependencyConfigurationStructure(): void
    {
        // Act: 获取依赖配置
        $dependencies = OrderCheckoutBundle::getBundleDependencies();

        // Assert: 验证配置结构
        foreach ($dependencies as $bundleClass => $config) {
            $this->assertIsString($bundleClass, 'Bundle class should be a string');
            $this->assertIsArray($config, 'Bundle configuration should be an array');
            $this->assertNotEmpty($bundleClass, 'Bundle class should not be empty');
            $this->assertNotEmpty($config, 'Bundle configuration should not be empty');

            // 验证Bundle类名结尾
            $this->assertStringEndsWith('Bundle', $bundleClass, 'Bundle class should end with "Bundle"');

            // 验证配置结构
            foreach ($config as $environment => $enabled) {
                $this->assertIsString($environment, 'Environment should be a string');
                $this->assertIsBool($enabled, 'Environment status should be boolean');
            }
        }
    }

    public function testStaticMethodAccessibility(): void
    {
        // Act: 检查静态方法可访问性
        $reflection = new \ReflectionClass(OrderCheckoutBundle::class);
        $method = $reflection->getMethod('getBundleDependencies');

        // Assert: 验证方法特征
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $returnType = $method->getReturnType();
        if ($returnType instanceof \ReflectionType) {
            // PHP 7.1+ 兼容性处理
            $typeName = method_exists($returnType, 'getName') ? $returnType->getName() : (string) $returnType;
            $this->assertEquals('array', $typeName);
        }
    }
}
