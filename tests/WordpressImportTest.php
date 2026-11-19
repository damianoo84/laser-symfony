<?php

declare(strict_types=1);

namespace App\Tests;

use App\Command\ImportWordpressContentCommand;
use App\Content\ContentValidator;
use App\Content\WordpressContent;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class WordpressImportTest extends TestCase
{
    private Connection $db;
    private array $backups;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` TEXT UNIQUE, value TEXT)');
        $this->db->executeStatement('CREATE TABLE app_blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, sort INTEGER, data TEXT)');
        $this->db->executeStatement('CREATE TABLE app_inquiries (id INTEGER PRIMARY KEY, message TEXT)');
        $this->db->executeStatement("INSERT INTO app_settings (`key`, value) VALUES ('contact.phone', 'Mój numer')");
        $this->db->executeStatement("INSERT INTO app_inquiries (id, message) VALUES (1, 'Wiadomość klienta — nie zmieniać')");
        $this->db->insert('app_blocks', ['type' => 'offer', 'sort' => 1, 'data' => json_encode(['name' => 'DREWNO', 'desc' => 'Własny opis', 'img' => 'uploads/settings/custom.jpg'])]);
        $this->db->insert('app_blocks', ['type' => 'offer', 'sort' => 2, 'data' => json_encode(['name' => 'KAMIEŃ', 'href' => '#moj-link'])]);
        $this->backups = glob(dirname(__DIR__) . '/var/backups/wordpress-content/*.json') ?: [];
    }

    protected function tearDown(): void
    {
        $this->db->close();
        foreach (array_diff(glob(dirname(__DIR__) . '/var/backups/wordpress-content/*.json') ?: [], $this->backups) as $file) {
            unlink($file);
        }
    }

    private function command(): CommandTester
    {
        return new CommandTester(new ImportWordpressContentCommand($this->db, new WordpressContent(dirname(__DIR__)), dirname(__DIR__)));
    }

    public function testPayloadContainsAllBusinessPagesAndVerifiedLocalMediaWithoutSecrets(): void
    {
        $content = new WordpressContent(dirname(__DIR__));
        $content->verifyFiles();
        $data = $content->data();
        self::assertCount(10, $data['sourcePages']);
        self::assertCount(15, $data['articles']);
        self::assertCount(69, $data['gallery']);
        self::assertCount(4, array_filter($data['files'], static fn (array $file): bool => $file['kind'] === 'video'));
        $allText = '';
        foreach ($data['articles'] as $article) {
            $body = $article['data']['body'];
            self::assertLessThanOrEqual(20000, mb_strlen($body));
            $text = static fn (string $html): string => preg_replace('/\s+/u', ' ', trim(strip_tags(html_entity_decode($html))));
            self::assertSame($text($body), $text(ContentValidator::html($body)));
            $allText .= $body;
        }
        foreach (['Dolnego Śląska', 'Zalety oczyszczania laserowego drewna', 'Rolnictwo', 'Gastronomia', 'od 45 zł/m²'] as $text) {
            self::assertStringContainsString($text, html_entity_decode(strip_tags($allText)));
        }
        $json = json_encode($data);
        foreach (['DB_PASSWORD', 'MAILER_DSN', 'user_pass', 'web-designer-portfolio', 'wp-content/uploads', 'Starting Price'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $json);
        }
    }

    public function testPreviewIsReadOnlyAndApplyIsBackedUpAndPreservesCustomerData(): void
    {
        $before = $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id');
        $command = $this->command();
        self::assertSame(0, $command->execute([]));
        self::assertSame($before, $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id'));
        self::assertSame($this->backups, glob(dirname(__DIR__) . '/var/backups/wordpress-content/*.json') ?: []);
        self::assertSame(0, $command->execute(['--apply' => true]));
        self::assertSame('Mój numer', $this->db->fetchOne("SELECT value FROM app_settings WHERE `key`='contact.phone'"));
        self::assertSame('Wiadomość klienta — nie zmieniać', $this->db->fetchOne('SELECT message FROM app_inquiries WHERE id=1'));
        $offer = json_decode($this->db->fetchOne('SELECT data FROM app_blocks WHERE id=1'), true);
        self::assertSame('Własny opis', $offer['desc']);
        self::assertSame('uploads/settings/custom.jpg', $offer['img']);
        self::assertSame('#wp-drewno', $offer['href']);
        self::assertSame('#moj-link', json_decode($this->db->fetchOne('SELECT data FROM app_blocks WHERE id=2'), true)['href']);
        $files = array_values(array_diff(glob(dirname(__DIR__) . '/var/backups/wordpress-content/*.json') ?: [], $this->backups));
        self::assertCount(1, $files);
        self::assertSame($before, json_decode(file_get_contents($files[0]), true)['blocks']);
        $articleId = $this->db->fetchOne("SELECT id FROM app_blocks WHERE type='archiveArticle' LIMIT 1");
        $this->db->delete('app_blocks', ['id' => $articleId]);
        $after = $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id');
        self::assertSame(0, $command->execute(['--apply' => true]));
        self::assertSame($after, $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id'));
    }

    public function testTransactionRollsBackAllContentOnFailure(): void
    {
        $before = $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id');
        $this->db->executeStatement("CREATE TRIGGER reject_import BEFORE INSERT ON app_blocks BEGIN SELECT RAISE(ABORT, 'test'); END");
        try {
            $this->command()->execute(['--apply' => true]);
            self::fail('Expected the test trigger to reject inserts.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame($before, $this->db->fetchAllAssociative('SELECT * FROM app_blocks ORDER BY id'));
            self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM app_settings'));
        }
    }

    public function testMediaValidationRejectsRemoteAndExecutableFiles(): void
    {
        foreach (['uploads/wordpress/photo.png', 'uploads/archiveMedia/video.mp4'] as $path) {
            self::assertSame($path, ContentValidator::media($path));
        }
        foreach (['https://other.example/a.jpg', 'uploads/a.php', '../.env', 'uploads/a.svg'] as $path) {
            try {
                ContentValidator::media($path);
                self::fail('Unsafe media accepted.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
