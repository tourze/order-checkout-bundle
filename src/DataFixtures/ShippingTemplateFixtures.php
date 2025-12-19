<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;

final class ShippingTemplateFixtures extends Fixture
{
    public const SHIPPING_TEMPLATE_DEFAULT_REFERENCE = 'shipping-template-default';
    public const SHIPPING_TEMPLATE_QUANTITY_REFERENCE = 'shipping-template-quantity';
    public const SHIPPING_TEMPLATE_FREE_REFERENCE = 'shipping-template-free';

    public function load(ObjectManager $manager): void
    {
        // 创建默认运费模板
        $defaultTemplate = new ShippingTemplate();
        $defaultTemplate->setName('默认运费模板');
        $defaultTemplate->setDescription('全国通用运费模板');
        $defaultTemplate->setChargeType(ChargeType::WEIGHT);
        $defaultTemplate->setIsDefault(true);
        $defaultTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $defaultTemplate->setFreeShippingThreshold('99.00');
        $defaultTemplate->setFirstUnit('1.000');
        $defaultTemplate->setFirstUnitFee('8.00');
        $defaultTemplate->setAdditionalUnit('1.000');
        $defaultTemplate->setAdditionalUnitFee('5.00');

        // 添加广东省配送区域
        $guangdongArea = new ShippingTemplateArea();
        $guangdongArea->setShippingTemplate($defaultTemplate);
        $guangdongArea->setProvinceCode('440000');
        $guangdongArea->setProvinceName('广东省');
        $guangdongArea->setFirstUnit('1.000');
        $guangdongArea->setFirstUnitFee('6.00');
        $guangdongArea->setAdditionalUnit('1.000');
        $guangdongArea->setAdditionalUnitFee('3.00');
        $guangdongArea->setFreeShippingThreshold('88.00');
        $guangdongArea->setIsDeliverable(true);

        // 添加深圳市特殊配送区域
        $shenzhenArea = new ShippingTemplateArea();
        $shenzhenArea->setShippingTemplate($defaultTemplate);
        $shenzhenArea->setProvinceCode('440000');
        $shenzhenArea->setProvinceName('广东省');
        $shenzhenArea->setCityCode('440300');
        $shenzhenArea->setCityName('深圳市');
        $shenzhenArea->setFirstUnit('1.000');
        $shenzhenArea->setFirstUnitFee('5.00');
        $shenzhenArea->setAdditionalUnit('1.000');
        $shenzhenArea->setAdditionalUnitFee('2.00');
        $shenzhenArea->setFreeShippingThreshold('66.00');
        $shenzhenArea->setIsDeliverable(true);

        // 创建按件数计费模板
        $quantityTemplate = new ShippingTemplate();
        $quantityTemplate->setName('按件数计费模板');
        $quantityTemplate->setDescription('适用于小件商品的按件数计费');
        $quantityTemplate->setChargeType(ChargeType::QUANTITY);
        $quantityTemplate->setIsDefault(false);
        $quantityTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $quantityTemplate->setFreeShippingThreshold('199.00');
        $quantityTemplate->setFirstUnit('1.000');
        $quantityTemplate->setFirstUnitFee('10.00');
        $quantityTemplate->setAdditionalUnit('1.000');
        $quantityTemplate->setAdditionalUnitFee('5.00');

        // 创建免邮模板
        $freeTemplate = new ShippingTemplate();
        $freeTemplate->setName('全场包邮模板');
        $freeTemplate->setDescription('全场商品包邮');
        $freeTemplate->setChargeType(ChargeType::WEIGHT);
        $freeTemplate->setIsDefault(false);
        $freeTemplate->setStatus(ShippingTemplateStatus::ACTIVE);
        $freeTemplate->setFreeShippingThreshold('0.01');
        $freeTemplate->setFirstUnit('1.000');
        $freeTemplate->setFirstUnitFee('0.00');
        $freeTemplate->setAdditionalUnit('1.000');
        $freeTemplate->setAdditionalUnitFee('0.00');

        $defaultTemplate->addArea($guangdongArea);
        $defaultTemplate->addArea($shenzhenArea);

        $manager->persist($defaultTemplate);
        $manager->persist($quantityTemplate);
        $manager->persist($freeTemplate);
        $manager->persist($guangdongArea);
        $manager->persist($shenzhenArea);

        $manager->flush();

        // 添加引用以供其他 Fixtures 使用
        $this->addReference(self::SHIPPING_TEMPLATE_DEFAULT_REFERENCE, $defaultTemplate);
        $this->addReference(self::SHIPPING_TEMPLATE_QUANTITY_REFERENCE, $quantityTemplate);
        $this->addReference(self::SHIPPING_TEMPLATE_FREE_REFERENCE, $freeTemplate);
    }
}
