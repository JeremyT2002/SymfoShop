<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], priority: 20)]
    public function sitemap(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        $urls = [];
        $base = $request->getSchemeAndHttpHost();

        $staticRoutes = [
            'catalog_home',
            'catalog_products',
            'cart_show',
            'login',
            'register',
            'legal_privacy',
            'legal_cookies',
            'legal_returns',
            'legal_terms',
            'legal_imprint',
            'legal_faq',
            'contact',
            'return_request',
        ];
        foreach ($staticRoutes as $name) {
            $urls[] = [
                'loc' => $base . $this->generateUrl($name, referenceType: UrlGeneratorInterface::ABSOLUTE_PATH),
                'changefreq' => 'weekly',
                'priority' => $name === 'catalog_home' ? '1.0' : ($name === 'catalog_products' ? '0.9' : '0.6'),
            ];
        }

        foreach ($categoryRepository->findAllSlugsOrdered() as $slug) {
            $urls[] = [
                'loc' => $base . $this->generateUrl('catalog_category', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_PATH),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        foreach ($productRepository->findActiveSlugs() as $slug) {
            $urls[] = [
                'loc' => $base . $this->generateUrl('catalog_product', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_PATH),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        $xml = $this->renderView('seo/sitemap.xml.twig', ['urls' => $urls]);

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    #[Route('/robots.txt', name: 'robots_txt', methods: ['GET'], priority: 20)]
    public function robots(Request $request): Response
    {
        $sitemap = $request->getSchemeAndHttpHost() . $this->generateUrl('sitemap', referenceType: UrlGeneratorInterface::ABSOLUTE_PATH);
        $body = "User-agent: *\nDisallow: /admin/\nDisallow: /api/\nDisallow: /checkout/\nDisallow: /payment/\n\nSitemap: {$sitemap}\n";

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
