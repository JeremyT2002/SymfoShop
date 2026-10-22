<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Service\Security\EmailVerifier;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.registration_limiter')]
        RateLimiterFactory $registrationLimiter,
        EmailVerifier $emailVerifier,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('account_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $limiter = $registrationLimiter->create($request->getClientIp() ?? 'anonymous');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'security.register.rate_limited');

                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $emailVerifier->sendVerification($user);
            } catch (TransportExceptionInterface) {
                // The account works without verification; the customer can request the link again.
            }

            // do anything else you need here, like send an email
            $this->addFlash('success', 'ui.verify.flash_registered');

            return $this->redirectToRoute('login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

