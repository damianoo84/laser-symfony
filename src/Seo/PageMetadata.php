<?php

declare(strict_types=1);

namespace App\Seo;

use App\Content\ContentValidator;
use Symfony\Component\HttpFoundation\Request;

final class PageMetadata
{
    public function __construct(private SiteUrl $siteUrl)
    {
    }

    public function home(array $settings, array $blocks, Request $request): array
    {
        $url = $this->siteUrl->home();
        $image = '';
        try {
            $path = ContentValidator::url($settings['seo.image'] ?? '', true);
            if ($path !== '') {
                $image = str_starts_with($path, 'uploads/') ? $this->siteUrl->asset($path) : $path;
            }
        } catch (\InvalidArgumentException) {
            // An invalid optional sharing image must not break the page.
        }
        $business = [
            '@type' => 'LocalBusiness', '@id' => $url . '#firma',
            'name' => $settings['contact.company'], 'url' => $url,
            'description' => $settings['site.description'],
            'telephone' => $settings['contact.phone'], 'email' => $settings['contact.email'],
            'address' => $settings['contact.address'], 'areaServed' => $settings['contact.area'],
        ];
        // Derive the structured address from the same editable address shown to visitors.
        if (preg_match('/^(.+),\s*(\d{2}-\d{3})\s+(.+)$/u', trim($settings['contact.address']), $address)) {
            $business['address'] = [
                '@type' => 'PostalAddress', 'streetAddress' => trim($address[1]),
                'postalCode' => $address[2], 'addressLocality' => trim($address[3]), 'addressCountry' => 'PL',
            ];
        }
        if ($image !== '') {
            $business['image'] = $image;
        }
        $profiles = [];
        foreach ($blocks['socialLink'] ?? [] as $social) {
            $profile = $social->get('href');
            if (filter_var($profile, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $profile)) {
                $profiles[] = $profile;
            }
        }
        if ($profiles) {
            $business['sameAs'] = array_values(array_unique($profiles));
        }
        $graph = [
            $business,
            ['@type' => 'WebSite', '@id' => $url . '#website', 'url' => $url, 'name' => $settings['site.name'], 'inLanguage' => 'pl-PL', 'publisher' => ['@id' => $url . '#firma']],
            ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $settings['site.title'], 'description' => $settings['site.description'], 'inLanguage' => 'pl-PL', 'isPartOf' => ['@id' => $url . '#website'], 'about' => ['@id' => $url . '#firma']],
        ];
        if ($settings['section.oferta.visible'] ?? false) {
            foreach ($blocks['offer'] ?? [] as $offer) {
                $graph[] = [
                    '@type' => 'Service', 'name' => 'Czyszczenie laserowe — ' . $offer->get('name'),
                    'description' => trim(strip_tags($offer->get('desc'))),
                    'provider' => ['@id' => $url . '#firma'], 'areaServed' => $settings['contact.area'],
                    'url' => $url . '#oferta',
                ];
            }
        }
        return [
            'canonical' => $url,
            'robots' => $this->siteUrl->indexable($request) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow',
            'image' => $image, 'imageAlt' => $settings['seo.imageAlt'] ?? '',
            'schema' => ['@context' => 'https://schema.org', '@graph' => $graph],
        ];
    }
}
