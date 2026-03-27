<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\SupportConversation;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\SupportConversationRepository;
use App\Service\Support\SupportAttachmentService;
use App\Service\Support\SupportChatService;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account/support', name: 'account_support_')]
#[IsGranted('ROLE_USER')]
final class SupportController extends AbstractController
{
    public function __construct(
        private readonly SupportConversationRepository $conversationRepository,
        private readonly OrderRepository $orderRepository,
        private readonly SupportChatService $supportChatService,
        private readonly SupportAttachmentService $supportAttachmentService,
        private readonly ShopContextResolver $shopContextResolver,
        private readonly ThemeResolver $themeResolver,
        #[Autowire(service: 'limiter.support_message_limiter')]
        private readonly RateLimiterFactory $supportMessageLimiter,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessSelfcodedSupportEnabled();

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user session.');
        }
        $customerOrders = $this->orderRepository->findByCustomerEmail($user->getEmail() ?? '', 50);

        if ($request->isMethod('POST')) {
            $subject = trim((string) $request->request->get('subject', ''));
            $message = trim((string) $request->request->get('message', ''));
            $category = trim((string) $request->request->get('category', SupportConversation::CATEGORY_OTHER));
            $relatedOrderNumber = trim((string) $request->request->get('relatedOrderNumber', ''));
            $limiter = $this->supportMessageLimiter->create('support:new:' . ($user->getId() ?? 'anon'));
            $limit = $limiter->consume(1);
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'support.flash.rate_limited');
                return $this->redirectToRoute('account_support_index');
            }

            $allowedCategories = [
                SupportConversation::CATEGORY_ORDER,
                SupportConversation::CATEGORY_PAYMENT,
                SupportConversation::CATEGORY_SHIPPING,
                SupportConversation::CATEGORY_PRODUCT,
                SupportConversation::CATEGORY_TECHNICAL,
                SupportConversation::CATEGORY_OTHER,
            ];
            if (!in_array($category, $allowedCategories, true)) {
                $category = SupportConversation::CATEGORY_OTHER;
            }
            $allowedOrderNumbers = array_map(
                static fn (\App\Entity\Order $order): string => $order->getOrderNumber(),
                $customerOrders
            );
            if ($relatedOrderNumber !== '' && !in_array($relatedOrderNumber, $allowedOrderNumbers, true)) {
                $relatedOrderNumber = '';
            }

            if ($subject === '' || $message === '') {
                $this->addFlash('error', 'support.flash.subject_message_required');
            } else {
                $conversation = $this->supportChatService->createConversation(
                    $user,
                    $subject,
                    $message,
                    $category,
                    $relatedOrderNumber !== '' ? $relatedOrderNumber : null
                );
                $this->addFlash('success', 'support.flash.conversation_created');

                return $this->redirectToRoute('account_support_show', ['id' => $conversation->getId()]);
            }
        }

        return $this->render('account/support/index.html.twig', [
            'conversations' => $this->conversationRepository->findForCustomer($user),
            'orders' => $customerOrders,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(SupportConversation $conversation, Request $request): Response
    {
        $this->denyUnlessSelfcodedSupportEnabled();

        $user = $this->getUser();
        if (!$user instanceof User || $conversation->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this conversation.');
        }

        if ($request->isMethod('POST')) {
            $body = trim((string) $request->request->get('body', ''));
            $limiter = $this->supportMessageLimiter->create('support:reply:' . ($user->getId() ?? 'anon'));
            $limit = $limiter->consume(1);
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'support.flash.rate_limited');
                return $this->redirectToRoute('account_support_show', ['id' => $conversation->getId()]);
            }
            if ($body === '') {
                $this->addFlash('error', 'support.flash.message_required');
            } else {
                $message = $this->supportChatService->addCustomerMessage($conversation, $user, $body);
                try {
                    /** @var array<\Symfony\Component\HttpFoundation\File\UploadedFile|null> $uploaded */
                    $uploaded = $request->files->all('attachments');
                    $this->supportAttachmentService->storeForMessage($message, $uploaded);
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
                $this->addFlash('success', 'support.flash.message_sent');
                return $this->redirectToRoute('account_support_show', ['id' => $conversation->getId()]);
            }
        }

        $messages = $this->supportChatService->listMessages($conversation);
        $this->supportChatService->markConversationReadForCustomer($conversation);

        return $this->render('account/support/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    private function denyUnlessSelfcodedSupportEnabled(): void
    {
        $shop = $this->shopContextResolver->resolve();
        $themeConfig = $this->themeResolver->resolveConfig($shop);
        $provider = (string) (($themeConfig['support']['provider'] ?? 'disabled'));
        if ($provider !== 'selfcoded') {
            throw $this->createNotFoundException('Support is not available.');
        }
    }
}

