<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Responsive product images.
 *
 * Uploaded images (/uploads/…) are served in a few fixed widths as WebP: /media/{width}/uploads/….webp.
 * The first request renders the file with GD into public/media; afterwards the web server serves it
 * directly. Unsplash URLs (demo data) are resized by Unsplash itself; other external URLs pass through.
 */
final class ResponsiveImages
{
    public const WIDTHS = [320, 480, 640, 960, 1280, 1600];
    private const QUALITY = 78;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    /**
     * URL of the image at (at most) the given width.
     */
    public function url(?string $path, int $width): string
    {
        $path = (string) $path;
        $width = $this->closestWidth($width);

        if (str_starts_with($path, '/uploads/') && \function_exists('imagewebp')) {
            return '/media/' . $width . $path . '.webp';
        }

        if (str_contains($path, 'images.unsplash.com')) {
            $parts = parse_url($path);
            parse_str($parts['query'] ?? '', $query);
            $query = array_merge($query, ['w' => $width, 'auto' => 'format', 'fit' => 'max', 'q' => 75]);

            return sprintf('%s://%s%s?%s', $parts['scheme'] ?? 'https', $parts['host'] ?? '', $parts['path'] ?? '', http_build_query($query));
        }

        return $path;
    }

    /**
     * srcset attribute value, e.g. "/media/320/… 320w, /media/640/… 640w".
     *
     * @param list<int> $widths
     */
    public function srcset(?string $path, array $widths): string
    {
        $path = (string) $path;
        if (!str_starts_with($path, '/uploads/') && !str_contains($path, 'images.unsplash.com')) {
            return '';
        }

        return implode(', ', array_map(fn (int $w): string => $this->url($path, $w) . ' ' . $this->closestWidth($w) . 'w', $widths));
    }

    /**
     * Renders /media/{width}/{source}.webp from public/{source}. Returns the generated file path,
     * or null if the source is not an uploaded image.
     */
    public function render(int $width, string $source): ?string
    {
        if (!\in_array($width, self::WIDTHS, true) || !\function_exists('imagewebp')) {
            return null;
        }

        $uploads = realpath($this->publicDir . '/uploads');
        $sourceFile = realpath($this->publicDir . '/' . ltrim($source, '/'));
        // Only files inside public/uploads (no traversal, no PHP files)
        if ($uploads === false || $sourceFile === false || !str_starts_with($sourceFile, $uploads . \DIRECTORY_SEPARATOR) || !is_file($sourceFile)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($sourceFile));
        if ($image === false) {
            return null;
        }

        if (imagesx($image) > $width) {
            $scaled = imagescale($image, $width);
            imagedestroy($image);
            if ($scaled === false) {
                return null;
            }
            $image = $scaled;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $target = $this->publicDir . '/media/' . $width . '/' . ltrim($source, '/') . '.webp';
        if (!is_dir(\dirname($target)) && !mkdir(\dirname($target), 0775, true) && !is_dir(\dirname($target))) {
            imagedestroy($image);

            return null;
        }
        imagewebp($image, $target, self::QUALITY);
        imagedestroy($image);

        return $target;
    }

    private function closestWidth(int $width): int
    {
        foreach (self::WIDTHS as $candidate) {
            if ($candidate >= $width) {
                return $candidate;
            }
        }

        return self::WIDTHS[array_key_last(self::WIDTHS)];
    }
}
