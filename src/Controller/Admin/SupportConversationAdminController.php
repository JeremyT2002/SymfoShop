<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SupportConversation;
use App\Entity\User;
use App\Repository\SupportConversationRepository;
use App\Service\Support\SupportAttachmentService;
use App\Service\Support\SupportChatService;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/support', name: 'admin_support_')]
final class SupportConversationAdminController extends AbstractController
{
    public function __construct(
        private readonly SupportConversationRepository $conversationRepository,
        private readonly SupportChatService $supportChatService,
        private readonly SupportAttachmentService $supportAttachmentService,
        private readonly ShopContextResolver $shopContextResolver,
        private readonly ThemeResolver $themeResolver,
        #[Autowire(service: 'limiter.support_message_limiter')]
        private readonly RateLimiterFactory $supportMessageLimiter,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        if (($redirect = $this->redirectUnlessSelfcodedSupportEnabled()) !== null) {
            return $redirect;
        }

        return $this->render('admin/support/index.html.twig', [
            'conversations' => $this->conversationRepository->findForAdminInbox(300),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(SupportConversation $conversation, Request $request): Response
    {
        if (($redirect = $this->redirectUnlessSelfcodedSupportEnabled()) !== null) {
            return $redirect;
        }

        if ($request->isMethod('POST')) {
            $body = trim((string) $request->request->get('body', ''));
            $close = $request->request->getBoolean('closeConversation', false);
            $user = $this->getUser();
            $limiter = $this->supportMessageLimiter->create('support:admin:' . ($user instanceof User ? (string) $user->getId() : 'anon'));
            $limit = $limiter->consume(1);
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'support.flash.rate_limited');
                return $this->redirectToRoute('admin_support_show', ['id' => $conversation->getId()]);
            }

            if ($body !== '' && $user instanceof User) {
                $message = $this->supportChatService->addSupportMessage($conversation, $user, $body);
                try {
                    /** @var array<\Symfony\Component\HttpFoundation\File\UploadedFile|null> $uploaded */
                    $uploaded = $request->files->all('attachments');
                    $this->supportAttachmentService->storeForMessage($message, $uploaded);
                } catch (\RuntimeException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
                $this->addFlash('success', 'support.flash.reply_sent');
            }

            if ($close) {
                $this->supportChatService->closeConversation($conversation);
                $this->addFlash('info', 'support.flash.conversation_closed');
            }

            return $this->redirectToRoute('admin_support_show', ['id' => $conversation->getId()]);
        }

        $messages = $this->supportChatService->listMessages($conversation);
        $this->supportChatService->markConversationReadForSupport($conversation);

        return $this->render('admin/support/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    private function redirectUnlessSelfcodedSupportEnabled(): ?RedirectResponse
    {
        $shop = $this->shopContextResolver->resolve();
        $themeConfig = $this->themeResolver->resolveConfig($shop);
        $provider = (string) (($themeConfig['support']['provider'] ?? 'disabled'));
        if ($provider !== 'selfcoded') {
            $this->addFlash('warning', 'Support is currently disabled. Switch provider to "Selfcoded" in settings.');

            return $this->redirectToRoute('admin_settings');
        }

        return null;
    }
}

