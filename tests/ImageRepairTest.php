<?php

declare(strict_types=1);

namespace App\Tests;

use App\Command\RepairImagesCommand;
use App\Content\Defaults;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImageRepairTest extends TestCase
{
    private Connection $db;
    private array $existingBackups;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` TEXT UNIQUE, value TEXT)');
        $this->db->executeStatement('CREATE TABLE app_blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, data TEXT)');
        $this->existingBackups = glob(dirname(__DIR__) . '/var/backups/image-repair/*.json') ?: [];
        foreach ([
            'intro.baBefore' => 'uploads/site/20251215_105448-scaled.jpg',
            'intro.baAfter' => 'uploads/site/20251202_113931-scaled.jpg',
            'intro.baNote' => 'Przesuń suwak, aby zobaczyć efekt. Prawdziwe realizacje — sekcja Galeria.',
            'contact.phone' => '+48 600 111 222',
        ] as $key => $value) {
            $this->db->executeStatement('INSERT INTO app_settings (`key`, value) VALUES (?, ?)', [$key, $value]);
        }
        $this->addBlock('offer', ['img' => 'uploads/site/20251215_105448-scaled.jpg', 'name' => 'METAL', 'href' => '#wlasny-link', 'desc' => 'Mój własny opis usługi']);
        $this->addBlock('app', ['img' => 'uploads/site/20251215_124727-scaled.jpg', 'kicker' => 'Renowacje', 'name' => 'Motoryzacja', 'desc' => 'Lakiery, rdza, detale']);
        $this->addBlock('gallery', ['src' => 'uploads/site/20251215_143710-169x300.jpg', 'full' => 'uploads/site/20251215_143710-scaled.jpg', 'alt' => 'Drewno — renowacja', 'cap' => 'Drewno — renowacja']);
        $this->addBlock('slide', ['img' => 'uploads/site/20251201_184610-scaled.jpg', 'eyebrow' => 'Przemysł • konstrukcje • maszyny', 'title' => 'Czyszczenie laserem konstrukcji stalowych, instalacji przemysłowych i linii produkcyjnych', 'text' => 'Rdza, farby i inne zabrudzenia — bez demontażu, bez chemii, z krótszym przestojem.', 'cta1href' => '#wlasne-cenniki']);
    }

    protected function tearDown(): void
    {
        $this->db->close();
        foreach (array_diff(glob(dirname(__DIR__) . '/var/backups/image-repair/*.json') ?: [], $this->existingBackups) as $file) {
            unlink($file);
        }
    }

    public function testPreviewDoesNotModifyTheDatabaseOrCreateABackup(): void
    {
        $before = $this->snapshot();
        $command = new CommandTester(new RepairImagesCommand($this->db, dirname(__DIR__)));
        self::assertSame(0, $command->execute([]));
        self::assertStringContainsString('Rekordy do zmiany: 10.', $command->getDisplay());
        self::assertSame($before, $this->snapshot());
        self::assertSame($this->existingBackups, glob(dirname(__DIR__) . '/var/backups/image-repair/*.json') ?: []);
    }

    public function testApplyMakesABackupRepairsContentAndIsIdempotent(): void
    {
        $command = new CommandTester(new RepairImagesCommand($this->db, dirname(__DIR__)));
        self::assertSame(0, $command->execute(['--apply' => true]));
        $backups = array_values(array_diff(glob(dirname(__DIR__) . '/var/backups/image-repair/*.json') ?: [], $this->existingBackups));
        self::assertCount(1, $backups);
        $backup = json_decode(file_get_contents($backups[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(10, $backup['changes']);
        self::assertSame('uploads/site/20251215_105448-scaled.jpg', $backup['changes'][0]['before']);
        self::assertSame('', $this->setting('intro.baBefore'));
        self::assertSame('', $this->setting('intro.baAfter'));
        self::assertSame('uploads/site/20250218_110112-scaled.jpg', $this->setting('intro.image'));
        self::assertSame('+48 600 111 222', $this->setting('contact.phone'));
        $offer = $this->block('offer');
        self::assertSame('uploads/site/20250305_122423-scaled.jpg', $offer['img']);
        self::assertSame('#wlasny-link', $offer['href']);
        self::assertSame('Mój własny opis usługi', $offer['desc']);
        self::assertSame('Elewacje', $this->block('app')['name']);
        self::assertSame(Defaults::data()['blocks']['gallery'][2], $this->block('gallery'));
        self::assertSame('#wlasne-cenniki', $this->block('slide')['cta1href']);
        self::assertSame('Czyszczenie laserowe kamienia, cegły i detali architektonicznych', $this->block('slide')['title']);
        $after = $this->snapshot();
        self::assertSame(0, $command->execute(['--apply' => true]));
        self::assertStringContainsString('Rekordy do zmiany: 0.', $command->getDisplay());
        self::assertSame($after, $this->snapshot());
        self::assertCount(1, array_diff(glob(dirname(__DIR__) . '/var/backups/image-repair/*.json') ?: [], $this->existingBackups));
    }

    public function testCustomPhotosCaptionsAndComparisonPairsArePreserved(): void
    {
        $this->db->executeStatement('UPDATE app_settings SET value = ? WHERE `key` = ?', ['uploads/settings/custom-before.jpg', 'intro.baBefore']);
        $this->db->executeStatement('UPDATE app_settings SET value = ? WHERE `key` = ?', ['Mój opis porównania', 'intro.baNote']);
        $offer = $this->block('offer');
        $offer['img'] = 'uploads/offer/custom.jpg';
        $this->db->executeStatement('UPDATE app_blocks SET data = ? WHERE type = ?', [json_encode($offer), 'offer']);
        $gallery = $this->block('gallery');
        $gallery['cap'] = 'Własny podpis kolegi';
        $this->db->executeStatement('UPDATE app_blocks SET data = ? WHERE type = ?', [json_encode($gallery), 'gallery']);
        $command = new CommandTester(new RepairImagesCommand($this->db, dirname(__DIR__)));
        self::assertSame(0, $command->execute(['--apply' => true]));
        self::assertSame('uploads/settings/custom-before.jpg', $this->setting('intro.baBefore'));
        self::assertSame('uploads/site/20251202_113931-scaled.jpg', $this->setting('intro.baAfter'));
        self::assertSame('Mój opis porównania', $this->setting('intro.baNote'));
        self::assertSame($offer, $this->block('offer'));
        self::assertSame($gallery, $this->block('gallery'));
    }

    public function testDatabaseTransactionRollsBackOnFailure(): void
    {
        $before = $this->snapshot();
        $this->db->executeStatement("CREATE TRIGGER reject_block_update BEFORE UPDATE ON app_blocks BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
        $command = new CommandTester(new RepairImagesCommand($this->db, dirname(__DIR__)));
        try {
            $command->execute(['--apply' => true]);
            self::fail('The test trigger must reject the update.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame($before, $this->snapshot());
        }
    }

    private function addBlock(string $type, array $data): void
    {
        $this->db->insert('app_blocks', ['type' => $type, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private function setting(string $key): string
    {
        return $this->db->fetchOne('SELECT value FROM app_settings WHERE `key` = ?', [$key]);
    }

    private function block(string $type): array
    {
        return json_decode($this->db->fetchOne('SELECT data FROM app_blocks WHERE type = ?', [$type]), true, 512, JSON_THROW_ON_ERROR);
    }

    private function snapshot(): array
    {
        return [
            $this->db->fetchAllAssociative('SELECT * FROM app_settings ORDER BY id'),
            $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id'),
        ];
    }
}
