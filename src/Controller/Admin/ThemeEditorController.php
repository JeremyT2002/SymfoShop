<?php

namespace App\Controller\Admin;

use App\Entity\Theme;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/theme')]
#[IsGranted('ROLE_ADMIN')]
class ThemeEditorController extends AbstractController
{
    public function __construct(
        private readonly ShopContextResolver $shopContext,
        private readonly ThemeConfigService $themeConfig,
    ) {
    }

    #[Route('', name: 'admin_theme_editor', methods: ['GET'])]
    public function index(): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        return $this->render('admin/theme_editor/index.html.twig', [
            'theme' => $theme,
            'config' => $theme->getConfig(),
            'defaultConfig' => $this->themeConfig->getDefaultConfig(),
        ]);
    }

    #[Route('/save', name: 'admin_theme_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        $config = json_decode($request->request->get('config', '{}'), true) ?? [];
        $this->themeConfig->saveDraft($theme, $config, $this->getUser());
        $this->addFlash('success', 'Theme draft saved.');
        return $this->redirectToRoute('admin_theme_editor');
    }

    #[Route('/publish', name: 'admin_theme_publish', methods: ['POST'])]
    public function publish(Request $request): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        $config = json_decode($request->request->get('config', '{}'), true) ?? [];
        $theme->setConfig($this->themeConfig->mergeAndValidate($config));
        $this->themeConfig->publish($theme, $this->getUser());
        $this->addFlash('success', 'Theme published.');
        return $this->redirectToRoute('admin_theme_editor');
    }

    #[Route('/reset', name: 'admin_theme_reset', methods: ['POST'])]
    public function reset(): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        $this->themeConfig->resetToDefault($theme);
        $this->addFlash('success', 'Theme reset to default.');
        return $this->redirectToRoute('admin_theme_editor');
    }

    #[Route('/rollback/{version}', name: 'admin_theme_rollback', requirements: ['version' => '\d+'], methods: ['POST'])]
    public function rollback(int $version): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        try {
            $this->themeConfig->rollback($theme, $version);
            $this->addFlash('success', 'Theme rolled back to version ' . $version . '.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('admin_theme_editor');
    }

    #[Route('/export', name: 'admin_theme_export', methods: ['GET'])]
    public function export(): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        $json = json_encode($theme->getConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="theme-config.json"',
        ]);
    }

    #[Route('/import', name: 'admin_theme_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $shop = $this->shopContext->resolve();
        $theme = $this->themeConfig->getOrCreateDraftTheme($shop);
        $file = $request->files->get('config_file');
        if ($file === null || !$file->isValid()) {
            $this->addFlash('error', 'Invalid or missing file.');
            return $this->redirectToRoute('admin_theme_editor');
        }
        $json = file_get_contents($file->getPathname());
        $config = json_decode($json, true);
        if (!is_array($config)) {
            $this->addFlash('error', 'Invalid JSON.');
            return $this->redirectToRoute('admin_theme_editor');
        }
        $this->themeConfig->saveDraft($theme, $config, $this->getUser());
        $this->addFlash('success', 'Theme imported.');
        return $this->redirectToRoute('admin_theme_editor');
    }
}
