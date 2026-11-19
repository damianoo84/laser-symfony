<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\ContentValidator;
use App\Content\ImageFiles;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ContentExtension extends AbstractExtension
{
    public function __construct(private ImageFiles $images, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_info', [$this->images, 'info']),
            new TwigFunction('asset_revision', [$this, 'assetRevision']),
        ];
    }

    public function assetRevision(string $path): string
    {
        if (!preg_match('~^(?:css|js)/[a-zA-Z0-9_-]+\.(?:css|js)$~', $path)) {
            throw new \InvalidArgumentException('Nieprawidłowa ścieżka statycznego zasobu.');
        }
        $file = $this->projectDir . '/public/' . $path;
        return is_file($file) ? substr(hash_file('sha256', $file), 0, 12) : '0';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('content_html', [ContentValidator::class, 'html'], ['is_safe' => ['html']]),
            new TwigFilter('safe_link', fn (string $value): string => $this->safeUrl($value, false)),
            new TwigFilter('image_path', fn (string $value): string => $this->safeUrl($value, true)),
            new TwigFilter('media_path', static function (string $value): string {
                try {
                    return ContentValidator::media($value);
                } catch (\InvalidArgumentException) {
                    return '';
                }
            }),
        ];
    }

    private function safeUrl(string $value, bool $image): string
    {
        try {
            return ContentValidator::url($value, $image);
        } catch (\InvalidArgumentException) {
            return $image ? '' : '#';
        }
    }
}
