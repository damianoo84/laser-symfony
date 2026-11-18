<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Optional WebP variants; originals are always retained as a browser fallback. */
final class ImageFiles
{
    private array $cache = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private Packages $assets,
    ) {
    }

    public function info(string $path): array
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }
        try {
            $safe = $path === 'images/laser-surface.svg' ? $path : ContentValidator::url($path, true);
        } catch (\InvalidArgumentException) {
            $safe = '';
        }
        $result = ['src' => $safe !== '' ? $this->assets->getUrl($safe) : '', 'width' => null, 'height' => null, 'srcset' => ''];
        $file = $this->localFile($safe);
        $size = $file ? @getimagesize($file) : false;
        if ($size) {
            $result['width'] = $size[0];
            $result['height'] = $size[1];
            $variants = [];
            foreach ($this->widths($size[0]) as $width) {
                $variant = $safe . '.w' . $width . '.webp';
                $variantFile = $this->projectDir . '/public/' . $variant;
                if (is_file($variantFile) && filemtime($variantFile) >= filemtime($file)) {
                    $variants[] = $this->assets->getUrl($variant) . ' ' . $width . 'w';
                }
            }
            $result['srcset'] = implode(', ', $variants);
        }
        return $this->cache[$path] = $result;
    }

    public function optimize(string $path): int
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
            return 0;
        }
        $file = $this->localFile($path);
        $size = $file ? @getimagesize($file) : false;
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 12000000 || !is_writable(dirname($file))) {
            return 0;
        }
        if ($size[2] === IMAGETYPE_JPEG) {
            // GD ignores EXIF rotation. Keep the original rather than produce a wrongly rotated variant.
            if (!function_exists('exif_read_data')) {
                return 0;
            }
            $exif = @exif_read_data($file);
            if (($exif['Orientation'] ?? 1) !== 1) {
                return 0;
            }
        }
        $source = null;
        $created = 0;
        try {
            foreach ($this->widths($size[0]) as $width) {
                $target = $file . '.w' . $width . '.webp';
                if (is_file($target) && filemtime($target) >= filemtime($file)) {
                    continue;
                }
                $source ??= $size[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($file) : @imagecreatefrompng($file);
                if (!$source) {
                    return $created;
                }
                $height = max(1, (int) round($size[1] * $width / $size[0]));
                $image = imagecreatetruecolor($width, $height);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
                imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
                $temporary = tempnam(dirname($file), 'webp-');
                try {
                    if ($temporary !== false && @imagewebp($image, $temporary, 80)
                        && filesize($temporary) > 0 && @rename($temporary, $target)) {
                        @chmod($target, 0644);
                        ++$created;
                    }
                } finally {
                    imagedestroy($image);
                    if ($temporary !== false && is_file($temporary)) {
                        unlink($temporary);
                    }
                }
            }
        } finally {
            if ($source) {
                imagedestroy($source);
            }
            $this->cache = [];
        }
        return $created;
    }

    private function localFile(string $path): ?string
    {
        if (!preg_match('~^uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp|gif)$~i', $path)) {
            return null;
        }
        $file = realpath($this->projectDir . '/public/' . $path);
        $root = realpath($this->projectDir . '/public/uploads');
        if ($file === false || $root === false || !is_file($file)
            || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')) {
            return null;
        }
        return $file;
    }

    private function widths(int $width): array
    {
        return array_values(array_unique(array_map(static fn (int $size): int => min($size, $width), [480, 960, 1600])));
    }
}
