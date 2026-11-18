<?php

declare(strict_types=1);

namespace App\Tests;

use App\Content\Defaults;
use App\Controller\SeoController;
use App\Entity\Block;
use App\EventSubscriber\SeoSubscriber;
use App\Seo\PageMetadata;
use App\Seo\SiteUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SeoTest extends TestCase
{
    public function testCanonicalUrlIsConfiguredAndDoesNotUseAnUntrustedHost(): void
    {
        $site = new SiteUrl('https://laser.example', 'prod', false);
        self::assertSame('https://laser.example/', $site->home());
        self::assertSame('https://laser.example/uploads/site/photo.jpg', $site->asset('uploads/site/photo.jpg'));
        self::assertTrue($site->indexable(Request::create('https://laser.example/')));
        self::assertFalse($site->indexable(Request::create('https://attacker.example/')));
        self::assertNull($site->redirect(Request::create('https://attacker.example/')));
        self::assertSame('https://laser.example/page?utm_source=test', $site->redirect(Request::create('http://www.laser.example/page?utm_source=test')));
        self::assertNull($site->redirect(Request::create('https://laser.example/')));
        self::assertNull($site->redirect(Request::create('http://laser.example/', 'POST')));
    }

    public function testLocalAndDebugEnvironmentsCannotBeIndexedOrRedirected(): void
    {
        foreach ([['dev', false], ['test', false], ['prod', true]] as [$environment, $debug]) {
            $site = new SiteUrl('https://laser.example/', $environment, $debug);
            self::assertFalse($site->indexable(Request::create('https://laser.example/')));
            self::assertNull($site->redirect(Request::create('http://www.laser.example/')));
        }
    }

    /** @dataProvider invalidUrls */
    public function testInvalidCanonicalConfigurationIsRejected(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SiteUrl($url, 'prod', false);
    }

    public function invalidUrls(): array
    {
        return [[''], ['/relative'], ['javascript:alert(1)'], ['https://user:pass@laser.example/'], ['https://laser.example/?query=1'], ['https://laser.example/#anchor']];
    }

    public function testRobotsAllowsOnlyProductionAndListsOnlyTheCanonicalSitemap(): void
    {
        $request = Request::create('https://laser.example/robots.txt');
        $controller = new SeoController();
        $production = $controller->robots($request, new SiteUrl('https://laser.example/', 'prod', false));
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: https://laser.example/sitemap.xml\n", $production->getContent());
        $local = $controller->robots($request, new SiteUrl('https://laser.example/', 'dev', false));
        self::assertSame("User-agent: *\nDisallow: /\n", $local->getContent());
    }

    public function testAdminErrorsAndPostResponsesRemainNoindexInProduction(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $subscriber = new SeoSubscriber(new SiteUrl('https://laser.example/', 'prod', false));
        foreach ([['/admin', 'GET', 200], ['/missing', 'GET', 404], ['/', 'POST', 422]] as [$path, $method, $status]) {
            $response = new Response('', $status);
            $subscriber->onResponse(new ResponseEvent($kernel, Request::create('https://laser.example' . $path, $method), HttpKernelInterface::MAIN_REQUEST, $response));
            self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        }
        $response = new Response();
        $subscriber->onResponse(new ResponseEvent($kernel, Request::create('https://laser.example/'), HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('X-Robots-Tag'));
        $redirect = new RedirectResponse('https://laser.example/', 301);
        $subscriber->onResponse(new ResponseEvent($kernel, Request::create('http://www.laser.example/'), HttpKernelInterface::MAIN_REQUEST, $redirect));
        self::assertFalse($redirect->headers->has('X-Robots-Tag'));
    }

    public function testSchemaUsesEditableBusinessDataAndDoesNotInventRatings(): void
    {
        $defaults = Defaults::data();
        $blocks = [];
        foreach ($defaults['blocks']['offer'] as $data) {
            $blocks['offer'][] = (new Block())->setData($data);
        }
        $metadata = (new PageMetadata(new SiteUrl('https://laser.example/', 'prod', false)))->home($defaults['settings'], $blocks, Request::create('https://laser.example/'));
        self::assertSame('index, follow, max-image-preview:large', $metadata['robots']);
        self::assertSame('https://laser.example/uploads/site/20251104_130814-scaled.jpg', $metadata['image']);
        $business = $metadata['schema']['@graph'][0];
        self::assertSame($defaults['settings']['contact.company'], $business['name']);
        self::assertSame('Lipowe 182', $business['address']['streetAddress']);
        self::assertSame('34-600', $business['address']['postalCode']);
        self::assertSame('Limanowa', $business['address']['addressLocality']);
        self::assertArrayNotHasKey('aggregateRating', $business);
        self::assertArrayNotHasKey('review', $business);
        self::assertCount(7, $metadata['schema']['@graph']);
        $defaults['settings']['section.oferta.visible'] = '0';
        $hidden = (new PageMetadata(new SiteUrl('https://laser.example/', 'prod', false)))->home($defaults['settings'], $blocks, Request::create('https://laser.example/'));
        self::assertCount(3, $hidden['schema']['@graph']);
    }
}
