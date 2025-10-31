<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;

class ShippingTemplateAreaFixtures extends Fixture implements DependentFixtureInterface
{
    public const SHIPPING_AREA_BEIJING_REFERENCE = 'shipping-area-beijing';
    public const SHIPPING_AREA_SHANGHAI_REFERENCE = 'shipping-area-shanghai';
    public const SHIPPING_AREA_REMOTE_REFERENCE = 'shipping-area-remote';

    public function load(ObjectManager $manager): void
    {
        $defaultTemplate = $this->getReference(ShippingTemplateFixtures::SHIPPING_TEMPLATE_DEFAULT_REFERENCE, ShippingTemplate::class);
        assert($defaultTemplate instanceof ShippingTemplate);

        // 北京市配送区域
        $beijingArea = new ShippingTemplateArea();
        $beijingArea->setShippingTemplate($defaultTemplate);
        $beijingArea->setProvinceCode('110000');
        $beijingArea->setProvinceName('北京市');
        $beijingArea->setFirstUnit('1.000');
        $beijingArea->setFirstUnitFee('8.00');
        $beijingArea->setAdditionalUnit('1.000');
        $beijingArea->setAdditionalUnitFee('4.00');
        $beijingArea->setFreeShippingThreshold('79.00');
        $beijingArea->setIsDeliverable(true);

        // 上海市配送区域
        $shanghaiArea = new ShippingTemplateArea();
        $shanghaiArea->setShippingTemplate($defaultTemplate);
        $shanghaiArea->setProvinceCode('310000');
        $shanghaiArea->setProvinceName('上海市');
        $shanghaiArea->setFirstUnit('1.000');
        $shanghaiArea->setFirstUnitFee('8.00');
        $shanghaiArea->setAdditionalUnit('1.000');
        $shanghaiArea->setAdditionalUnitFee('4.00');
        $shanghaiArea->setFreeShippingThreshold('79.00');
        $shanghaiArea->setIsDeliverable(true);

        // 偏远地区配送区域（西藏）
        $remoteArea = new ShippingTemplateArea();
        $remoteArea->setShippingTemplate($defaultTemplate);
        $remoteArea->setProvinceCode('540000');
        $remoteArea->setProvinceName('西藏自治区');
        $remoteArea->setFirstUnit('1.000');
        $remoteArea->setFirstUnitFee('15.00');
        $remoteArea->setAdditionalUnit('1.000');
        $remoteArea->setAdditionalUnitFee('10.00');
        $remoteArea->setFreeShippingThreshold('299.00');
        $remoteArea->setIsDeliverable(true);

        $defaultTemplate->addArea($beijingArea);
        $defaultTemplate->addArea($shanghaiArea);
        $defaultTemplate->addArea($remoteArea);

        $manager->persist($beijingArea);
        $manager->persist($shanghaiArea);
        $manager->persist($remoteArea);

        $manager->flush();

        // 添加引用以供其他 Fixtures 使用
        $this->addReference(self::SHIPPING_AREA_BEIJING_REFERENCE, $beijingArea);
        $this->addReference(self::SHIPPING_AREA_SHANGHAI_REFERENCE, $shanghaiArea);
        $this->addReference(self::SHIPPING_AREA_REMOTE_REFERENCE, $remoteArea);
    }

    public function getDependencies(): array
    {
        return [
            ShippingTemplateFixtures::class,
        ];
    }
}
