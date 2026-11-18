<?php

declare(strict_types=1);

namespace App\Tests;

use App\Content\ImageFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

final class ImageFilesTest extends TestCase
{
    public function testDimensionsAndResponsiveVariantsReferOnlyToLocalFiles(): void
    {
        $images = new ImageFiles(dirname(__DIR__), new Packages(new Package(new EmptyVersionStrategy())));
        $info = $images->info('uploads/site/20251104_130814-scaled.jpg');
        self::assertNotEmpty($info['width']);
        self::assertNotEmpty($info['height']);
        self::assertStringContainsString('.w480.webp 480w', $info['srcset']);
        self::assertStringContainsString('.w1600.webp 1600w', $info['srcset']);
        $remote = $images->info('https://images.example/photo.jpg');
        self::assertSame('https://images.example/photo.jpg', $remote['src']);
        self::assertNull($remote['width']);
        self::assertSame('', $remote['srcset']);
        self::assertSame('', $images->info('uploads/../../.env')['src']);
        self::assertSame('', $images->info('javascript:alert(1)')['src']);
    }

    public function testOptimizationKeepsTheOriginalAndDoesNotUpscale(): void
    {
        $root = dirname(__DIR__) . '/var/image-test-' . bin2hex(random_bytes(8));
        $directory = $root . '/public/uploads/test';
        mkdir($directory, 0775, true);
        $original = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFz8AAAAASUVORK5CYII=');
        file_put_contents($directory . '/source.png', $original);
        if (function_exists('imagepng')) {
            $pixel = imagecreatetruecolor(1, 1);
            imagepng($pixel, $directory . '/source.png');
            imagedestroy($pixel);
            $original = file_get_contents($directory . '/source.png');
        }
        try {
            $images = new ImageFiles($root, new Packages(new Package(new EmptyVersionStrategy())));
            self::assertSame('', $images->info('uploads/test/source.png')['srcset']);
            $created = $images->optimize('uploads/test/source.png');
            self::assertSame($original, file_get_contents($directory . '/source.png'));
            if (function_exists('imagewebp')) {
                self::assertSame(1, $created);
                self::assertSame(1, getimagesize($directory . '/source.png.w1.webp')[0]);
                self::assertStringContainsString('source.png.w1.webp 1w', $images->info('uploads/test/source.png')['srcset']);
                self::assertSame(0, $images->optimize('uploads/test/source.png'));
            } else {
                self::assertSame(0, $created);
                self::assertSame('', $images->info('uploads/test/source.png')['srcset']);
            }
        } finally {
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
            rmdir($root . '/public/uploads');
            rmdir($root . '/public');
            rmdir($root);
        }
    }
}
