<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Product')
            ->setEntityLabelInPlural('Products')
            ->setPageTitle('index', 'Products')
            ->setPageTitle('new', 'Create Product')
            ->setPageTitle('edit', 'Edit Product')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('name')
            ->add('slug')
            ->add('taxClass');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield TextField::new('name')
            ->setColumns('col-md-8')
            ->setRequired(true);

        yield TextField::new('slug')
            ->setColumns('col-md-4')
            ->setRequired(true)
            ->hideOnForm()
            ->setHelp('URL-friendly identifier');

        yield SlugField::new('slug')
            ->setTargetFieldName('name')
            ->setColumns('col-md-4')
            ->onlyOnForms()
            ->setRequired(true);

        yield ChoiceField::new('status')
            ->setChoices([
                'Draft' => ProductStatus::DRAFT,
                'Active' => ProductStatus::ACTIVE,
                'Archived' => ProductStatus::ARCHIVED,
            ])
            ->setColumns('col-md-4')
            ->setRequired(true);

        yield TextField::new('taxClass')
            ->setColumns('col-md-4')
            ->setRequired(true)
            ->setHelp('Tax class identifier (e.g., standard, reduced, zero)');

        yield TextareaField::new('description')
            ->setColumns('col-md-12')
            ->hideOnIndex()
            ->setRequired(false);

        yield AssociationField::new('variants')
            ->setColumns('col-md-12')
            ->onlyOnDetail()
            ->setHelp('Manage variants in the Product Variants section or edit this product');

        yield AssociationField::new('media')
            ->setColumns('col-md-12')
            ->onlyOnDetail()
            ->setHelp('Manage media in the Product Media section or edit this product');

        yield DateTimeField::new('createdAt')
            ->setColumns('col-md-6')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield DateTimeField::new('updatedAt')
            ->setColumns('col-md-6')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }
}

