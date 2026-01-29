<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductMedia;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductMediaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductMedia::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Product Media')
            ->setEntityLabelInPlural('Product Media')
            ->setPageTitle('index', 'Product Media')
            ->setPageTitle('new', 'Create Product Media')
            ->setPageTitle('edit', 'Edit Product Media')
            ->setDefaultSort(['product' => 'ASC', 'sort' => 'ASC'])
            ->setSearchFields(['path', 'alt', 'product.name']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('product')
            ->add('sort');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield AssociationField::new('product')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('Select the product this media belongs to');

        yield TextField::new('path')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('Path to the media file (e.g., /uploads/products/image.jpg)');

        yield TextField::new('alt')
            ->setColumns('col-md-6')
            ->setRequired(false)
            ->setHelp('Alternative text for accessibility');

        yield IntegerField::new('sort')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('Sort order (lower numbers appear first)');
    }
}

