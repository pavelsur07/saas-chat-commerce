<?php

namespace App\Controller\Api;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Repository\WebChat\WebChatSiteRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmbedController extends AbstractController
{
    #[Route('/api/embed/init', name: 'api.embed.init', methods: ['POST'])]
    public function init(Request $request, WebChatSiteRepository $sites): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $siteKey = isset($data['site_key']) ? (string) $data['site_key'] : '';
        $siteKey = trim($siteKey);
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
        $pageUrl = isset($data['page_url']) ? (string) $data['page_url'] : null;
        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);

        if (!$this->isHostAllowed($host, $site->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $sessionId = $request->cookies->get('web_session_id');
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = bin2hex(random_bytes(16));
        }

        $socketPath = $_ENV['SOCKET_PATH'] ?? '/socket.io';

        $response = new JsonResponse([
            'session_id' => $sessionId,
            'socket_path' => $socketPath,
            'room' => null,
            'policy' => [
                'maxTextLen' => 2000,
            ],
        ]);

        $cookie = Cookie::create('web_session_id', $sessionId)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/api/embed/message', name: 'api.embed.message', methods: ['POST'])]
    public function send(
        Request $request,
        WebChatSiteRepository $sites,
        MessageIngressService $ingress
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $siteKey = isset($data['site_key']) ? trim((string) $data['site_key']) : '';
        if ($siteKey === '') {
            return new JsonResponse(['error' => 'Invalid site key'], Response::HTTP_FORBIDDEN);
        }

        if (!$sites->isStorageReady()) {
            return new JsonResponse(['error' => 'Web chat is not ready'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $webChatSite = $sites->findActiveBySiteKey($siteKey);
        if (!$webChatSite) {
            return new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN);
        }

        $text = isset($data['text']) ? (string) $data['text'] : '';
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            return new JsonResponse(['error' => 'Invalid message text'], Response::HTTP_BAD_REQUEST);
        }

        $sessionId = $request->cookies->get('web_session_id');
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = isset($data['session_id']) ? trim((string) $data['session_id']) : '';
        }

        if ($sessionId === '') {
            return new JsonResponse(['error' => 'Invalid session'], Response::HTTP_BAD_REQUEST);
        }

        $originHeader = $request->headers->get('Origin');
        $pageUrl = isset($data['page_url']) ? (string) $data['page_url'] : null;
        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);

        if (!$this->isHostAllowed($host, $webChatSite->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $referrer = isset($data['referrer']) ? (string) $data['referrer'] : null;
        $utm = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'utm_')) {
                continue;
            }

            if (is_scalar($value)) {
                $stringValue = trim((string) $value);
                if ($stringValue !== '') {
                    $utm[$key] = $stringValue;
                }
            }
        }

        $inbound = new InboundMessage(
            Channel::WEB->value,
            $sessionId,
            $text,
            meta: [
                'company' => $webChatSite->getCompany(),
                'source' => [
                    'site_id' => $webChatSite->getId(),
                    'page_url' => $pageUrl,
                    'referrer' => $referrer,
                    'utm' => $utm,
                    'ip' => $request->getClientIp(),
                    'ua' => $request->headers->get('User-Agent'),
                ],
            ]
        );

        $ingress->accept($inbound);

        $client = $inbound->meta['_client'] ?? null;
        $persistedMessageId = $inbound->meta['_persisted_message_id'] ?? null;

        if ($client instanceof Client && null !== $persistedMessageId) {
            try {
                $redis = new \Predis\Client([
                    'scheme' => 'tcp',
                    'host' => $_ENV['REDIS_REALTIME_HOST'] ?? 'redis-realtime',
                    'port' => (int) ($_ENV['REDIS_REALTIME_PORT'] ?? 6379),
                ]);
                $redis->publish("chat.client.{$client->getId()}", json_encode([
                    'id' => $persistedMessageId,
                    'clientId' => $client->getId(),
                    'text' => $inbound->text,
                    'direction' => 'in',
                    'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
                // ignore publishing failures
            }

            return new JsonResponse([
                'ok' => true,
                'clientId' => $client->getId(),
                'room' => 'client-' . $client->getId(),
            ]);
        }

        return new JsonResponse(['error' => 'Message processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $allowedHost = $this->extractHost($origin) ?? strtolower((string) $origin);
            if ($allowedHost === '') {
                continue;
            }

            if ($host === $allowedHost) {
                return true;
            }

            if (str_ends_with($host, '.' . $allowedHost)) {
                return true;
            }
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
}
