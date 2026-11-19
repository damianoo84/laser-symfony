<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaUploader
{
    public function __construct(private ImageUploader $images, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function upload(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!$file->isValid() || $file->getSize() > 50 * 1024 * 1024) {
            throw new \InvalidArgumentException('Nie udało się wgrać materiału. Film: maks. 50 MB lub limit PHP.');
        }
        $mime = $file->getMimeType();
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return $this->images->upload($file, 'archiveMedia');
        }
        $extensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('Dozwolone są zdjęcia JPG/PNG/WebP/GIF albo filmy MP4/WebM.');
        }
        $directory = $this->projectDir . '/public/uploads/archiveMedia';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie można utworzyć katalogu materiałów.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $file->move($directory, $name);
        return 'uploads/archiveMedia/' . $name;
    }
}
