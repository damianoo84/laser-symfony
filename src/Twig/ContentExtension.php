<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\ContentValidator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ContentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('content_html', [ContentValidator::class, 'html'], ['is_safe' => ['html']]),
            new TwigFilter('safe_link', fn (string $value): string => $this->safeUrl($value, false)),
            new TwigFilter('image_path', fn (string $value): string => $this->safeUrl($value, true)),
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
