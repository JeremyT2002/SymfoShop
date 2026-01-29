<?php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class AuditLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Audit Log')
            ->setEntityLabelInPlural('Audit Logs')
            ->setPageTitle('index', 'Audit Logs')
            ->setPageTitle('detail', 'Audit Log Details')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('entityType')
            ->add('action')
            ->add('changedField')
            ->add('userIdentifier')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield ChoiceField::new('entityType', 'Entity Type')
            ->setChoices([
                'Order' => 'Order',
                'Product' => 'Product',
            ])
            ->setColumns('col-md-3');

        yield TextField::new('entityId', 'Entity ID')
            ->setColumns('col-md-2')
            ->hideOnIndex();

        yield ChoiceField::new('action', 'Action')
            ->setChoices([
                'Update' => 'update',
                'Create' => 'create',
                'Delete' => 'delete',
            ])
            ->setColumns('col-md-2');

        yield TextField::new('changedField', 'Changed Field')
            ->setColumns('col-md-3')
            ->hideOnIndex();

        yield TextField::new('userIdentifier', 'User')
            ->setColumns('col-md-3')
            ->hideOnIndex();

        yield TextareaField::new('oldValue', 'Old Value')
            ->setColumns('col-md-6')
            ->onlyOnDetail()
            ->setMaxLength(500);

        yield TextareaField::new('newValue', 'New Value')
            ->setColumns('col-md-6')
            ->onlyOnDetail()
            ->setMaxLength(500);

        yield DateTimeField::new('createdAt', 'Created At')
            ->setColumns('col-md-4')
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }
}

