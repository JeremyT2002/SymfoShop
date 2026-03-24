<?php

namespace App\Controller\Account;

use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly EntityManagerInterface $entityManager
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
        ]);
    }

    #[Route('/profile', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Invalid user session.');
        }

        if (!$this->isCsrfTokenValid('account_profile_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_csrf');
            return $this->redirectToRoute('account_dashboard');
        }

        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $email = trim((string) $request->request->get('email', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $company = trim((string) $request->request->get('company', ''));
        $addressLine1 = trim((string) $request->request->get('addressLine1', ''));
        $addressLine2 = trim((string) $request->request->get('addressLine2', ''));
        $postalCode = trim((string) $request->request->get('postalCode', ''));
        $city = trim((string) $request->request->get('city', ''));
        $state = trim((string) $request->request->get('state', ''));
        $countryCode = strtoupper(trim((string) $request->request->get('countryCode', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'account.dashboard.flash.invalid_email');
            return $this->redirectToRoute('account_dashboard');
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'account.dashboard.flash.email_in_use');
            return $this->redirectToRoute('account_dashboard');
        }

        $user->setFirstName($firstName !== '' ? $firstName : null);
        $user->setLastName($lastName !== '' ? $lastName : null);
        $user->setEmail($email);
        $user->setPhone($phone !== '' ? $phone : null);
        $user->setCompany($company !== '' ? $company : null);
        $user->setAddressLine1($addressLine1 !== '' ? $addressLine1 : null);
        $user->setAddressLine2($addressLine2 !== '' ? $addressLine2 : null);
        $user->setPostalCode($postalCode !== '' ? $postalCode : null);
        $user->setCity($city !== '' ? $city : null);
        $user->setState($state !== '' ? $state : null);
        $user->setCountryCode($countryCode !== '' ? $countryCode : null);

        $this->entityManager->flush();

        $this->addFlash('success', 'account.dashboard.flash.profile_updated');

        return $this->redirectToRoute('account_dashboard');
    }

    #[Route('/invoice/{invoiceNumber}/download', name: 'invoice_download', methods: ['GET'])]
    public function downloadInvoice(string $invoiceNumber): Response
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

        $pdfPath = $invoice->getPdfPath();
        if (!$pdfPath || !is_file($pdfPath)) {
            throw $this->createNotFoundException('Die PDF-Datei konnte nicht gefunden werden.');
        }

        $response = new BinaryFileResponse($pdfPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'invoice_' . $invoice->getInvoiceNumber() . '.pdf'
        );

        return $response;
    }
}

