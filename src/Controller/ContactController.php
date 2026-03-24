<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(default:app.default_mailer_from:CONTACT_NOTIFY_EMAIL)%')]
        private readonly string $notifyEmail,
        #[Autowire('%env(default:app.default_mailer_from:MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $subject = $this->trans('contact.email.subject', [
                '%name%' => (string) $data['name'],
            ]);
            $body = $this->trans('contact.email.body', [
                '%name%' => (string) $data['name'],
                '%email%' => (string) $data['email'],
                '%message%' => (string) $data['message'],
            ]);

            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($this->notifyEmail)
                ->replyTo((string) $data['email'])
                ->subject($subject)
                ->text($body);

            $mailer->send($email);
            $this->addFlash('success', 'contact.flash.sent');

            return $this->redirectToRoute('contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
