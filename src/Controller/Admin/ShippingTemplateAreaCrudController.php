<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;

#[AdminCrud(routePath: '/order-checkout/shipping-template-area', routeName: 'order_checkout_shipping_template_area')]
final class ShippingTemplateAreaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ShippingTemplateArea::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('配送模板区域')
            ->setEntityLabelInPlural('配送模板区域')
            ->setSearchFields(['provinceName', 'cityName', 'areaName', 'provinceCode', 'cityCode', 'areaCode'])
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setColumns(2)
        ;

        yield AssociationField::new('shippingTemplate', '配送模板')
            ->setRequired(true)
            ->setHelp('关联的配送模板')
            ->setColumns(4)
        ;

        yield TextField::new('provinceCode', '省份代码')
            ->setRequired(true)
            ->setMaxLength(10)
            ->setHelp('省份行政区划代码')
            ->setColumns(2)
        ;

        yield TextField::new('provinceName', '省份名称')
            ->setRequired(true)
            ->setMaxLength(50)
            ->setHelp('省份名称')
            ->setColumns(3)
        ;

        yield TextField::new('cityCode', '城市代码')
            ->setRequired(false)
            ->setMaxLength(10)
            ->setHelp('城市行政区划代码，为空表示全省')
            ->setColumns(2)
        ;

        yield TextField::new('cityName', '城市名称')
            ->setRequired(false)
            ->setMaxLength(50)
            ->setHelp('城市名称')
            ->setColumns(3)
        ;

        yield TextField::new('areaCode', '区县代码')
            ->setRequired(false)
            ->setMaxLength(10)
            ->setHelp('区县行政区划代码，为空表示全市')
            ->setColumns(2)
            ->hideOnIndex()
        ;

        yield TextField::new('areaName', '区县名称')
            ->setRequired(false)
            ->setMaxLength(50)
            ->setHelp('区县名称')
            ->setColumns(3)
            ->hideOnIndex()
        ;

        yield NumberField::new('firstUnit', '区域首重/首件')
            ->setRequired(false)
            ->setHelp('区域首重或首件数量')
            ->setColumns(3)
            ->setNumDecimals(3)
        ;

        yield MoneyField::new('firstUnitFee', '区域首重/首件运费')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('区域首重或首件运费')
            ->setColumns(3)
            ->setNumDecimals(2)
        ;

        yield NumberField::new('additionalUnit', '区域续重/续件')
            ->setRequired(false)
            ->setHelp('区域续重或续件数量')
            ->setColumns(3)
            ->setNumDecimals(3)
            ->hideOnIndex()
        ;

        yield MoneyField::new('additionalUnitFee', '区域续重/续件运费')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('区域续重或续件运费')
            ->setColumns(3)
            ->setNumDecimals(2)
            ->hideOnIndex()
        ;

        yield MoneyField::new('freeShippingThreshold', '区域包邮门槛')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('区域包邮门槛金额')
            ->setColumns(3)
            ->setNumDecimals(2)
            ->hideOnIndex()
        ;

        yield BooleanField::new('isDeliverable', '支持配送')
            ->setRequired(true)
            ->setHelp('是否支持配送到该区域')
            ->setColumns(2)
        ;

        yield CodeEditorField::new('extendedConfig', '区域扩展配置')
            ->setLanguage('javascript')
            ->setRequired(false)
            ->setHelp('区域扩展配置信息（JSON格式）')
            ->setColumns(6)
            ->hideOnIndex()
            ->formatValue(function ($value) {
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value;
            })
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(3)
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(3)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('provinceName', '省份'))
            ->add(TextFilter::new('cityName', '城市'))
            ->add(TextFilter::new('areaName', '区县'))
            ->add(BooleanFilter::new('isDeliverable', '支持配送'))
        ;
    }
}
