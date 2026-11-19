<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\WordpressContent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LegacyContentController extends AbstractController
{
    #[Route('/{legacy}', name: 'legacy_content', requirements: ['legacy' => 'onas|drewno|cegla|kamien|beton|cennik|zastosowanie|kontakt|galeria|galeria-2|lazer-oczyszczanie-laserowe|oczyszczanie-laserowe'], methods: ['GET'])]
    #[Route('/{legacy}/', name: 'legacy_content_slash', requirements: ['legacy' => 'onas|drewno|cegla|kamien|beton|cennik|zastosowanie|kontakt|galeria|galeria-2|lazer-oczyszczanie-laserowe|oczyszczanie-laserowe'], methods: ['GET'])]
    public function redirectOldPage(string $legacy, WordpressContent $content): RedirectResponse
    {
        return $this->redirectToRoute('home', ['_fragment' => $content->data()['redirects'][$legacy]], 301);
    }
}
