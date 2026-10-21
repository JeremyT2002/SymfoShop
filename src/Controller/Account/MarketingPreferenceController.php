<?php

namespace App\Controller\Account;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

class MarketingPreferenceController extends AbstractController
{
    /**
     * One-click unsubscribe from marketing e-mails. The link is signed (see the abandoned-cart
     * reminder), so it cannot be used to unsubscribe other users by guessing their id.
     */
    #[Route('/account/marketing/opt-out/{id}', name: 'account_marketing_opt_out', methods: ['GET'])]
    public function optOut(
        int $id,
        Request $request,
        UriSigner $uriSigner,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$uriSigner->checkRequest($request)) {
            $this->addFlash('error', 'account.marketing.opt_out_invalid_link');

            return $this->redirectToRoute('catalog_home');
        }

        $user = $userRepository->find($id);
        if ($user !== null) {
            $user->setMarketingOptIn(false);
            $entityManager->flush();
            $this->addFlash('success', 'account.marketing.opt_out_success');
        }

        return $this->redirectToRoute('catalog_home');
    }
}
