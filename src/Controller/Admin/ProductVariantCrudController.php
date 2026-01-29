<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\JsonField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductVariantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Product Variant')
            ->setEntityLabelInPlural('Product Variants')
            ->setPageTitle('index', 'Product Variants')
            ->setPageTitle('new', 'Create Product Variant')
            ->setPageTitle('edit', 'Edit Product Variant')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['sku', 'product.name']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('product')
            ->add('sku')
            ->add('currency');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield AssociationField::new('product')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('Select the parent product');

        yield TextField::new('sku')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('Unique Stock Keeping Unit identifier');

        yield MoneyField::new('priceAmount', 'Price')
            ->setCurrencyPropertyPath('currency')
            ->setStoredAsCents(true)
            ->setColumns('col-md-4')
            ->setRequired(true)
            ->setHelp('Price in cents (e.g., 1999 = €19.99)');

        yield TextField::new('currency')
            ->setColumns('col-md-2')
            ->setRequired(true)
            ->setHelp('Currency code (EUR, USD, etc.)');

        yield JsonField::new('attributes')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Variant attributes (size, color, etc.) as JSON');

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

