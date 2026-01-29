<?php

namespace App\Controller\Admin;

use App\Entity\ApiKey;
use App\Service\Api\ApiKeyService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;

class ApiKeyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly RequestStack $requestStack
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
            ->setRequired(true)
            ->setHelp('The user who will own this API key')
            ->formatValue(function ($value, $entity) {
                return $value ? $value->getEmail() : '';
            })
            ->setFormTypeOption('choice_label', 'email');
        yield TextField::new('name')
            ->setRequired(true)
            ->setHelp('A descriptive name for this API key (e.g., "Production API", "Mobile App")');
        
        // Show plain API key only on detail page after creation
        yield TextField::new('plainApiKey')
            ->onlyOnDetail()
            ->setLabel('API Key')
            ->setHelp('⚠️ IMPORTANT: Save this key now! It will not be shown again.')
            ->formatValue(function ($value) {
                if ($value) {
                    return '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px; word-break: break-all;"><strong>' . htmlspecialchars($value) . '</strong></div>';
                }
                return '<em style="color: #6c757d;">Key is not available after creation</em>';
            });
        
        yield TextField::new('keyHash')
            ->onlyOnIndex()
            ->setHelp('SHA256 hash of the API key');
        yield BooleanField::new('isActive')
            ->setHelp('Inactive keys cannot be used for authentication')
            ->renderAsSwitch(false);
        yield DateTimeField::new('createdAt')
            ->onlyOnIndex();
        yield DateTimeField::new('expiresAt')
            ->setHelp('Leave empty for keys that never expire');
        yield DateTimeField::new('lastUsedAt')
            ->onlyOnIndex();
        yield TextField::new('scopes')
            ->setHelp('JSON array of allowed scopes (leave empty for all access). Format: ["read:products", "read:orders"]')
            ->formatValue(function ($value) {
                if (is_array($value)) {
                    return json_encode($value, JSON_PRETTY_PRINT);
                }
                if (is_string($value) && !empty($value)) {
                    return $value;
                }
                return 'All access';
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var ApiKey $apiKey */
        $apiKey = $entityInstance;
        
        // Don't persist the entity directly - we need to generate the key first
        // Parse scopes if provided as JSON string
        $scopes = null;
        if ($apiKey->getScopes()) {
            if (is_string($apiKey->getScopes())) {
                $decoded = json_decode($apiKey->getScopes(), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $scopes = $decoded;
                }
            } elseif (is_array($apiKey->getScopes())) {
                $scopes = $apiKey->getScopes();
            }
        }

        // Generate the actual API key using the service (this persists the entity)
        $plainApiKey = $this->apiKeyService->generateApiKey(
            $apiKey->getUser(),
            $apiKey->getName(),
            $apiKey->getExpiresAt(),
            $scopes
        );

        // Reload the entity to get the generated one
        $createdApiKey = $entityManager->getRepository(ApiKey::class)->findOneBy(
            ['user' => $apiKey->getUser(), 'name' => $apiKey->getName()],
            ['id' => 'DESC']
        );

        if ($createdApiKey) {
            // Store the plain key in session to display it after redirect
            $session = $this->requestStack->getSession();
            $session->set('api_key_plain_' . $createdApiKey->getId(), $plainApiKey);
        }
        
        $this->addFlash('success', 'API key created successfully! View the details to see the key - save it now as it will not be shown again.');
    }

    public function detail(AdminContext $context): Response
    {
        $entity = $context->getEntity()->getInstance();
        
        // Check if we just created this entity and have the plain key in session
        if ($entity instanceof ApiKey) {
            $session = $this->requestStack->getSession();
            $plainKey = $session->get('api_key_plain_' . $entity->getId());
            
            if ($plainKey) {
                $entity->setPlainApiKey($plainKey);
                // Remove from session after displaying
                $session->remove('api_key_plain_' . $entity->getId());
            }
        }
        
        return parent::detail($context);
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

