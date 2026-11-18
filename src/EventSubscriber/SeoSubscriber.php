<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Seo\SiteUrl;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SeoSubscriber implements EventSubscriberInterface
{
    public function __construct(private SiteUrl $siteUrl)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 10], KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && ($target = $this->siteUrl->redirect($event->getRequest())) !== null) {
            $event->setResponse(new RedirectResponse($target, 301));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($event->getResponse()->isRedirection()
            && ($target = $this->siteUrl->redirect($request)) !== null
            && $target === $event->getResponse()->headers->get('Location')) {
            return;
        }
        if (!$this->siteUrl->indexable($request) || $event->getResponse()->getStatusCode() >= 400
            || str_starts_with($request->getPathInfo(), '/admin') || !$request->isMethodSafe()) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
