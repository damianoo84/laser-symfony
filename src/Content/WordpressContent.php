<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Public text/media manifest only. The WordPress SQL and credentials are never deployed. */
final class WordpressContent
{
    private ?array $data = null;

    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function data(): array
    {
        return $this->data ??= json_decode(file_get_contents($this->projectDir . '/resources/wordpress/content.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function verifyFiles(): void
    {
        foreach ($this->data()['files'] as $file) {
            $path = ContentValidator::media($file['path']);
            $full = $this->projectDir . '/public/' . $path;
            if ($path === '' || !is_file($full) || hash_file('sha256', $full) !== $file['sha256']) {
                throw new \RuntimeException('Brak lub niezgodna suma kontrolna materiału: ' . $path);
            }
        }
    }
}
