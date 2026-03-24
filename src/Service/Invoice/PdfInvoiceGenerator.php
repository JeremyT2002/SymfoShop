<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Theme\ShopContextResolver;
use App\Theme\ThemeResolver;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class PdfInvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $invoiceStoragePath,
        private readonly ShopContextResolver $shopContext,
        private readonly ThemeResolver $themeResolver,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Generate PDF invoice and save to storage
     *
     * @return string Path to generated PDF file
     */
    public function generate(Invoice $invoice, Order $order): string
    {
        // Ensure storage directory exists
        if (!is_dir($this->invoiceStoragePath)) {
            mkdir($this->invoiceStoragePath, 0755, true);
        }

        $shop = $this->shopContext->resolve();
        $themeConfig = $this->themeResolver->resolveConfig($shop);

        // Render HTML template
        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice' => $invoice,
            'order' => $order,
            'theme_config' => $themeConfig,
            'pdf_brand' => $this->buildPdfBrandContext($themeConfig),
        ]);

        // Configure PDF options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        // Generate PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF to file
        $filename = 'invoice_' . $invoice->getInvoiceNumber() . '_' . time() . '.pdf';
        $filepath = $this->invoiceStoragePath . '/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }

    /**
     * Flatten theme tokens for Dompdf-friendly inline CSS (limited var() support).
     *
     * @param array<string, mixed> $themeConfig
     * @return array<string, string|null>
     */
    private function buildPdfBrandContext(array $themeConfig): array
    {
        $brand = is_array($themeConfig['brand'] ?? null) ? $themeConfig['brand'] : [];
        $colors = is_array($themeConfig['colors'] ?? null) ? $themeConfig['colors'] : [];
        $primary = is_array($colors['primary'] ?? null) ? $colors['primary'] : [];
        $text = is_array($colors['text'] ?? null) ? $colors['text'] : [];

        $pick = static function (array $palette, string ...$keys): string {
            foreach ($keys as $k) {
                if (isset($palette[$k]) && is_string($palette[$k]) && $palette[$k] !== '') {
                    return $palette[$k];
                }
            }

            return '#2563eb';
        };

        $primaryMain = $pick($primary, '700', '600', '500');
        $primarySoft = $pick($primary, '50', '100', '200');
        $surface = is_string($colors['surface'] ?? null) ? $colors['surface'] : '#ffffff';
        $background = is_string($colors['background'] ?? null) ? $colors['background'] : '#f9fafb';
        $accent = is_string($colors['accent'] ?? null) ? $colors['accent'] : '#22c55e';
        $textPrimary = is_string($text['primary'] ?? null) ? $text['primary'] : '#111827';
        $textSecondary = is_string($text['secondary'] ?? null) ? $text['secondary'] : '#4b5563';
        $textMuted = is_string($text['muted'] ?? null) ? $text['muted'] : '#9ca3af';

        $themeSiteName = is_string($brand['siteName'] ?? null) && $brand['siteName'] !== ''
            ? $brand['siteName']
            : 'SymfoShop';

        $logoSrc = $this->resolveLogoAbsoluteSrc($brand);
        // Ohne nutzbares Logo einheitlich „SymfoShop“ als Markenname (kein leerer Kopf, kein Theme-Name bei fehlender Datei)
        $siteName = $logoSrc !== null ? $themeSiteName : 'SymfoShop';

        $tagline = is_string($brand['tagline'] ?? null) ? trim($brand['tagline']) : '';

        return [
            'site_name' => $siteName,
            'tagline' => $tagline !== '' ? $tagline : null,
            'logo_src' => $logoSrc,
            'primary' => $primaryMain,
            'primary_soft' => $primarySoft,
            'surface' => $surface,
            'background' => $background,
            'accent' => $accent,
            'text_primary' => $textPrimary,
            'text_secondary' => $textSecondary,
            'text_muted' => $textMuted,
        ];
    }

    /**
     * @param array<string, mixed> $brand
     */
    private function resolveLogoAbsoluteSrc(array $brand): ?string
    {
        $url = $brand['logoUrl'] ?? '';
        if (!is_string($url) || $url === '') {
            return null;
        }
        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            $path = $this->projectDir . '/public' . $url;
            if (is_file($path)) {
                $real = realpath($path);

                return $real !== false ? 'file:///' . str_replace('\\', '/', $real) : null;
            }
        }

        return null;
    }
}

