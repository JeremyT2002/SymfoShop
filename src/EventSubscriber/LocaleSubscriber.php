<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_LOCALE = 'en';
    private const SUPPORTED_LOCALES = ['en', 'de', 'fr'];

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Try to see if the locale has been set as a routing parameter
        $locale = $request->attributes->get('_locale');
        
        if (!$locale) {
            // If no locale in the route, try to get it from the session
            $locale = $request->getSession()->get('_locale', self::DEFAULT_LOCALE);
        }
        
        // Ensure the locale is supported
        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }
        
        // Set the locale for the request
        $request->setLocale($locale);
        
        // Save it to the session for future requests
        $request->getSession()->set('_locale', $locale);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must be registered before (i.e., with a higher priority than) the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}

