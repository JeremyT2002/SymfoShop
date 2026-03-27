<?php

declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportConversation;
use App\Entity\SupportMessage;
use App\Entity\User;
use App\Repository\SupportConversationRepository;
use App\Repository\SupportMessageRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SupportChatService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SupportConversationRepository $conversationRepository,
        private readonly SupportMessageRepository $messageRepository,
    ) {
    }

    public function createConversation(
        User $customer,
        string $subject,
        string $initialMessage,
        string $category = SupportConversation::CATEGORY_OTHER,
        ?string $relatedOrderNumber = null
    ): SupportConversation
    {
        $conversation = (new SupportConversation())
            ->setCustomer($customer)
            ->setSubject($subject)
            ->setCategory($category)
            ->setRelatedOrderNumber($relatedOrderNumber)
            ->setStatus(SupportConversation::STATUS_OPEN);

        $message = (new SupportMessage())
            ->setConversation($conversation)
            ->setSenderType(SupportMessage::SENDER_CUSTOMER)
            ->setSenderUser($customer)
            ->setBody($initialMessage);

        $conversation->setSupporterUnreadCount(1)->setCustomerUnreadCount(0)->touch();

        $this->entityManager->persist($conversation);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $conversation;
    }

    public function addCustomerMessage(SupportConversation $conversation, User $customer, string $body): SupportMessage
    {
        if ($conversation->getCustomer()?->getId() !== $customer->getId()) {
            throw new \RuntimeException('Conversation does not belong to this customer.');
        }

        $message = (new SupportMessage())
            ->setConversation($conversation)
            ->setSenderType(SupportMessage::SENDER_CUSTOMER)
            ->setSenderUser($customer)
            ->setBody($body);

        $conversation
            ->setStatus(SupportConversation::STATUS_OPEN)
            ->setSupporterUnreadCount($conversation->getSupporterUnreadCount() + 1)
            ->setCustomerUnreadCount(0)
            ->touch();

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function addSupportMessage(SupportConversation $conversation, User $supporter, string $body): SupportMessage
    {
        $message = (new SupportMessage())
            ->setConversation($conversation)
            ->setSenderType(SupportMessage::SENDER_SUPPORT)
            ->setSenderUser($supporter)
            ->setBody($body);

        $conversation
            ->setCustomerUnreadCount($conversation->getCustomerUnreadCount() + 1)
            ->setSupporterUnreadCount(0)
            ->touch();

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * @return list<SupportMessage>
     */
    public function listMessages(SupportConversation $conversation, ?int $afterId = null, int $limit = 200): array
    {
        return $this->messageRepository->findForConversation($conversation, $afterId, $limit);
    }

    public function markConversationReadForCustomer(SupportConversation $conversation): void
    {
        $conversation->setCustomerUnreadCount(0)->touch();
        $this->entityManager->flush();
    }

    public function markConversationReadForSupport(SupportConversation $conversation): void
    {
        $conversation->setSupporterUnreadCount(0)->touch();
        $this->entityManager->flush();
    }

    public function closeConversation(SupportConversation $conversation): void
    {
        $conversation->setStatus(SupportConversation::STATUS_CLOSED)->touch();
        $this->entityManager->flush();
    }
}

