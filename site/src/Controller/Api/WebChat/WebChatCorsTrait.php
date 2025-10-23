<?php

declare(strict_types=1);

namespace App\Controller\Api\WebChat;

use App\Repository\WebChat\WebChatSiteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait WebChatCorsTrait
{
    private function handlePreflight(Request $request, WebChatSiteRepository $sites): ?Response
    {
        if (!$request->isMethod('OPTIONS')) {
            return null;
        }

        $siteKey = trim((string) $request->query->get('site_key', ''));
        if ($siteKey === '') {
            return new JsonResponse(['error' => 'Invalid site key'], Response::HTTP_FORBIDDEN);
        }

        if (!$sites->isStorageReady()) {
            return new JsonResponse(['error' => 'Web chat is not ready'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $site = $sites->findActiveBySiteKey($siteKey);
        if (!$site) {
            return new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN);
        }

        $originHeader = $request->headers->get('Origin');
        $pageUrl = $request->query->get('page_url');
        if (is_string($pageUrl)) {
            $pageUrl = trim($pageUrl);
            if ($pageUrl === '') {
                $pageUrl = null;
            }
        } else {
            $pageUrl = null;
        }

        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);
        if (!$this->isHostAllowed($host, $site->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);

        $response = new Response('', Response::HTTP_NO_CONTENT);

        return $this->applyCors($response, $request, $allowedOrigin);
    }

    private function applyCors(Response $response, Request $request, ?string $allowedOrigin): Response
    {
        if ($allowedOrigin !== null && $allowedOrigin !== '') {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Vary', 'Origin');
        }

        $requestedHeaders = $request->headers->get('Access-Control-Request-Headers');
        if ($requestedHeaders !== null && $requestedHeaders !== '') {
            $response->headers->set('Access-Control-Allow-Headers', $requestedHeaders);
        } else {
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');

        return $response;
    }

    private function resolveAllowedOrigin(?string $originHeader, array $allowedOrigins, ?string $pageUrl = null): ?string
    {
        if ($originHeader === null || $originHeader === '') {
            return $this->resolveAllowedOriginFromPageUrl($pageUrl, $allowedOrigins);
        }

        $normalizedOrigin = trim($originHeader);

        if ($normalizedOrigin === '') {
            return $this->resolveAllowedOriginFromPageUrl($pageUrl, $allowedOrigins);
        }

        $host = $this->extractHost($normalizedOrigin);
        if ($host === null) {
            return $this->resolveAllowedOriginFromPageUrl($pageUrl, $allowedOrigins);
        }

        if (!$this->isHostAllowed($host, $allowedOrigins)) {
            return $this->resolveAllowedOriginFromPageUrl($pageUrl, $allowedOrigins);
        }

        return $normalizedOrigin;
    }

    private function resolveAllowedOriginFromPageUrl(?string $pageUrl, array $allowedOrigins): ?string
    {
        if ($pageUrl === null || $pageUrl === '') {
            return null;
        }

        $normalizedPageUrl = trim($pageUrl);

        if ($normalizedPageUrl === '') {
            return null;
        }

        $origin = $this->extractOrigin($normalizedPageUrl);

        if ($origin === null) {
            return null;
        }

        $host = $this->extractHost($origin);
        if ($host === null) {
            return null;
        }

        if (!$this->isHostAllowed($host, $allowedOrigins)) {
            return null;
        }

        return $origin;
    }

    /**
     * @param string[] $allowedOrigins
     */
    private function isHostAllowed(?string $host, array $allowedOrigins): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        $host = strtolower($host);

        if ($allowedOrigins === []) {
            return false;
        }

        foreach ($allowedOrigins as $origin) {
            $rawAllowed = trim((string) $origin);
            if ($rawAllowed === '') {
                continue;
            }

            if ($rawAllowed === '*') {
                return true;
            }

            $allowedHost = $this->extractHost($rawAllowed) ?? strtolower($rawAllowed);
            if ($allowedHost === '') {
                continue;
            }

            if ($this->hostMatchesAllowed($host, $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private function hostMatchesAllowed(string $host, string $allowedHost): bool
    {
        if ($allowedHost === '*' || $host === $allowedHost) {
            return true;
        }

        if (str_starts_with($allowedHost, '*.')) {
            $suffix = substr($allowedHost, 2);
            if ($suffix === '') {
                return true;
            }

            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }

            return false;
        }

        if (str_contains($allowedHost, '*')) {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($allowedHost, '/')) . '$/';
            if (preg_match($pattern, $host) === 1) {
                return true;
            }
        }

        if (str_ends_with($host, '.' . $allowedHost)) {
            return true;
        }

        return false;
    }

    private function extractHost(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = $value;

        if (!str_contains($normalized, '://')) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        $host = parse_url($normalized, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        return null;
    }

    private function extractOrigin(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = $value;

        if (!str_contains($normalized, '://')) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        $parts = parse_url($normalized);

        if (!is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (!is_string($scheme) || !is_string($host) || $scheme === '' || $host === '') {
            return null;
        }

        $origin = strtolower($scheme) . '://' . strtolower($host);

        if (isset($parts['port']) && is_int($parts['port'])) {
            $defaultPort = null;
            if ($scheme === 'http') {
                $defaultPort = 80;
            } elseif ($scheme === 'https') {
                $defaultPort = 443;
            }

            if ($defaultPort === null || $parts['port'] !== $defaultPort) {
                $origin .= ':' . $parts['port'];
            }
        }

        return $origin;
    }
}
