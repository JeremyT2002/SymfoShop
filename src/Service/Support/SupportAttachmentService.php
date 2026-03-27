<?php

declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportAttachment;
use App\Entity\SupportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class SupportAttachmentService
{
    private const MAX_BYTES = 10_000_000; // 10 MB per file
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'text/plain',
    ];
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'txt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @param UploadedFile[] $files
     */
    public function storeForMessage(SupportMessage $message, array $files): void
    {
        if ($files === []) {
            return;
        }

        $targetDir = $this->kernel->getProjectDir() . '/var/support_uploads';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $size = $file->getSize();
            if (!is_int($size) || $size <= 0 || $size > self::MAX_BYTES) {
                throw new \RuntimeException('Attachment exceeds max size (10 MB).');
            }

            $extension = mb_strtolower((string) $file->guessExtension());
            $mimeType = (string) $file->getMimeType();
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true) || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                throw new \RuntimeException('Attachment type is not allowed.');
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $file->move($targetDir, $storedName);

            $attachment = (new SupportAttachment())
                ->setMessage($message)
                ->setOriginalName((string) $file->getClientOriginalName())
                ->setStoredName($storedName)
                ->setMimeType($mimeType)
                ->setSizeBytes($size);

            $this->entityManager->persist($attachment);
        }

        $this->entityManager->flush();
    }

    public function getAbsolutePath(SupportAttachment $attachment): string
    {
        return $this->kernel->getProjectDir() . '/var/support_uploads/' . $attachment->getStoredName();
    }
}

