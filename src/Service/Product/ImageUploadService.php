<?php

namespace App\Service\Product;

use App\Entity\Product;
use App\Entity\ProductMedia;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    
    private const MAX_FILE_SIZE = 5242880; // 5MB in bytes
    
    public function __construct(
        private readonly string $uploadsDirectory,
        private readonly SluggerInterface $slugger
    ) {
    }

    /**
     * Upload and create ProductMedia for uploaded files
     * 
     * @param Product $product
     * @param UploadedFile[] $files
     * @return ProductMedia[]
     * @throws \Exception
     */
    public function uploadImages(Product $product, array $files): array
    {
        $uploadedMedia = [];
        $sortOrder = $product->getMedia()->count();
        
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            
            // Validate file
            $this->validateFile($file);
            
            // Generate unique filename
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            
            // Create directory if it doesn't exist
            $productDir = $this->getProductUploadDirectory($product);
            if (!is_dir($productDir)) {
                mkdir($productDir, 0755, true);
            }
            
            // Move file
            try {
                $file->move($productDir, $newFilename);
            } catch (FileException $e) {
                throw new \Exception('Failed to upload file: ' . $e->getMessage());
            }
            
            // Create ProductMedia entity
            $media = new ProductMedia();
            $media->setProduct($product);
            $media->setPath($this->getRelativePath($product, $newFilename));
            $media->setAlt($product->getName() . ' - Image ' . ($sortOrder + 1));
            $media->setSort($sortOrder);
            
            $uploadedMedia[] = $media;
            $sortOrder++;
        }
        
        return $uploadedMedia;
    }

    /**
     * Delete a media file from filesystem
     */
    public function deleteMediaFile(ProductMedia $media): void
    {
        $filePath = $this->getAbsolutePath($media->getPath());
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Try to remove empty product directory
        $productDir = dirname($filePath);
        if (is_dir($productDir) && count(scandir($productDir)) === 2) { // Only . and ..
            rmdir($productDir);
        }
    }

    /**
     * Validate uploaded file
     * 
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File size exceeds maximum allowed size of 5MB');
        }
        
        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \Exception('Invalid file type. Allowed types: JPEG, PNG, GIF, WebP');
        }
        
        // Check if file is actually an image
        if (!@getimagesize($file->getPathname())) {
            throw new \Exception('File is not a valid image');
        }
    }

    /**
     * Get upload directory for a product
     */
    private function getProductUploadDirectory(Product $product): string
    {
        return $this->uploadsDirectory . '/products/' . $product->getId();
    }

    /**
     * Get relative path for storage in database
     */
    private function getRelativePath(Product $product, string $filename): string
    {
        return '/uploads/products/' . $product->getId() . '/' . $filename;
    }

    /**
     * Get absolute path from relative path
     */
    private function getAbsolutePath(string $relativePath): string
    {
        // Remove leading slash if present
        $relativePath = ltrim($relativePath, '/');
        return $this->uploadsDirectory . '/' . $relativePath;
    }
}

