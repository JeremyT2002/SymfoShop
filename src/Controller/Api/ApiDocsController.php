<?php

namespace App\Controller\Api;

use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/docs', name: 'api_docs')]
class ApiDocsController extends AbstractController
{
    public function __construct(
        private readonly RenderOpenApi $renderOpenApi
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Get the OpenAPI spec from nelmio
        $openApiSpec = $this->renderOpenApi->render(RenderOpenApi::JSON, 'default');
        
        return $this->render('api/swagger_ui.html.twig', [
            'openApiSpec' => $openApiSpec,
        ]);
    }
}

