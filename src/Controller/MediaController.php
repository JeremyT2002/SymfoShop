<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Media\ResponsiveImages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Only reached when the resized image does not exist yet: renders it into public/media,
 * from where the web server serves all later requests.
 */
final class MediaController extends AbstractController
{
    #[Route('/media/{width}/{source}.webp', name: 'media_image', methods: ['GET'], requirements: ['width' => '\d+', 'source' => 'uploads/.+'])]
    public function image(int $width, string $source, ResponsiveImages $images): Response
    {
        $file = $images->render($width, $source);
        if ($file === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'image/webp']);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }
}
