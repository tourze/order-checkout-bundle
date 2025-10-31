<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Controller;

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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;

#[AdminCrud(routePath: '/order-checkout/shipping-template', routeName: 'order_checkout_shipping_template')]
final class ShippingTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ShippingTemplate::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('物流配送模板')
            ->setEntityLabelInPlural('物流配送模板')
            ->setSearchFields(['name', 'description'])
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

        yield TextField::new('name', '模板名称')
            ->setRequired(true)
            ->setMaxLength(100)
            ->setHelp('物流配送模板名称')
            ->setColumns(4)
        ;

        yield TextareaField::new('description', '模板描述')
            ->setRequired(false)
            ->setMaxLength(500)
            ->setHelp('模板描述信息')
            ->setColumns(6)
            ->setNumOfRows(2)
            ->hideOnIndex()
        ;

        $chargeTypeField = EnumField::new('chargeType', '计费方式')
            ->setRequired(true)
            ->setHelp('计费方式：按重量、按件数、按体积')
            ->setColumns(3)
        ;
        $chargeTypeField->setEnumCases(ChargeType::cases());
        yield $chargeTypeField;

        yield BooleanField::new('isDefault', '默认模板')
            ->setRequired(true)
            ->setHelp('是否为默认配送模板')
            ->setColumns(2)
        ;

        $statusField = EnumField::new('status', '状态')
            ->setRequired(true)
            ->setHelp('模板状态：启用或禁用')
            ->setColumns(2)
        ;
        $statusField->setEnumCases(ShippingTemplateStatus::cases());
        yield $statusField;

        yield MoneyField::new('freeShippingThreshold', '包邮门槛')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('包邮门槛金额，为空表示不包邮')
            ->setColumns(3)
            ->setNumDecimals(2)
        ;

        yield NumberField::new('firstUnit', '首重/首件')
            ->setRequired(false)
            ->setHelp('首重或首件数量')
            ->setColumns(3)
            ->setNumDecimals(3)
        ;

        yield MoneyField::new('firstUnitFee', '首重/首件运费')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('首重或首件运费')
            ->setColumns(3)
            ->setNumDecimals(2)
        ;

        yield NumberField::new('additionalUnit', '续重/续件')
            ->setRequired(false)
            ->setHelp('续重或续件数量')
            ->setColumns(3)
            ->setNumDecimals(3)
            ->hideOnIndex()
        ;

        yield MoneyField::new('additionalUnitFee', '续重/续件运费')
            ->setCurrency('CNY')
            ->setRequired(false)
            ->setHelp('续重或续件运费')
            ->setColumns(3)
            ->setNumDecimals(2)
            ->hideOnIndex()
        ;

        yield CodeEditorField::new('extendedConfig', '扩展配置')
            ->setLanguage('javascript')
            ->setRequired(false)
            ->setHelp('扩展配置信息（JSON格式）')
            ->setColumns(6)
            ->hideOnIndex()
            ->formatValue(function ($value) {
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value;
            })
        ;

        yield AssociationField::new('areas', '配送区域')
            ->setHelp('关联的配送区域设置')
            ->hideOnForm()
            ->setColumns(6)
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
            ->add(TextFilter::new('name', '模板名称'))
            ->add(BooleanFilter::new('isDefault', '默认模板'))
        ;
    }
}
