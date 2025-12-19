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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\OrderCheckoutBundle\Entity\PayOrder;

#[AdminCrud(routePath: '/order/pay-order', routeName: 'order_pay_order')]
final class OrderPayOrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PayOrder::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('支付订单')
            ->setEntityLabelInPlural('支付订单列表')
            ->setPageTitle('index', '支付订单管理')
            ->setPageTitle('detail', '支付订单详情')
            ->setPageTitle('edit', '编辑支付订单')
            ->setPageTitle('new', '创建支付订单')
            ->setHelp('index', '管理所有支付订单信息，包括支付金额、交易号和支付时间')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'tradeNo', 'contract.sn'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield AssociationField::new('contract', '关联订单')
            ->setRequired(true)
            ->setHelp('选择要关联的订单契约')
            ->autocomplete()
        ;

        yield MoneyField::new('amount', '支付金额')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setHelp('支付的具体金额，单位为元')
        ;

        yield TextField::new('tradeNo', '交易号')
            ->setMaxLength(128)
            ->setHelp('第三方支付平台返回的交易号')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('payTime', '支付时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('实际支付完成的时间')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        yield TextField::new('createdBy', '创建人')
            ->hideOnForm()
            ->onlyOnDetail()
        ;

        yield TextField::new('updatedBy', '更新人')
            ->hideOnForm()
            ->onlyOnDetail()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('contract', '关联订单'))
            ->add(TextFilter::new('tradeNo', '交易号'))
            ->add(NumericFilter::new('amount', '支付金额'))
            ->add(DateTimeFilter::new('payTime', '支付时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(EntityFilter::new('createdBy', '创建人'))
        ;
    }
}
