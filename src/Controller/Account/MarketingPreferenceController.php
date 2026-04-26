<?php

namespace App\Controller\Account;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class MarketingPreferenceController extends AbstractController
{
    #[Route('/account/marketing/opt-out/{id}', name: 'account_marketing_opt_out', methods: ['GET'])]
    public function optOut(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $userRepository->find($id);
        if ($user !== null) {
            $user->setMarketingOptIn(false);
            $entityManager->flush();
            $this->addFlash('success', 'account.marketing.opt_out_success');
        }

        return $this->redirectToRoute('catalog_home');
    }
}

