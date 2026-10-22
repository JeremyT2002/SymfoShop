<?php

declare(strict_types=1);

namespace App\Tests\Service\Media;

use App\Service\Media\ResponsiveImages;
use PHPUnit\Framework\TestCase;

final class ResponsiveImagesTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/symfoshop-media-' . uniqid();
        mkdir($this->publicDir . '/uploads/products/1', 0777, true);
        file_put_contents($this->publicDir . '/secret.php', '<?php echo 1;');

        $image = imagecreatetruecolor(1200, 800);
        imagepng($image, $this->publicDir . '/uploads/products/1/photo.png');
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->publicDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->publicDir);
    }

    public function testUrlsForUploadsUnsplashAndOtherSources(): void
    {
        $images = new ResponsiveImages($this->publicDir);

        self::assertSame('/media/640/uploads/products/1/photo.png.webp', $images->url('/uploads/products/1/photo.png', 600));
        self::assertSame('/media/1600/uploads/a.jpg.webp', $images->url('/uploads/a.jpg', 5000));
        self::assertStringContainsString('w=480', $images->url('https://images.unsplash.com/photo-1?w=800', 480));
        self::assertSame('https://cdn.example.com/a.jpg', $images->url('https://cdn.example.com/a.jpg', 640));
        self::assertSame('', $images->srcset('https://cdn.example.com/a.jpg', [320, 640]));
        self::assertSame(
            '/media/320/uploads/a.jpg.webp 320w, /media/640/uploads/a.jpg.webp 640w',
            $images->srcset('/uploads/a.jpg', [320, 640])
        );
    }

    public function testRenderCreatesDownscaledWebp(): void
    {
        $images = new ResponsiveImages($this->publicDir);

        $file = $images->render(640, 'uploads/products/1/photo.png');

        self::assertNotNull($file);
        self::assertFileExists($this->publicDir . '/media/640/uploads/products/1/photo.png.webp');
        [$width, , $type] = getimagesize($file);
        self::assertSame(640, $width);
        self::assertSame(IMAGETYPE_WEBP, $type);
    }

    public function testRenderRejectsUnknownWidthsAndFilesOutsideUploads(): void
    {
        $images = new ResponsiveImages($this->publicDir);

        self::assertNull($images->render(641, 'uploads/products/1/photo.png'));
        self::assertNull($images->render(640, 'uploads/../secret.php'));
        self::assertNull($images->render(640, 'uploads/missing.png'));
    }
}
