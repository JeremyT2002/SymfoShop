<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setPageTitle('index', 'Users')
            ->setPageTitle('new', 'Create User')
            ->setPageTitle('edit', 'Edit User')
            ->setPageTitle('detail', 'User Details')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('email')
            ->add('isActive')
            ->add('roles')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield EmailField::new('email')
            ->setColumns('col-md-6')
            ->setRequired(true);

        yield TextField::new('firstName', 'First Name')
            ->setColumns('col-md-3')
            ->hideOnIndex();

        yield TextField::new('lastName', 'Last Name')
            ->setColumns('col-md-3')
            ->hideOnIndex();

        yield TextField::new('password', 'Password')
            ->setColumns('col-md-6')
            ->onlyWhenCreating()
            ->setRequired(true)
            ->setHelp('Enter a new password. Leave empty when editing to keep current password.')
            ->formatValue(function ($value, $entity) {
                return '';
            });

        yield ArrayField::new('roles', 'Roles')
            ->setColumns('col-md-6')
            ->setHelp('Available roles: ROLE_USER, ROLE_ADMIN')
            ->formatValue(function ($value) {
                return implode(', ', $value ?? []);
            });

        yield BooleanField::new('isActive', 'Active')
            ->setColumns('col-md-3');

        yield DateTimeField::new('createdAt', 'Created At')
            ->setColumns('col-md-4')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield DateTimeField::new('lastLoginAt', 'Last Login')
            ->setColumns('col-md-4')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }

    public function persistEntity(\Doctrine\ORM\EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $user */
        $user = $entityInstance;

        // Hash password if it's set
        if ($user->getPassword() && !str_starts_with($user->getPassword(), '$')) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $user->getPassword())
            );
        }

        parent::persistEntity($entityManager, $user);
    }

    public function updateEntity(\Doctrine\ORM\EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $user */
        $user = $entityInstance;

        // Only hash password if it's been changed (not already hashed)
        $plainPassword = $this->getContext()->getRequest()->request->get('User')['password'] ?? null;
        if ($plainPassword && !str_starts_with($plainPassword, '$')) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $plainPassword)
            );
        }

        parent::updateEntity($entityManager, $user);
    }
}

