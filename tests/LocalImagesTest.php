<?php

declare(strict_types=1);

namespace App\Tests;

use App\Content\BlockTypes;
use App\Content\ContentValidator;
use App\Content\Defaults;
use App\Content\SettingFields;
use PHPUnit\Framework\TestCase;

final class LocalImagesTest extends TestCase
{
    public function testRecoveredImagesExistAndAreValidJpegs(): void
    {
        $files = glob(dirname(__DIR__) . '/public/uploads/site/*.jpg');
        self::assertNotEmpty($files);
        foreach ($files as $path) {
            self::assertFileExists($path);
            self::assertSame(IMAGETYPE_JPEG, getimagesize($path)[2], $path);
        }
    }

    public function testLocalImagePathsAreNotRewritten(): void
    {
        foreach (['uploads/site/20251215_105448-scaled.jpg', 'uploads/settings/my-photo.jpg'] as $path) {
            self::assertSame($path, ContentValidator::url($path, true));
        }
    }

    public function testNonImageLinksRemainUnchanged(): void
    {
        foreach (['https://example.com/contact', '#kontakt', 'tel:+48501965485'] as $link) {
            self::assertSame($link, ContentValidator::url($link));
        }
    }

    public function testAllDefaultPhotosUseExistingLocalFiles(): void
    {
        $defaults = Defaults::data();
        $images = [];
        foreach (SettingFields::imageKeys() as $key) {
            if (($defaults['settings'][$key] ?? '') !== '') {
                $images[] = $defaults['settings'][$key];
            }
        }
        foreach (BlockTypes::types() as $type => $definition) {
            foreach ($defaults['blocks'][$type] ?? [] as $block) {
                foreach ($definition['fields'] as $field) {
                    if ($field['type'] === 'image' && ($block[$field['key']] ?? '') !== '') {
                        $images[] = $block[$field['key']];
                    }
                }
            }
        }
        self::assertNotEmpty($images);
        foreach ($images as $image) {
            self::assertStringStartsWith('uploads/site/', $image);
            self::assertSame($image, ContentValidator::url($image, true));
            self::assertFileExists(dirname(__DIR__) . '/public/' . $image);
        }
    }
}
