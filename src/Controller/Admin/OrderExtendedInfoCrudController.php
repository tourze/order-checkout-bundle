<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;

#[AdminCrud(routePath: '/order-checkout/order-extended-info', routeName: 'order_checkout_order_extended_info')]
final class OrderExtendedInfoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OrderExtendedInfo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('订单扩展信息')
            ->setEntityLabelInPlural('订单扩展信息')
            ->setSearchFields(['orderId', 'infoType', 'infoKey', 'infoValue'])
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setColumns(2)
        ;

        yield IntegerField::new('orderId', '订单ID')
            ->setRequired(true)
            ->setHelp('关联的订单ID')
            ->setColumns(3)
        ;

        yield TextField::new('infoType', '信息类型')
            ->setRequired(true)
            ->setMaxLength(50)
            ->setHelp('信息类型，如：remark备注')
            ->setColumns(3)
        ;

        yield TextField::new('infoKey', '信息键名')
            ->setRequired(true)
            ->setMaxLength(100)
            ->setHelp('信息键名，如：customer_remark')
            ->setColumns(3)
        ;

        yield TextareaField::new('infoValue', '信息内容')
            ->setRequired(true)
            ->setHelp('信息内容值')
            ->setColumns(6)
            ->setNumOfRows(3)
        ;

        yield TextareaField::new('originalValue', '原始内容')
            ->setRequired(false)
            ->setHelp('过滤前的原始内容')
            ->setColumns(6)
            ->setNumOfRows(3)
            ->hideOnIndex()
        ;

        yield BooleanField::new('isFiltered', '已过滤')
            ->setRequired(true)
            ->setHelp('是否已过滤敏感词')
            ->setColumns(2)
        ;

        yield TextareaField::new('filteredWords', '过滤词列表')
            ->setRequired(false)
            ->setHelp('被过滤的敏感词列表（JSON格式）')
            ->setColumns(6)
            ->setNumOfRows(2)
            ->hideOnIndex()
            ->formatValue(function ($value) {
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
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
            ->add(TextFilter::new('orderId', '订单ID'))
            ->add(TextFilter::new('infoType', '信息类型'))
            ->add(TextFilter::new('infoKey', '信息键名'))
            ->add(BooleanFilter::new('isFiltered', '已过滤'))
        ;
    }
}
