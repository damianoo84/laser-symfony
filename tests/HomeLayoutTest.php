<?php

declare(strict_types=1);

namespace App\Tests;

use App\Content\Defaults;
use App\Content\HomeLayout;
use App\Content\WordpressContent;
use App\Entity\Block;
use PHPUnit\Framework\TestCase;

final class HomeLayoutTest extends TestCase
{
    private function imported(): array
    {
        $data = (new WordpressContent(dirname(__DIR__)))->data();
        $blocks = [];
        $id = 1;
        foreach (['articles' => 'archiveArticle', 'media' => 'archiveMedia'] as $group => $type) {
            foreach ($data[$group] as $entry) {
                $block = (new Block())->setType($type)->setData($entry['data'] + ['_import_key' => $entry['key']]);
                (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id++);
                $blocks[$type][] = $block;
            }
        }
        return $blocks;
    }

    public function testImportedContentIsReusedWithoutDuplicateSectionsOrLogos(): void
    {
        $blocks = $this->imported();
        $view = (new HomeLayout())->build(Defaults::data()['settings'], $blocks);
        self::assertCount(1, $view['company']);
        self::assertCount(1, $view['introMore']);
        self::assertCount(5, $view['offers']);
        self::assertCount(6, $view['applications']);
        self::assertCount(0, $view['extras']);
        self::assertStringStartsWith('<p>Firma', $view['company'][0]['body']);
        self::assertStringContainsString('Dolnego Śląska', $view['company'][0]['body']);
        self::assertSame(['Metal', 'Drewno', 'Cegła', 'Kamień', 'Beton'], array_column($view['offers'], 'label'));
        self::assertContains('Przemysł', array_column($view['applications'], 'label'));
        self::assertContains('Zabytki / Sztuka', array_column($view['applications'], 'label'));
        foreach ($view['applications'] as $application) {
            self::assertFileExists(dirname(__DIR__) . '/public/' . $application['image']);
            self::assertNotEmpty($application['imageAlt']);
        }
        self::assertSame('masonry', $view['offers'][2]['group']);
        self::assertSame('masonry', $view['offers'][4]['group']);
        $ids = array_column(array_merge($view['offers'], $view['applications']), 'anchor');
        self::assertCount(count($ids), array_unique($ids));
        // Projection must not alter the CMS records.
        $company = array_values(array_filter($blocks['archiveArticle'], static fn (Block $block): bool => $block->get('anchor') === 'wp-onas'))[0];
        self::assertStringStartsWith('<p><strong><b>O nas', $company->get('body'));
    }

    public function testPresentationAndShortLabelsCanBeChangedInAdminData(): void
    {
        $blocks = $this->imported();
        $wood = array_values(array_filter($blocks['archiveArticle'], static fn (Block $block): bool => $block->get('anchor') === 'wp-drewno'))[0];
        $wood->setData(array_replace($wood->getData(), ['shortTitle' => 'Renowacja drewna', 'body' => '<p>Własny opis.</p>']));
        $view = (new HomeLayout())->build(Defaults::data()['settings'], $blocks);
        self::assertSame('Renowacja drewna', $view['offers'][1]['label']);
        self::assertSame('<p>Własny opis.</p>', $view['offers'][1]['body']);
        $wood->setData(array_replace($wood->getData(), ['presentation' => 'hidden']));
        $view = (new HomeLayout())->build(Defaults::data()['settings'], $blocks);
        self::assertNotContains('wp-drewno', array_column($view['offers'], 'anchor'));
        $application = $blocks['archiveArticle'][9];
        $application->setData(array_replace($application->getData(), ['image' => 'uploads/settings/my-machine.jpg', 'imageAlt' => 'Moja maszyna']));
        $view = (new HomeLayout())->build(Defaults::data()['settings'], $blocks);
        self::assertSame('uploads/settings/my-machine.jpg', $view['applications'][0]['image']);
        self::assertSame('Moja maszyna', $view['applications'][0]['imageAlt']);
    }
}
