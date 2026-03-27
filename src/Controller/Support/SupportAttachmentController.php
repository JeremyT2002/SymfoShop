<?php

declare(strict_types=1);

namespace App\Controller\Support;

use App\Entity\SupportAttachment;
use App\Entity\User;
use App\Service\Support\SupportAttachmentService;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/attachment', name: 'support_attachment_')]
#[IsGranted('ROLE_USER')]
final class SupportAttachmentController extends AbstractController
{
    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
        private readonly ThemeResolver $themeResolver,
    ) {
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(SupportAttachment $attachment, SupportAttachmentService $attachmentService): BinaryFileResponse
    {
        $this->denyUnlessSelfcodedSupportEnabled();

        $conversation = $attachment->getMessage()?->getConversation();
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            if (!$user instanceof User || $conversation?->getCustomer()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException('You cannot access this attachment.');
            }
        }

        $path = $attachmentService->getAbsolutePath($attachment);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Attachment file not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getOriginalName()
        );

        return $response;
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

