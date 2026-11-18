<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\ImageFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:images:optimize', description: 'Tworzy responsywne WebP w public/uploads bez zmiany oryginałów i bazy')]
final class OptimizeImagesCommand extends Command
{
    public function __construct(private ImageFiles $images, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!function_exists('imagewebp') || !function_exists('exif_read_data')) {
            $output->writeln('<error>Generowanie wymaga PHP GD z WebP oraz EXIF. Wyświetlanie strony nie wymaga tych rozszerzeń.</error>');
            return Command::FAILURE;
        }
        $created = 0;
        $directory = $this->projectDir . '/public/uploads';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && !$file->isLink() && preg_match('/\.(?:jpe?g|png)$/i', $file->getFilename())) {
                $path = 'uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                $created += $this->images->optimize($path);
            }
        }
        $output->writeln(sprintf('<info>Utworzono %d wariantów WebP. Oryginały i dane w bazie pozostały bez zmian.</info>', $created));
        return Command::SUCCESS;
    }
}
