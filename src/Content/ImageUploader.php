<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUploader
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function upload(?UploadedFile $file, string $group): ?string
    {
        if ($file === null) {
            return null;
        }
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Nie udało się przesłać zdjęcia. Maksymalny rozmiar: 5 MB (lub limit PHP).');
        }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $info = @getimagesize($file->getPathname());
        if (!$info || !isset($extensions[$info['mime']]) || $file->getMimeType() !== $info['mime'] || $info[0] > 12000 || $info[1] > 12000) {
            throw new \InvalidArgumentException('Dozwolone są wyłącznie zdjęcia JPG, PNG, WebP i GIF do 12 000 px.');
        }
        $directory = $this->projectDir . '/public/uploads/' . $group;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie można utworzyć katalogu zdjęć.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$info['mime']];
        $file->move($directory, $name);
        return 'uploads/' . $group . '/' . $name;
    }
}
