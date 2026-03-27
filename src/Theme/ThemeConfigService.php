<?php

namespace App\Theme;

use App\Entity\Shop;
use App\Entity\Theme;
use App\Entity\ThemeRevision;
use App\Entity\User;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validates, merges, and persists theme configuration.
 */
class ThemeConfigService
{
    private const DEFAULT_CONFIG_PATH = __DIR__ . '/../../config/theme_config_example.json';

    /** @var array<string, mixed> */
    private ?array $defaultConfig = null;

    public function __construct(
        private readonly ThemeRepository $themeRepository,
        private readonly ThemeResolver $themeResolver,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        if ($this->defaultConfig === null) {
            $path = self::DEFAULT_CONFIG_PATH;
            if (is_readable($path)) {
                $json = file_get_contents($path);
                $data = json_decode($json, true);
                $this->defaultConfig = is_array($data) ? $this->whitelistConfig($data) : [];
            } else {
                $this->defaultConfig = $this->getMinimalConfig();
            }
        }
        return $this->defaultConfig;
    }

    /**
     * Merge custom config over defaults and validate.
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function mergeAndValidate(array $config): array
    {
        $merged = $this->deepMerge($this->getDefaultConfig(), $config);
        return $this->whitelistConfig($merged);
    }

    /**
     * Whitelist allowed config keys; strip unknown.
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function whitelistConfig(array $config): array
    {
        $allowed = [
            'brand', 'colors', 'typography', 'layout', 'header', 'footer',
            'homepage', 'catalog', 'product', 'components', 'support', 'customCss',
        ];
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $config) && is_array($config[$key])) {
                $out[$key] = $this->sanitizeValue($config[$key], $key);
            } elseif ($key === 'customCss' && isset($config[$key])) {
                $out[$key] = $this->sanitizeCustomCss((string) $config[$key]);
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $value */
    private function sanitizeValue(mixed $value, string $context): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->sanitizeValue($v, $context . '.' . $k);
            } elseif (is_string($v)) {
                if ($context === 'customCss' || str_contains($context, 'customCss')) {
                    $out[$k] = $this->sanitizeCustomCss($v);
                } elseif (str_contains($k, 'url') || str_contains($k, 'Url')) {
                    $out[$k] = $this->sanitizeUrl($v);
                } elseif (preg_match('/^#[0-9a-fA-F]{3,6}$/', $v) || !str_contains($v, ':')) {
                    $out[$k] = $v;
                } else {
                    $out[$k] = $v;
                }
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?://|/|mailto:|tel:)#', $url)) {
            return $url;
        }
        return '';
    }

    private function sanitizeCustomCss(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }
        if (strlen($css) > 50 * 1024) {
            return '';
        }
        $dangerous = ['@import', 'expression(', 'javascript:', 'behavior:', 'url(javascript:'];
        foreach ($dangerous as $pattern) {
            if (stripos($css, $pattern) !== false) {
                return '';
            }
        }
        return $css;
    }

    public function getOrCreateDraftTheme(?Shop $shop): Theme
    {
        $theme = $this->themeRepository->findByShopAndSlug($shop, 'default');
        if ($theme !== null) {
            return $theme;
        }
        $theme = new Theme();
        $theme->setShop($shop);
        $theme->setName('Default');
        $theme->setSlug('default');
        $theme->setStatus(Theme::STATUS_DRAFT);
        $theme->setConfig($this->getDefaultConfig());
        $this->em->persist($theme);
        $this->em->flush();
        return $theme;
    }

    public function saveDraft(Theme $theme, array $config, ?User $user = null): Theme
    {
        $theme->setConfig($this->mergeAndValidate($config));
        $revision = new ThemeRevision();
        $revision->setTheme($theme);
        $revision->setConfig($theme->getConfig());
        $revision->setVersion($theme->getVersion());
        $revision->setStatus($theme->getStatus());
        $revision->setComment('Draft saved');
        $revision->setCreatedBy($user);
        $theme->addRevision($revision);
        $this->em->flush();
        return $theme;
    }

    public function publish(Theme $theme, ?User $user = null): Theme
    {
        $theme->setStatus(Theme::STATUS_PUBLISHED);
        $theme->incrementVersion();
        $revision = new ThemeRevision();
        $revision->setTheme($theme);
        $revision->setConfig($theme->getConfig());
        $revision->setVersion($theme->getVersion());
        $revision->setStatus(Theme::STATUS_PUBLISHED);
        $revision->setPublishedAt(new \DateTimeImmutable());
        $revision->setComment('Published');
        $revision->setCreatedBy($user);
        $theme->addRevision($revision);
        $this->em->flush();
        $this->themeResolver->bustCache($theme->getShop());
        return $theme;
    }

    public function rollback(Theme $theme, int $version): Theme
    {
        $revision = $theme->getRevisions()->filter(
            fn (ThemeRevision $r) => $r->getVersion() === $version
        )->first();
        if ($revision === false) {
            throw new \InvalidArgumentException('Revision not found');
        }
        $theme->setConfig($revision->getConfig());
        $theme->incrementVersion();
        $revisionNew = new ThemeRevision();
        $revisionNew->setTheme($theme);
        $revisionNew->setConfig($theme->getConfig());
        $revisionNew->setVersion($theme->getVersion());
        $revisionNew->setStatus($theme->getStatus());
        $revisionNew->setComment('Rollback to v' . $version);
        $theme->addRevision($revisionNew);
        $this->em->flush();
        if ($theme->isPublished()) {
            $this->themeResolver->bustCache($theme->getShop());
        }
        return $theme;
    }

    public function resetToDefault(Theme $theme): Theme
    {
        $theme->setConfig($this->getDefaultConfig());
        $this->em->flush();
        return $theme;
    }

    /** @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed> */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /** @return array<string, mixed> */
    private function getMinimalConfig(): array
    {
        return [
            'brand' => ['siteName' => 'SymfoShop'],
            'colors' => $this->loadDefaultTokens()['colors'] ?? [],
            'typography' => [],
            'layout' => [],
            'header' => ['menuItems' => [], 'showCart' => true, 'showLanguageSwitcher' => true],
            'footer' => ['columns' => [], 'socialLinks' => [], 'trustBadges' => []],
            'homepage' => ['sections' => []],
            'catalog' => [],
            'product' => [],
            'components' => [],
            'support' => [
                'provider' => 'selfcoded',
                'tawkEmbedPath' => '',
                'charlaProjectId' => '',
            ],
            'customCss' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function loadDefaultTokens(): array
    {
        $path = __DIR__ . '/../../config/theme_tokens_default.json';
        if (is_readable($path)) {
            $data = json_decode(file_get_contents($path), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }
}
