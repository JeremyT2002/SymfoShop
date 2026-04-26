<?php

namespace App\Controller\Admin;

use App\Service\Admin\AdminActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AdminActivityService $adminActivityService,
    ) {
    }

    #[Route('/admin/audit-log', name: 'admin_audit_log', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/audit_log/index.html.twig', [
            'entries' => $this->adminActivityService->getAuditEntries(),
        ]);
    }

    #[Route('/admin/notifications/mark-seen', name: 'admin_notifications_mark_seen', methods: ['POST'])]
    public function markNotificationsSeen(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_notifications_mark_seen', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin');
        }

        $this->adminActivityService->markAllNotificationsAsSeen();
        $this->addFlash('success', 'Notifications marked as read.');

        $redirectTo = (string) $request->request->get('redirect_to', '');
        if ($redirectTo !== '') {
            return $this->redirect($redirectTo);
        }

        return $this->redirectToRoute('admin');
    }
}

