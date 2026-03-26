<?php

namespace App\Controller\Admin;

use App\Service\System\AppUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SystemUpdateController extends AbstractController
{
    #[Route('/admin/system/update', name: 'admin_system_update', methods: ['POST'])]
    public function update(Request $request, AppUpdateService $appUpdateService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_system_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin');
        }

        $check = $appUpdateService->checkForUpdates();
        if ($check['ok'] !== true) {
            $this->addFlash('error', 'Update check failed: ' . ($check['error'] ?? 'unknown error'));
            return $this->redirectToRoute('admin');
        }

        if (($check['updates_available'] ?? false) !== true) {
            $this->addFlash('info', 'No updates available.');
            return $this->redirectToRoute('admin');
        }

        // Demo-friendly: run synchronously; keep output short in flash, full output in logs/CLI.
        @set_time_limit(0);
        $result = $appUpdateService->runUpdate();

        if ($result['ok']) {
            $this->addFlash('success', 'Update complete.');
            return $this->redirectToRoute('admin');
        }

        $msg = $result['output'] !== '' ? $result['output'] : ('Update failed (exit code ' . $result['exit_code'] . ').');
        $this->addFlash('error', mb_strimwidth($msg, 0, 600, '…'));

        return $this->redirectToRoute('admin');
    }
}

