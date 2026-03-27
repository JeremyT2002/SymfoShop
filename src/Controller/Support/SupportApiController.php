<?php

declare(strict_types=1);

namespace App\Controller\Support;

use App\Entity\SupportConversation;
use App\Entity\SupportMessage;
use App\Entity\User;
use App\Repository\SupportConversationRepository;
use App\Service\Support\SupportChatService;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/api', name: 'support_api_')]
#[IsGranted('ROLE_USER')]
final class SupportApiController extends AbstractController
{
    public function __construct(
        private readonly SupportChatService $supportChatService,
        private readonly SupportConversationRepository $conversationRepository,
        private readonly ShopContextResolver $shopContextResolver,
        private readonly ThemeResolver $themeResolver,
    ) {
    }

    #[Route('/conversations/{id}/messages', name: 'messages', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function messages(SupportConversation $conversation, Request $request): JsonResponse
    {
        $this->denyUnlessSelfcodedSupportEnabled();
        $this->denyConversationAccess($conversation);

        $afterId = $request->query->has('afterId') ? (int) $request->query->get('afterId') : null;
        if ($afterId !== null && $afterId <= 0) {
            $afterId = null;
        }

        $messages = $this->supportChatService->listMessages($conversation, $afterId);

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if ($isAdmin) {
            $this->supportChatService->markConversationReadForSupport($conversation);
        } else {
            $this->supportChatService->markConversationReadForCustomer($conversation);
        }

        return $this->json([
            'conversationId' => $conversation->getId(),
            'messages' => array_map(fn (SupportMessage $m) => $this->serializeMessage($m), $messages),
            'status' => $conversation->getStatus(),
            'customerUnreadCount' => $conversation->getCustomerUnreadCount(),
            'supporterUnreadCount' => $conversation->getSupporterUnreadCount(),
        ]);
    }

    #[Route('/unread-summary', name: 'unread_summary', methods: ['GET'])]
    public function unreadSummary(): JsonResponse
    {
        $this->denyUnlessSelfcodedSupportEnabled();
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthenticated'], 401);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $conversations = $this->conversationRepository->findForAdminInbox(500);
            $unread = 0;
            foreach ($conversations as $conversation) {
                $unread += $conversation->getSupporterUnreadCount();
            }

            return $this->json(['unread' => $unread]);
        }

        $conversations = $this->conversationRepository->findForCustomer($user, 500);
        $unread = 0;
        foreach ($conversations as $conversation) {
            $unread += $conversation->getCustomerUnreadCount();
        }

        return $this->json(['unread' => $unread]);
    }

    private function denyConversationAccess(SupportConversation $conversation): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();
        if (!$user instanceof User || $conversation->getCustomer()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have access to this conversation.');
        }
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

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(SupportMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'senderType' => $message->getSenderType(),
            'senderUser' => $message->getSenderUser()?->getEmail(),
            'body' => $message->getBody(),
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'attachments' => array_map(
                static fn ($a) => [
                    'id' => $a->getId(),
                    'name' => $a->getOriginalName(),
                ],
                $message->getAttachments()->toArray()
            ),
        ];
    }
}

