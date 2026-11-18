<?php

declare(strict_types=1);

namespace App\Controller;

use App\Seo\SiteUrl;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'seo_robots', methods: ['GET'])]
    public function robots(Request $request, SiteUrl $siteUrl): Response
    {
        // Do not disallow /admin on production: crawlers must be able to read its noindex header.
        $text = $siteUrl->indexable($request)
            ? "User-agent: *\nAllow: /\n\nSitemap: " . $siteUrl->asset('sitemap.xml') . "\n"
            : "User-agent: *\nDisallow: /\n";
        return new Response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'])]
    public function sitemap(SiteUrl $siteUrl): Response
    {
        // This is a one-page site: section anchors are not separate indexable documents.
        return $this->render('seo/sitemap.xml.twig', ['canonical' => $siteUrl->home()], new Response('', 200, ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
