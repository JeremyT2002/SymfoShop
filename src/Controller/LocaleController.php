<?php

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'locale_switch', requirements: ['locale' => 'en|de|fr'])]
    public function switchLocale(Request $request, string $locale): Response
    {
        $request->getSession()->set('_locale', $locale);

        // Go back to the page the switcher was used on — but only on this host,
        // otherwise a link from another site would bounce the visitor back there.
        $referer = (string) $request->headers->get('referer', '');
        $parsedUrl = $referer !== '' ? parse_url($referer) : false;
        if (\is_array($parsedUrl) && ($parsedUrl['host'] ?? null) === $request->getHost()) {
            $path = $parsedUrl['path'] ?? '/';
            if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
                $path = '/';
            }
            // Drop ?lang=…, it would override the language just chosen
            parse_str($parsedUrl['query'] ?? '', $params);
            unset($params[LocaleSubscriber::QUERY_PARAMETER]);
            $query = $params !== [] ? '?' . http_build_query($params) : '';

            return $this->redirect($request->getSchemeAndHttpHost() . $path . $query);
        }

        return $this->redirectToRoute('catalog_home');
    }
}
