<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
        private readonly ThemeConfigService $themeConfigService,
    ) {
    }

    #[Route('/settings', name: 'admin_settings', methods: ['GET'])]
    public function index(): Response
    {
        $shop = $this->shopContextResolver->resolve();
        $theme = $this->themeConfigService->getOrCreateDraftTheme($shop);
        $config = $theme->getConfig();

        return $this->render('admin/settings/index.html.twig', [
            'support_provider' => $this->getSupportProviderConfigFromConfig($config),
            'storefront' => $this->getStorefrontFormData($config),
        ]);
    }

    #[Route('/settings/storefront', name: 'admin_settings_storefront_save', methods: ['POST'])]
    public function saveStorefront(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_storefront_settings', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $shop = $this->shopContextResolver->resolve();
        $theme = $this->themeConfigService->getOrCreateDraftTheme($shop);
        $config = $theme->getConfig();

        $brand = is_array($config['brand'] ?? null) ? $config['brand'] : [];
        $siteName = trim((string) $request->request->get('brand_siteName', ''));
        $tagline = trim((string) $request->request->get('brand_tagline', ''));
        if (mb_strlen($siteName) > 120) {
            $siteName = mb_substr($siteName, 0, 120);
        }
        if (mb_strlen($tagline) > 200) {
            $tagline = mb_substr($tagline, 0, 200);
        }
        $brand['siteName'] = $siteName !== '' ? $siteName : ($brand['siteName'] ?? 'SymfoShop');
        $brand['tagline'] = $tagline;
        $config['brand'] = $brand;

        $header = is_array($config['header'] ?? null) ? $config['header'] : [];
        $header['sticky'] = $request->request->getBoolean('header_sticky');
        $header['showCart'] = $request->request->getBoolean('header_showCart');
        $header['showLanguageSwitcher'] = $request->request->getBoolean('header_showLanguageSwitcher');
        $config['header'] = $header;

        $footer = is_array($config['footer'] ?? null) ? $config['footer'] : [];
        $copyright = trim((string) $request->request->get('footer_copyright', ''));
        if (mb_strlen($copyright) > 280) {
            $copyright = mb_substr($copyright, 0, 280);
        }
        $footer['copyright'] = $copyright;
        $config['footer'] = $footer;

        $catalog = is_array($config['catalog'] ?? null) ? $config['catalog'] : [];
        $catalog['showFilters'] = $request->request->getBoolean('catalog_showFilters');
        $filterPos = (string) $request->request->get('catalog_filterPosition', 'sidebar');
        $catalog['filterPosition'] = in_array($filterPos, ['sidebar', 'top'], true) ? $filterPos : 'sidebar';
        $badgeStyle = (string) $request->request->get('catalog_badgeStyle', 'pill');
        $catalog['badgeStyle'] = in_array($badgeStyle, ['pill', 'square'], true) ? $badgeStyle : 'pill';
        $grid = is_array($catalog['gridColumns'] ?? null) ? $catalog['gridColumns'] : [];
        $mob = (int) $request->request->get('catalog_grid_mobile', $grid['mobile'] ?? 1);
        $tab = (int) $request->request->get('catalog_grid_tablet', $grid['tablet'] ?? 2);
        $desk = (int) $request->request->get('catalog_grid_desktop', $grid['desktop'] ?? 4);
        $catalog['gridColumns'] = [
            'mobile' => max(1, min(2, $mob)),
            'tablet' => max(1, min(4, $tab)),
            'desktop' => max(1, min(6, $desk)),
        ];
        $config['catalog'] = $catalog;

        $product = is_array($config['product'] ?? null) ? $config['product'] : [];
        $galleryStyle = (string) $request->request->get('product_galleryStyle', 'thumbnails');
        $product['galleryStyle'] = in_array($galleryStyle, ['thumbnails', 'carousel'], true) ? $galleryStyle : 'thumbnails';
        $product['stickyAddToCart'] = $request->request->getBoolean('product_stickyAddToCart');
        $tabsLayout = (string) $request->request->get('product_tabsLayout', 'accordion');
        $product['tabsLayout'] = in_array($tabsLayout, ['accordion', 'tabs'], true) ? $tabsLayout : 'accordion';
        $config['product'] = $product;

        $currentUser = $this->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;
        $this->themeConfigService->saveDraft($theme, $config, $user);
        $this->themeConfigService->publish($theme, $user);

        $this->addFlash('success', 'admin.settings.flash.storefront_saved');

        return $this->redirectToRoute('admin_settings');
    }

    #[Route('/support-provider', name: 'admin_support_provider_save', methods: ['POST'])]
    public function saveSupportProvider(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_support_provider', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $provider = (string) $request->request->get('provider', 'selfcoded');
        if (!in_array($provider, ['disabled', 'selfcoded', 'tawkto', 'charla', 'tawk_charla'], true)) {
            $provider = 'disabled';
        }

        $shop = $this->shopContextResolver->resolve();
        $theme = $this->themeConfigService->getOrCreateDraftTheme($shop);
        $config = $theme->getConfig();
        $tawkEmbedPath = trim((string) $request->request->get('tawkEmbedPath', ''));
        $charlaProjectId = trim((string) $request->request->get('charlaProjectId', ''));

        if ($tawkEmbedPath !== '' && !preg_match('#^[A-Za-z0-9/_-]+$#', $tawkEmbedPath)) {
            $this->addFlash('error', 'admin.settings.flash.invalid_tawk_path');

            return $this->redirectToRoute('admin_settings');
        }

        if ($charlaProjectId !== '' && !preg_match('/^[a-f0-9-]{8,64}$/i', $charlaProjectId)) {
            $this->addFlash('error', 'admin.settings.flash.invalid_charla_id');

            return $this->redirectToRoute('admin_settings');
        }

        $config['support'] = [
            'provider' => $provider,
            'tawkEmbedPath' => $tawkEmbedPath,
            'charlaProjectId' => $charlaProjectId,
        ];

        $currentUser = $this->getUser();
        $user = $currentUser instanceof User ? $currentUser : null;
        $this->themeConfigService->saveDraft($theme, $config, $user);
        $this->themeConfigService->publish($theme, $user);

        $this->addFlash('success', 'admin.settings.flash.support_saved');

        return $this->redirectToRoute('admin_settings');
    }

    /**
     * @param array<string, mixed> $themeConfig
     * @return array{provider:string,tawkEmbedPath:string,charlaProjectId:string}
     */
    private function getSupportProviderConfigFromConfig(array $themeConfig): array
    {
        $support = is_array($themeConfig['support'] ?? null) ? $themeConfig['support'] : [];

        $provider = (string) ($support['provider'] ?? 'selfcoded');
        if (!in_array($provider, ['disabled', 'selfcoded', 'tawkto', 'charla', 'tawk_charla'], true)) {
            $provider = 'disabled';
        }

        $tawkEmbedPath = (string) ($support['tawkEmbedPath'] ?? '');
        if ($tawkEmbedPath === '') {
            $legacyProperty = trim((string) ($support['tawkPropertyId'] ?? ''));
            $legacyWidget = trim((string) ($support['tawkWidgetId'] ?? ''));
            $tawkEmbedPath = $legacyProperty . ($legacyWidget !== '' ? '/' . $legacyWidget : '');
        }

        $charlaProjectId = (string) ($support['charlaProjectId'] ?? '');
        if ($charlaProjectId === '') {
            $charlaProjectId = (string) ($support['charlaWebsiteId'] ?? '');
        }

        return [
            'provider' => $provider,
            'tawkEmbedPath' => $tawkEmbedPath,
            'charlaProjectId' => $charlaProjectId,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function getStorefrontFormData(array $config): array
    {
        $brand = is_array($config['brand'] ?? null) ? $config['brand'] : [];
        $header = is_array($config['header'] ?? null) ? $config['header'] : [];
        $footer = is_array($config['footer'] ?? null) ? $config['footer'] : [];
        $catalog = is_array($config['catalog'] ?? null) ? $config['catalog'] : [];
        $grid = is_array($catalog['gridColumns'] ?? null) ? $catalog['gridColumns'] : [];
        $product = is_array($config['product'] ?? null) ? $config['product'] : [];

        return [
            'brand_siteName' => (string) ($brand['siteName'] ?? ''),
            'brand_tagline' => (string) ($brand['tagline'] ?? ''),
            'footer_copyright' => (string) ($footer['copyright'] ?? ''),
            'header_sticky' => (bool) ($header['sticky'] ?? true),
            'header_showCart' => (bool) ($header['showCart'] ?? true),
            'header_showLanguageSwitcher' => (bool) ($header['showLanguageSwitcher'] ?? true),
            'catalog_showFilters' => (bool) ($catalog['showFilters'] ?? true),
            'catalog_filterPosition' => (string) ($catalog['filterPosition'] ?? 'sidebar'),
            'catalog_badgeStyle' => (string) ($catalog['badgeStyle'] ?? 'pill'),
            'catalog_grid_mobile' => (int) ($grid['mobile'] ?? 1),
            'catalog_grid_tablet' => (int) ($grid['tablet'] ?? 2),
            'catalog_grid_desktop' => (int) ($grid['desktop'] ?? 4),
            'product_galleryStyle' => (string) ($product['galleryStyle'] ?? 'thumbnails'),
            'product_stickyAddToCart' => (bool) ($product['stickyAddToCart'] ?? true),
            'product_tabsLayout' => (string) ($product['tabsLayout'] ?? 'accordion'),
        ];
    }
}
