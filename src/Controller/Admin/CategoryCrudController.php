<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Category')
            ->setEntityLabelInPlural('Categories')
            ->setPageTitle('index', 'Categories')
            ->setPageTitle('new', 'Create Category')
            ->setPageTitle('edit', 'Edit Category')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('parent')
            ->add('name')
            ->add('slug');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield TextField::new('name')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('Category name');

        yield SlugField::new('slug')
            ->setTargetFieldName('name')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('URL-friendly identifier');

        yield AssociationField::new('parent')
            ->setColumns('col-md-12')
            ->setRequired(false)
            ->autocomplete()
            ->setHelp('Select a parent category to create a hierarchical structure (leave empty for root category)');

        yield AssociationField::new('children')
            ->setColumns('col-md-12')
            ->onlyOnDetail()
            ->setHelp('Child categories');
    }
}

