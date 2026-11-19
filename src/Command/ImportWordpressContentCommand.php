<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\BlockTypes;
use App\Content\ContentValidator;
use App\Content\WordpressContent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:wordpress:import', description: 'Dodaje odzyskane publiczne treści i media. Domyślnie podgląd; --apply zapisuje po utworzeniu kopii.')]
final class ImportWordpressContentCommand extends Command
{
    public function __construct(private Connection $db, private WordpressContent $source, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Zapisz treści po utworzeniu prywatnej kopii');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->source->data();
        $marker = 'migration.' . $source['version'];
        if ($this->db->fetchOne('SELECT id FROM app_settings WHERE `key` = ?', [$marker]) !== false) {
            $output->writeln('<info>Treści były już przeniesione. Niczego nie nadpisano — późniejsze edycje i usunięcia w panelu są zachowane.</info>');
            return Command::SUCCESS;
        }
        $this->source->verifyFiles();
        $blocks = $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id');
        $settings = $this->db->fetchAllAssociative('SELECT * FROM app_settings ORDER BY id');
        $known = [];
        $galleryHashes = [];
        $sort = [];
        foreach ($blocks as $block) {
            $data = json_decode($block['data'], true, 512, JSON_THROW_ON_ERROR);
            $sort[$block['type']] = max($sort[$block['type']] ?? 0, (int) $block['sort']);
            if (isset($data['_import_key'])) {
                $known[$data['_import_key']] = true;
            }
            if ($block['type'] === 'gallery') {
                foreach (['full', 'src'] as $field) {
                    $path = $data[$field] ?? '';
                    if (preg_match('~^uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|gif|webp)$~i', $path) && is_file($this->projectDir . '/public/' . $path)) {
                        $galleryHashes[hash_file('sha256', $this->projectDir . '/public/' . $path)] = true;
                    }
                }
            }
        }
        $fileHashes = array_column($source['files'], 'sha256', 'path');
        $add = [];
        foreach (['articles' => 'archiveArticle', 'media' => 'archiveMedia', 'gallery' => 'gallery'] as $group => $type) {
            foreach ($source[$group] as $entry) {
                if (isset($known[$entry['key']])) {
                    continue;
                }
                if ($type === 'gallery' && isset($galleryHashes[$fileHashes[$entry['data']['full']]])) {
                    continue;
                }
                $data = [];
                foreach (BlockTypes::fields($type) as $field) {
                    // Article references are verified against the manifest below, not a static dropdown.
                    $data[$field['key']] = $type === 'archiveMedia' && $field['key'] === 'article'
                        ? $entry['data']['article']
                        : ContentValidator::field($field, $entry['data'][$field['key']] ?? ($field['default'] ?? ''));
                }
                $data['_import_key'] = $entry['key'];
                $sort[$type] = ($sort[$type] ?? 0) + 1;
                $add[] = ['type' => $type, 'sort' => $sort[$type], 'data' => $data];
                if ($type === 'gallery') {
                    $galleryHashes[$fileHashes[$entry['data']['full']]] = true;
                }
            }
        }
        $anchors = array_column(array_column($source['articles'], 'data'), 'anchor');
        foreach ($source['media'] as $media) {
            if (!in_array($media['data']['article'], $anchors, true)) {
                throw new \RuntimeException('Materiał ma nieistniejący opis.');
            }
        }
        $links = [];
        foreach ($blocks as $block) {
            $data = json_decode($block['data'], true, 512, JSON_THROW_ON_ERROR);
            $anchor = $source['offerAnchors'][$data['name'] ?? ''] ?? null;
            if ($block['type'] === 'offer' && $anchor && in_array($data['href'] ?? '', ['', '#kontakt'], true)) {
                $links[] = ['id' => $block['id'], 'before' => $block['data'], 'after' => json_encode(array_replace($data, ['href' => '#' . $anchor]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
            }
        }
        $counts = array_count_values(array_column($add, 'type'));
        $output->writeln(json_encode(['dodawane_elementy' => $counts, 'linki_oferty' => count($links), 'pliki_sprawdzone_SHA256' => count($source['files'])], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (!$input->getOption('apply')) {
            $output->writeln('To tylko podgląd. Do zapisu użyj --apply. Nie jest importowana baza ani konta WordPressa.');
            return Command::SUCCESS;
        }
        $directory = $this->projectDir . '/var/backups/wordpress-content';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie można utworzyć prywatnego katalogu kopii.');
        }
        $backup = $directory . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
        $contents = json_encode(['settings' => $settings, 'blocks' => $blocks], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $handle = fopen($backup, 'x');
        if (!$handle) {
            throw new \RuntimeException('Nie można zapisać kopii. Baza nie została zmieniona.');
        }
        try {
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Niepełna kopia. Baza nie została zmieniona.');
            }
        } finally {
            fclose($handle);
            @chmod($backup, 0600);
        }
        $this->db->transactional(function () use ($marker, $add, $links): void {
            // A concurrent/repeated import must not add the same content twice.
            if ($this->db->fetchOne('SELECT id FROM app_settings WHERE `key` = ?', [$marker]) !== false) {
                throw new \RuntimeException('Import został już wykonany przez inny proces.');
            }
            $this->db->executeStatement('INSERT INTO app_settings (`key`, value) VALUES (?, ?)', [$marker, gmdate(DATE_ATOM)]);
            foreach ($add as $block) {
                $this->db->insert('app_blocks', ['type' => $block['type'], 'sort' => $block['sort'], 'data' => json_encode($block['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
            foreach ($links as $link) {
                if ($this->db->executeStatement('UPDATE app_blocks SET data = ? WHERE id = ? AND data = ?', [$link['after'], $link['id'], $link['before']]) !== 1) {
                    throw new \RuntimeException('W trakcie importu zmieniono treść oferty. Transakcję wycofano.');
                }
            }
        });
        $output->writeln('<info>Przeniesiono treści. Kopia wcześniejszych ustawień i bloków: ' . $backup . '</info>');
        return Command::SUCCESS;
    }
}
