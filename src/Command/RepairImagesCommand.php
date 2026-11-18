<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\Defaults;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:images:repair', description: 'Naprawia znane błędne zdjęcia i podpisy; domyślnie tylko podgląd, zapis po --apply z kopią treści')]
final class RepairImagesCommand extends Command
{
    public function __construct(private Connection $db, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Zapisz zmiany po utworzeniu prywatnej kopii zmienianych rekordów');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plan = $this->plan();
        foreach ($plan as $change) {
            $output->writeln(sprintf('%s: %s', $change['table'], $change['key'] ?? ('#' . $change['id'] . ' ' . $change['type'])));
            $this->checkImages($change['column'] === 'data' ? json_decode($change['after'], true, 512, JSON_THROW_ON_ERROR) : $change['after']);
        }
        $output->writeln(sprintf('Rekordy do zmiany: %d. Własne zdjęcia i zmodyfikowane podpisy niepasujące do wzorca pozostają bez zmian.', count($plan)));
        if (!$input->getOption('apply')) {
            $output->writeln('To tylko podgląd. Dodaj --apply, aby zapisać zmiany.');
            return Command::SUCCESS;
        }
        if (!$plan) {
            return Command::SUCCESS;
        }

        $directory = $this->projectDir . '/var/backups/image-repair';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nie można utworzyć prywatnego katalogu kopii. Baza nie została zmieniona.');
        }
        $backup = $directory . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
        $json = json_encode(['createdAt' => gmdate(DATE_ATOM), 'changes' => $plan], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = fopen($backup, 'x');
        if ($handle === false) {
            throw new \RuntimeException('Nie można zapisać kopii. Baza nie została zmieniona.');
        }
        try {
            if (fwrite($handle, $json) !== strlen($json)) {
                throw new \RuntimeException('Niepełna kopia. Baza nie została zmieniona.');
            }
        } finally {
            fclose($handle);
            @chmod($backup, 0600);
        }
        $this->db->transactional(function () use ($plan): void {
            foreach ($plan as $change) {
                if ($change['id'] === null) {
                    $this->db->insert('app_settings', ['`key`' => $change['key'], 'value' => $change['after']]);
                    continue;
                }
                $sql = 'UPDATE ' . $change['table'] . ' SET ' . $change['column'] . ' = ? WHERE id = ? AND ' . $change['column'];
                $parameters = [$change['after'], $change['id']];
                if ($change['before'] === null) {
                    $sql .= ' IS NULL';
                } else {
                    $sql .= ' = ?';
                    $parameters[] = $change['before'];
                }
                if ($this->db->executeStatement($sql, $parameters) !== 1) {
                    throw new \RuntimeException('Treść zmieniła się podczas naprawy. Całą transakcję wycofano.');
                }
            }
        });
        $output->writeln(sprintf('<info>Zapisano %d rekordów. Kopia poprzednich wartości: %s</info>', count($plan), $backup));
        return Command::SUCCESS;
    }

    private function plan(): array
    {
        $defaults = Defaults::data();
        $settings = [];
        foreach ($this->db->fetchAllAssociative('SELECT id, `key`, value FROM app_settings') as $row) {
            $settings[$row['key']] = $row;
        }
        $updates = [];
        if (($settings['intro.baBefore']['value'] ?? '') === 'uploads/site/20251215_105448-scaled.jpg'
            && ($settings['intro.baAfter']['value'] ?? '') === 'uploads/site/20251202_113931-scaled.jpg') {
            $updates['intro.baBefore'] = '';
            $updates['intro.baAfter'] = '';
            if (($settings['intro.baNote']['value'] ?? '') === 'Przesuń suwak, aby zobaczyć efekt. Prawdziwe realizacje — sekcja Galeria.') {
                $updates['intro.baNote'] = $defaults['settings']['intro.baNote'];
            }
        }
        foreach (['intro.image', 'intro.imageAlt', 'tech.imgAlt'] as $key) {
            if (!isset($settings[$key])) {
                $updates[$key] = $defaults['settings'][$key];
            }
        }
        $plan = [];
        foreach ($updates as $key => $value) {
            $plan[] = ['table' => 'app_settings', 'id' => $settings[$key]['id'] ?? null, 'key' => $key, 'column' => 'value', 'before' => $settings[$key]['value'] ?? null, 'after' => $value];
        }
        $rules = $this->rules($defaults['blocks']);
        foreach ($this->db->fetchAllAssociative('SELECT id, type, data FROM app_blocks ORDER BY id') as $row) {
            $data = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($rules[$row['type']] ?? [] as $rule) {
                if (!$this->matches($data, $rule['before'])) {
                    continue;
                }
                $new = array_replace($data, $rule['after']);
                if ($new !== $data) {
                    $plan[] = ['table' => 'app_blocks', 'id' => $row['id'], 'type' => $row['type'], 'column' => 'data', 'before' => $row['data'], 'after' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
                }
                break;
            }
        }
        return $plan;
    }

    private function matches(array $data, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (($data[$key] ?? '') !== $value) {
                return false;
            }
        }
        return true;
    }

    private function rules(array $new): array
    {
        $rules = [];
        $offers = [
            ['name' => 'METAL', 'img' => 'uploads/site/20251215_105448-scaled.jpg'],
            ['name' => 'DREWNO', 'img' => 'uploads/site/20251215_143710-scaled.jpg'],
            ['name' => 'KAMIEŃ', 'img' => 'uploads/site/20251122_144653-scaled.jpg'],
            ['name' => 'CEGŁA I BETON', 'img' => 'uploads/site/20251215_105454-scaled.jpg'],
        ];
        foreach ($offers as $index => $before) {
            $rules['offer'][] = ['before' => $before + ['img_alt' => ''], 'after' => array_intersect_key($new['offer'][$index], array_flip(['img', 'img_alt']))];
        }
        $apps = [
            ['img' => 'uploads/site/20251122_144653-scaled.jpg', 'kicker' => 'Restauracja i konserwacja', 'name' => 'Zabytki', 'desc' => 'Drewno / kamień, ołtarze, rzeźby'],
            ['img' => 'uploads/site/20251215_105448-scaled.jpg', 'kicker' => 'Konserwacja', 'name' => 'Przemysł', 'desc' => 'Konstrukcje, maszyny, formy'],
            ['img' => 'uploads/site/20251215_124727-scaled.jpg', 'kicker' => 'Renowacje', 'name' => 'Motoryzacja', 'desc' => 'Lakiery, rdza, detale'],
            ['img' => 'uploads/site/20251201_184610-scaled.jpg', 'kicker' => 'Utrzymanie', 'name' => 'Produkcja i dom', 'desc' => 'Taśmy, piece, kostka, tarasy'],
        ];
        foreach ($apps as $index => $before) {
            $rules['app'][] = ['before' => $before + ['img_alt' => ''], 'after' => $new['app'][$index]];
        }
        $slides = [
            1 => ['img' => 'uploads/site/20251201_184610-scaled.jpg', 'eyebrow' => 'Przemysł • konstrukcje • maszyny', 'title' => 'Czyszczenie laserem konstrukcji stalowych, instalacji przemysłowych i linii produkcyjnych', 'text' => 'Rdza, farby i inne zabrudzenia — bez demontażu, bez chemii, z krótszym przestojem.'],
            2 => ['img' => 'uploads/site/20251215_105448-scaled.jpg', 'eyebrow' => 'Metal • aluminium • stal kwasoodporna', 'title' => 'Przemysłowe czyszczenie laserem metali', 'text' => 'Aluminium, stal czarna i kwasoodporna, formy wtryskowe, konstrukcje stalowe, elementy blacharki samochodowej.'],
        ];
        foreach ($slides as $index => $before) {
            $rules['slide'][] = ['before' => $before, 'after' => array_intersect_key($new['slide'][$index], $before)];
        }
        $gallery = [
            ['20251215_105448-300x169.jpg', '20251215_105448-scaled.jpg', 'Stal — odrdzewianie'],
            ['20251202_113931-300x225.jpg', '20251202_113931-scaled.jpg', 'Detal — przed / po'],
            ['20251215_143710-169x300.jpg', '20251215_143710-scaled.jpg', 'Drewno — renowacja'],
            ['20251122_144653-225x300.jpg', '20251122_144653-scaled.jpg', 'Cegła — elewacja'],
            ['20251104_130814-300x225.jpg', '20251104_130814-scaled.jpg', 'Maszyna — bez demontażu'],
            ['20251004_103720-300x225.jpg', '20251004_103720-scaled.jpg', 'Beton — posadzka'],
        ];
        foreach ($gallery as $index => [$src, $full, $caption]) {
            $rules['gallery'][] = ['before' => ['src' => 'uploads/site/' . $src, 'full' => 'uploads/site/' . $full, 'alt' => $caption, 'cap' => $caption], 'after' => $new['gallery'][$index]];
        }
        return $rules;
    }

    private function checkImages(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->checkImages($item);
            }
        } elseif (is_string($value) && str_starts_with($value, 'uploads/')) {
            if (!preg_match('~^uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp|gif)$~i', $value)
                || !is_file($this->projectDir . '/public/' . $value)
                || @getimagesize($this->projectDir . '/public/' . $value) === false) {
                throw new \RuntimeException('Brakuje poprawnego zdjęcia: ' . $value . '. Baza nie została zmieniona.');
            }
        }
    }
}
