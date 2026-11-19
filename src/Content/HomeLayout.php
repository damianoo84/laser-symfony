<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Block;

/** Presentation only: all texts and media remain editable in the existing CMS. */
final class HomeLayout
{
    public function build(array $settings, array $blocks): array
    {
        $view = ['company' => [], 'introMore' => [], 'offers' => [], 'applications' => [], 'extras' => [], 'offerLinks' => []];
        foreach ($blocks['archiveArticle'] ?? [] as $article) {
            $role = $this->role($article);
            if ($role === 'hidden') {
                continue;
            }
            $topic = $this->topic($article, $blocks['archiveMedia'] ?? []);
            if ($role === 'company') {
                $topic['body'] = $this->companyBody($topic['body']);
            }
            $group = ['company' => 'company', 'introMore' => 'introMore', 'offer' => 'offers', 'application' => 'applications', 'extra' => 'extras'][$role];
            $view[$group][] = $topic;
        }
        // The source has no separate Metal page. Reuse the editable industrial-metal description,
        // without duplicating its database record or moving the Applications anchor.
        $metalExists = array_filter($view['offers'], static fn (array $topic): bool => mb_strtolower($topic['label']) === 'metal');
        if (!$metalExists) {
            foreach ($view['applications'] as $application) {
                if ($application['source'] === 'wp-zastosowanie-przemysl') {
                    $metal = $application;
                    $metal['anchor'] = 'opis-metal-' . $application['id'];
                    $metal['label'] = $settings['offer.metalLabel'];
                    $metal['title'] = $settings['offer.metalTitle'];
                    array_unshift($view['offers'], $metal);
                    break;
                }
            }
        }
        foreach ($blocks['offer'] ?? [] as $offer) {
            $href = $offer->get('href');
            foreach ($view['offers'] as $topic) {
                if ($href === '#' . $topic['anchor'] || $href === '#' . $topic['source']) {
                    $view['offerLinks'][$offer->getId()] = '#' . $topic['anchor'];
                    break;
                }
            }
        }
        return $view;
    }

    public function role(Block $article): string
    {
        $explicit = $article->get('presentation');
        if (in_array($explicit, ['company', 'introMore', 'offer', 'application', 'extra', 'hidden'], true)) {
            return $explicit;
        }
        return match ($this->sourceAnchor($article)) {
            'wp-onas' => 'company',
            'wp-uslugi' => 'introMore',
            'wp-materialy-galerii', 'wp-cennik', 'wp-kontakt' => 'hidden',
            default => match ($article->get('section')) {
                'oferta' => 'offer',
                'zastosowania' => 'application',
                default => 'extra',
            },
        };
    }

    private function sourceAnchor(Block $article): string
    {
        $key = $article->get('_import_key');
        return str_starts_with($key, 'wp-article-') ? substr($key, strlen('wp-article-')) : $article->get('anchor');
    }

    private function topic(Block $article, array $media): array
    {
        $anchor = $article->get('anchor');
        if (!preg_match('/^(?:wp|opis)-[a-z0-9]+(?:-[a-z0-9]+)*$/', $anchor)) {
            $anchor = 'opis-element-' . $article->getId();
        }
        $label = $article->get('shortTitle');
        if ($label === '') {
            $label = match ($this->sourceAnchor($article)) {
                'wp-drewno' => 'Drewno', 'wp-cegla' => 'Cegła', 'wp-kamien' => 'Kamień', 'wp-beton' => 'Beton',
                default => $article->get('title'),
            };
        }
        return [
            'id' => $article->getId(), 'anchor' => $anchor, 'source' => $this->sourceAnchor($article),
            'section' => $article->get('section'), 'title' => $article->get('title'), 'label' => $label,
            'body' => $article->get('body'),
            'group' => in_array($this->sourceAnchor($article), ['wp-cegla', 'wp-beton'], true) ? 'masonry' : $anchor,
            'image' => $article->get('image') ?: $this->applicationImage($this->sourceAnchor($article)),
            'imageAlt' => $article->get('imageAlt') ?: $this->applicationImageAlt($this->sourceAnchor($article)),
            'media' => array_values(array_filter($media, static fn (Block $item): bool => $item->get('article') === $article->get('anchor'))),
        ];
    }

    private function applicationImage(string $source): string
    {
        return match ($source) {
            'wp-zastosowanie-przemysl', 'wp-zastosowanie-rolnictwo' => 'uploads/wordpress/8bd3590be41b89814373fa1e.jpg',
            'wp-zastosowanie-budownictwo' => 'uploads/site/20251122_144653-scaled.jpg',
            'wp-zastosowanie-zabytki-sztuka' => 'uploads/wordpress/86b142ef9255c9d14f4e971a.jpg',
            'wp-zastosowanie-dom-ogrod' => 'uploads/wordpress/e7bb68beffb56022b8e44f1c.jpg',
            'wp-zastosowanie-gastronomia-przemysl-spozywczy' => 'uploads/wordpress/8b0643c47ac55484839c96d8.jpg',
            default => '',
        };
    }

    private function applicationImageAlt(string $source): string
    {
        return match ($source) {
            'wp-zastosowanie-przemysl', 'wp-zastosowanie-rolnictwo' => 'Przykład czyszczenia elementów maszyny',
            'wp-zastosowanie-budownictwo' => 'Prace przy kamiennych i ceglanych elementach budynku',
            'wp-zastosowanie-zabytki-sztuka' => 'Zabytkowe figury i detale podczas prac renowacyjnych',
            'wp-zastosowanie-dom-ogrod' => 'Przykład czyszczenia ceglanej posadzki we wnętrzu domu',
            'wp-zastosowanie-gastronomia-przemysl-spozywczy' => 'Przykład czyszczenia zbiornika i powierzchni metalowych',
            default => '',
        };
    }

    private function companyBody(string $body): string
    {
        // Remove only the old repeated heading paragraphs, never the company's prose.
        return preg_replace('~^\s*(?:<p>\s*<strong>\s*<b>O nas</b>\s*</strong>\s*</p>\s*)?(?:<p>\s*<strong>\s*<b>Lazer - oczyszczanie laserowe</b>\s*</strong>\s*</p>\s*)?~iu', '', $body) ?? $body;
    }
}
