<?php

declare(strict_types=1);

namespace App\Tests;

use App\Content\ContentValidator;
use App\Content\SettingFields;
use PHPUnit\Framework\TestCase;

final class ContentValidatorTest extends TestCase
{
    public function testHtmlAllowsFormattingButNoExecutableMarkup(): void
    {
        $safe = ContentValidator::html('<b onclick="alert(1)">Bold</b><script>alert(1)</script><img src=x onerror=alert(1)><iframe src="x"></iframe>');
        self::assertStringContainsString('<b>Bold</b>', $safe);
        self::assertStringNotContainsString('script', $safe);
        self::assertStringNotContainsString('onclick', $safe);
        self::assertStringNotContainsString('<img', $safe);
        self::assertStringNotContainsString('iframe', $safe);
    }

    /** @dataProvider unsafeLinks */
    public function testUnsafeLinksAreRejected(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContentValidator::url($url);
    }

    public function unsafeLinks(): array
    {
        return [['javascript:alert(1)'], ['//evil.example'], ['data:text/html,test'], ["https://example.com/');color:red;("], ["https://example.com/\nx"]];
    }

    public function testImageFieldsAreDerivedFromDefinitions(): void
    {
        self::assertContains('site.logo', SettingFields::imageKeys());
        self::assertNotContains('comparisonHead.title', SettingFields::imageKeys());
    }

    public function testRatingRangeIsEnforced(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContentValidator::field(['type' => 'number', 'label' => 'Gwiazdki', 'min' => 1, 'max' => 5], '99');
    }
}
