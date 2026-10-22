<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/newsletter', name: 'admin_newsletter_')]
#[IsGranted('ROLE_ADMIN')]
final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $subscribers,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/newsletter/index.html.twig', [
            'subscribers' => $this->subscribers->findAllNewestFirst(),
            'activeCount' => $this->subscribers->countActive(),
        ]);
    }

    /**
     * Confirmed, still subscribed addresses as CSV (for the newsletter tool).
     */
    #[Route('/export.csv', name: 'export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $subscribers = array_filter($this->subscribers->findAllNewestFirst(), static fn (NewsletterSubscriber $s): bool => $s->isActive());

        $unsubscribeUrl = fn (NewsletterSubscriber $s): string => $this->generateUrl('newsletter_unsubscribe', ['token' => $s->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new StreamedResponse(static function () use ($subscribers, $unsubscribeUrl): void {
            $out = fopen('php://output', 'wb');
            // unsubscribe_url: put it into every newsletter (required)
            fputcsv($out, ['email', 'locale', 'confirmed_at', 'unsubscribe_url']);
            foreach ($subscribers as $subscriber) {
                fputcsv($out, [$subscriber->getEmail(), $subscriber->getLocale(), $subscriber->getConfirmedAt()?->format(\DATE_ATOM), $unsubscribeUrl($subscriber)]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="newsletter-subscribers.csv"');

        return $response;
    }
}
