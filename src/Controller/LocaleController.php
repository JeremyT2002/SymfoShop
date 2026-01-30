<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'locale_switch', requirements: ['locale' => 'en|de|fr'])]
    public function switchLocale(Request $request, string $locale): Response
    {
        // Store the locale in the session
        $request->getSession()->set('_locale', $locale);
        
        // Get the referer URL or default to home
        $referer = $request->headers->get('referer');
        if ($referer) {
            // Parse the referer URL
            $parsedUrl = parse_url($referer);
            $path = $parsedUrl['path'] ?? '/';
            
            // Remove existing locale prefix if present
            $path = preg_replace('#^/(en|de|fr)(/|$)#', '/', $path);
            if ($path === '/') {
                $path = '';
            }
            
            // Build the new URL with the same path
            $newUrl = $parsedUrl['scheme'] ?? 'http';
            $newUrl .= '://';
            $newUrl .= $parsedUrl['host'] ?? '';
            if (isset($parsedUrl['port'])) {
                $newUrl .= ':' . $parsedUrl['port'];
            }
            $newUrl .= $path;
            if (isset($parsedUrl['query'])) {
                $newUrl .= '?' . $parsedUrl['query'];
            }
            
            return $this->redirect($newUrl);
        }
        
        return $this->redirectToRoute('catalog_home');
    }
}

