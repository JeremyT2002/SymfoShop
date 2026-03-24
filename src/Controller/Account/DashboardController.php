<?php

namespace App\Controller\Account;

use App\Entity\User;
use App\Form\Account\AccountProfileType;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\Invoice\PdfInvoiceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account', name: 'account_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PdfInvoiceGenerator $pdfInvoiceGenerator,
    ) {
    }

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user session.');
        }

        $orders = $this->orderRepository->findByCustomerEmail($user->getEmail() ?? '', 25);
        $invoices = $this->invoiceRepository->findByOrderEmail($user->getEmail() ?? '');

        return $this->render('account/dashboard.html.twig', [
            'orders' => $orders,
            'invoices' => $invoices,
            'profileForm' => $this->createForm(AccountProfileType::class, $user, [
                'action' => $this->generateUrl('account_profile_update'),
                'method' => 'POST',
            ])->createView(),
        ]);
    }

    #[Route('/profile', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user session.');
        }

        $form = $this->createForm(AccountProfileType::class, $user, [
            'action' => $this->generateUrl('account_profile_update'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('account_dashboard');
        }

        if (!$form->isValid()) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_email');
            return $this->redirectToRoute('account_dashboard');
        }

        $existingUser = $this->userRepository->findOneBy(['email' => (string) $user->getEmail()]);
        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            $form->get('email')->addError(new FormError('account.dashboard.flash.email_in_use'));
            $this->addFlash('error', 'account.dashboard.flash.email_in_use');
            return $this->redirectToRoute('account_dashboard');
        }

        $user->setFirstName($user->getFirstName() !== '' ? $user->getFirstName() : null);
        $user->setLastName($user->getLastName() !== '' ? $user->getLastName() : null);
        $user->setPhone($user->getPhone() !== '' ? $user->getPhone() : null);
        $user->setCompany($user->getCompany() !== '' ? $user->getCompany() : null);
        $user->setAddressLine1($user->getAddressLine1() !== '' ? $user->getAddressLine1() : null);
        $user->setAddressLine2($user->getAddressLine2() !== '' ? $user->getAddressLine2() : null);
        $user->setPostalCode($user->getPostalCode() !== '' ? $user->getPostalCode() : null);
        $user->setCity($user->getCity() !== '' ? $user->getCity() : null);
        $user->setState($user->getState() !== '' ? $user->getState() : null);
        $user->setCountryCode($user->getCountryCode() !== '' ? $user->getCountryCode() : null);

        $this->entityManager->flush();
        $this->addFlash('success', 'account.dashboard.flash.profile_updated');

        return $this->redirectToRoute('account_dashboard');
    }

    #[Route('/invoice/{invoiceNumber}/download', name: 'invoice_download', methods: ['GET'])]
    public function downloadInvoice(string $invoiceNumber, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user session.');
        }

        $invoice = $this->invoiceRepository->findOneForUserByInvoiceNumber($invoiceNumber, $user->getEmail() ?? '');
        if (!$invoice && $this->isGranted('ROLE_ADMIN')) {
            $invoice = $this->invoiceRepository->findOneByInvoiceNumber($invoiceNumber);
        }

        if (!$invoice) {
            throw $this->createNotFoundException('Rechnung nicht gefunden.');
        }

        $supportedLocales = ['en', 'de', 'fr'];
        $selectedLocale = (string) $request->query->get('lang', $request->getLocale());
        if (!in_array($selectedLocale, $supportedLocales, true)) {
            $selectedLocale = $request->getLocale();
        }

        $selectedLocale = in_array($selectedLocale, $supportedLocales, true) ? $selectedLocale : 'en';

        $previousLocale = $request->getLocale();
        $request->setLocale($selectedLocale);

        try {
            $pdfPath = $this->pdfInvoiceGenerator->generate($invoice, $invoice->getOrder());
        } finally {
            $request->setLocale($previousLocale);
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'invoice_' . $invoice->getInvoiceNumber() . '_' . $selectedLocale . '.pdf'
        );

        return $response;
    }
}

