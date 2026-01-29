<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/docs', name: 'api_docs')]
class ApiDocsController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('api/docs.html.twig');
    }
}

