<?php

declare(strict_types=1);

namespace App\Seo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class SiteUrl
{
    private string $url;

    public function __construct(
        #[Autowire('%env(SITE_URL)%')] string $url,
        #[Autowire('%kernel.environment%')] private string $environment,
        #[Autowire('%kernel.debug%')] private bool $debug,
    ) {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$parts
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('SITE_URL musi być pełnym adresem strony, bez hasła, parametrów i fragmentu.');
        }
        $this->url = rtrim($url, '/') . '/';
    }

    public function home(): string
    {
        return $this->url;
    }

    public function asset(string $path): string
    {
        return $this->url . ltrim($path, '/');
    }

    public function indexable(Request $request): bool
    {
        return $this->environment === 'prod' && !$this->debug
            && strtolower($request->getSchemeAndHttpHost()) === strtolower($this->origin());
    }

    public function redirect(Request $request): ?string
    {
        if ($this->environment !== 'prod' || $this->debug || !$request->isMethodSafe()) {
            return null;
        }
        $host = strtolower((string) parse_url($this->url, PHP_URL_HOST));
        $aliases = [$host, str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host];
        if (!in_array(strtolower($request->getHost()), $aliases, true)
            || strtolower($request->getSchemeAndHttpHost()) === strtolower($this->origin())) {
            return null;
        }
        return $this->origin() . $request->getRequestUri();
    }

    private function origin(): string
    {
        $parts = parse_url($this->url);
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
