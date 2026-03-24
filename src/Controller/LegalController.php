<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal', name: 'legal_')]
final class LegalController extends AbstractController
{
    #[Route('/privacy', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/cookies', name: 'cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }

    #[Route('/returns', name: 'returns', methods: ['GET'])]
    public function returns(): Response
    {
        return $this->render('legal/returns.html.twig');
    }

    #[Route('/terms', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }

    #[Route('/imprint', name: 'imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('legal/imprint.html.twig');
    }
}
