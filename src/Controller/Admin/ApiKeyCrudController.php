<?php

namespace App\Controller\Admin;

use App\Entity\ApiKey;
use App\Service\Api\ApiKeyService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ApiKey::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('API Key')
            ->setEntityLabelInPlural('API Keys')
            ->setSearchFields(['name', 'user.email'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('user')
            ->setRequired(true);
        yield TextField::new('name')
            ->setRequired(true);
        yield TextField::new('keyHash')
            ->onlyOnIndex()
            ->setHelp('SHA256 hash of the API key');
        yield BooleanField::new('isActive')
            ->setHelp('Inactive keys cannot be used for authentication');
        yield DateTimeField::new('createdAt')
            ->onlyOnIndex();
        yield DateTimeField::new('expiresAt')
            ->setHelp('Leave empty for keys that never expire');
        yield DateTimeField::new('lastUsedAt')
            ->onlyOnIndex();
        yield TextField::new('scopes')
            ->setHelp('JSON array of allowed scopes (leave empty for all access)')
            ->formatValue(function ($value) {
                return $value ? json_encode($value, JSON_PRETTY_PRINT) : 'All access';
            });
    }

    public function configureActions(Actions $actions): Actions
    {
        $revokeAction = Action::new('revoke', 'Revoke')
            ->linkToCrudAction('revoke')
            ->setCssClass('btn btn-warning')
            ->displayIf(fn(ApiKey $entity) => $entity->isActive());

        return $actions
            ->add(Crud::PAGE_INDEX, $revokeAction)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user'))
            ->add(BooleanFilter::new('isActive'))
            ->add('createdAt')
            ->add('expiresAt');
    }

    public function revoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entityId = $request->query->get('entityId');
        $apiKey = $entityManager->getRepository(ApiKey::class)->find($entityId);

        if (!$apiKey) {
            $this->addFlash('error', 'API key not found');
            return $this->redirect($request->headers->get('referer'));
        }

        $this->apiKeyService->revokeApiKey($apiKey);
        $this->addFlash('success', 'API key revoked successfully');

        return $this->redirect($request->headers->get('referer'));
    }
}

