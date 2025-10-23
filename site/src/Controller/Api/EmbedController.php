<?php

namespace App\Controller\Api;

use App\Controller\Api\WebChat\WebChatCorsTrait;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Repository\WebChat\WebChatSiteRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\RateLimiter\VisitorMessageRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmbedController extends AbstractController
{
    use WebChatCorsTrait;
    /**
     * Initialize an embed chat session for the requesting origin.
     *
     * Expected JSON payload:
     *  - site_key (string, required) — public identifier of the web chat site.
     *  - page_url (string, optional) — absolute URL of the page that hosts the widget; used for
     *    origin validation when the Origin header is absent.
     *
     * Successful response (200):
     *  - session_id (string) — identifier stored in the "web_session_id" cookie for subsequent
     *    requests.
     *  - socket_path (string) — Socket.IO namespace path for real-time updates.
     *  - room (string|null) — populated once the visitor is associated with a client record.
     *  - policy.maxTextLen (int) — maximum number of characters accepted by /api/embed/message.
     *
     * Error responses:
     *  - 400+ JSON payload containing "error" message for validation issues.
     *  - 403 when the site key is invalid or the request origin is not allow-listed.
     *  - 503 when the web chat storage backend is unavailable.
     *
     * CORS behaviour: when the request origin matches the site's allow list the controller
     * mirrors the Origin value in Access-Control-Allow-Origin and enables credentials so that
     * the session cookie can be persisted by the browser.
     */
    #[Route('/api/embed/init', name: 'api.embed.init', methods: ['GET', 'POST', 'OPTIONS'])]
    public function init(Request $request, WebChatSiteRepository $sites): Response
    {
        if ($response = $this->handlePreflight($request, $sites)) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $pageUrl = null;
        if (isset($data['page_url'])) {
            $pageUrlCandidate = trim((string) $data['page_url']);
            if ($pageUrlCandidate !== '') {
                $pageUrl = $pageUrlCandidate;
            }
        }
        if ($pageUrl === null) {
            $pageUrlQuery = $request->query->get('page_url');
            if (is_string($pageUrlQuery)) {
                $pageUrlQuery = trim($pageUrlQuery);
                if ($pageUrlQuery !== '') {
                    $pageUrl = $pageUrlQuery;
                }
            }
        }

        $fallbackOrigin = $this->guessRequestOrigin($request, $pageUrl);

        $siteKey = (string) $request->query->get('site_key', '');
        if ($siteKey === '' && isset($data['site_key'])) {
            $siteKey = (string) $data['site_key'];
        }
        $siteKey = trim($siteKey);
        if ($siteKey === '') {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid site key'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        if (!$sites->isStorageReady()) {
            return $this->applyCors(new JsonResponse(['error' => 'Web chat is not ready'], Response::HTTP_SERVICE_UNAVAILABLE), $request, $fallbackOrigin);
        }

        $site = $sites->findActiveBySiteKey($siteKey);

        if (!$site) {
            return $this->applyCors(new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        $originHeader = $request->headers->get('Origin');

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);
        if ($allowedOrigin === null) {
            return $this->applyCors(new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
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

        return $this->applyCors($response, $request, $allowedOrigin);
    }

    /**
     * Accept a visitor message submitted from the embedded chat widget.
     *
     * Expected JSON payload:
     *  - site_key (string, required) — public identifier of the web chat site.
     *  - text (string, required) — UTF-8 message body, trimmed and limited by policy.maxTextLen.
     *  - session_id (string, optional) — visitor session identifier when the cookie is missing.
     *  - page_url (string, optional) — absolute URL of the page that hosts the widget.
     *  - referrer (string, optional) — raw document.referrer value captured by the widget.
     *  - utm_* (string, optional) — any UTM markers forwarded as discrete fields; persisted as
     *    meta.source.utm.* for downstream analytics.
     *
     * Metadata captured for the ingest service:
     *  - meta.source.site_id — database identifier of the resolved web chat site.
     *  - meta.source.page_url — value provided via page_url.
     *  - meta.source.referrer — visitor referrer string when supplied.
     *  - meta.source.utm — associative array of forwarded utm_* fields.
     *  - meta.source.ip — resolved client IP from the Symfony request context.
     *  - meta.source.ua — raw User-Agent header.
     *
     * Successful response (200):
     *  - ok (bool) — indicates that the message was accepted for delivery.
     *  - clientId (string) — identifier of the resolved client entity.
     *  - room (string) — socket room name for follow-up events.
     *  - socket_path (string) — Socket.IO namespace path for real-time updates.
     *
     * Error responses:
     *  - 400 for invalid session or message text violations.
     *  - 403 when the site key is invalid or the request origin is not allow-listed.
     *  - 429 when a visitor exceeds 50 messages per minute (rate limit).
     *  - 500 when downstream message processing fails.
     *
     * CORS behaviour mirrors /api/embed/init. Rate limiting is enforced per session via a
     * lightweight cache-backed limiter service; cache failures do not block message processing.
     */
    #[Route('/api/embed/message', name: 'api.embed.message', methods: ['POST', 'OPTIONS'])]
    public function send(
        Request $request,
        WebChatSiteRepository $sites,
        MessageIngressService $ingress,
        VisitorMessageRateLimiter $messageRateLimiter
    ): Response {
        if ($response = $this->handlePreflight($request, $sites)) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }

        $pageUrl = isset($data['page_url']) ? (string) $data['page_url'] : null;
        if (is_string($pageUrl)) {
            $pageUrl = trim($pageUrl);
            if ($pageUrl === '') {
                $pageUrl = null;
            }
        }

        $fallbackOrigin = $this->guessRequestOrigin($request, $pageUrl);

        $siteKey = isset($data['site_key']) ? trim((string) $data['site_key']) : '';
        if ($siteKey === '') {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid site key'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        if (!$sites->isStorageReady()) {
            return $this->applyCors(new JsonResponse(['error' => 'Web chat is not ready'], Response::HTTP_SERVICE_UNAVAILABLE), $request, $fallbackOrigin);
        }

        $webChatSite = $sites->findActiveBySiteKey($siteKey);
        if (!$webChatSite) {
            return $this->applyCors(new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        $sessionId = $request->cookies->get('web_session_id');
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = isset($data['session_id']) ? trim((string) $data['session_id']) : '';
        }

        $originHeader = $request->headers->get('Origin');

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $webChatSite->getAllowedOrigins(), $pageUrl);
        if ($allowedOrigin === null) {
            return $this->applyCors(new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        $text = isset($data['text']) ? (string) $data['text'] : '';
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            return $this->jsonWithCors($request, $allowedOrigin, ['error' => 'Invalid message text'], Response::HTTP_BAD_REQUEST);
        }

        if ($sessionId === '') {
            return $this->jsonWithCors($request, $allowedOrigin, ['error' => 'Invalid session'], Response::HTTP_BAD_REQUEST);
        }

        $limit = $messageRateLimiter->consume($sessionId);

        if (!$limit->isAccepted()) {
            $response = $this->jsonWithCors($request, $allowedOrigin, ['error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);

            $retryAfter = $limit->getRetryAfter();
            if ($retryAfter instanceof \DateTimeInterface) {
                $response->headers->set('Retry-After', $retryAfter->format('U'));
            }

            return $response;
        }

        $redis = null;

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

        try {
            $ingress->accept($inbound);
        } catch (\Throwable) {
            return $this->jsonWithCors(
                $request,
                $allowedOrigin,
                ['error' => 'Message processing failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $client = $inbound->meta['_client'] ?? null;
        $persistedMessageId = $inbound->meta['_persisted_message_id'] ?? null;

        if ($client instanceof Client && null !== $persistedMessageId) {
            $socketPath = $_ENV['SOCKET_PATH'] ?? '/socket.io';
            $room = sprintf('client-%s', $client->getId());

            if ($redis === null) {
                $redis = $this->createRedisClient();
            }

            if ($redis !== null) {
                try {
                    $redis->publish("chat.client.{$client->getId()}", json_encode([
                        'id' => $persistedMessageId,
                        'clientId' => $client->getId(),
                        'room' => $room,
                        'text' => $inbound->text,
                        'direction' => 'in',
                        'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                } catch (\Throwable) {
                    // ignore publishing failures
                }
            }

            return $this->jsonWithCors($request, $allowedOrigin, [
                'ok' => true,
                'clientId' => $client->getId(),
                'room' => $room,
                'socket_path' => $socketPath,
            ]);
        }

        return $this->jsonWithCors(
            $request,
            $allowedOrigin,
            ['error' => 'Message processing failed'],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonWithCors(Request $request, ?string $allowedOrigin, array $payload, int $status = Response::HTTP_OK): Response
    {
        return $this->applyCors(new JsonResponse($payload, $status), $request, $allowedOrigin);
    }

    private function createRedisClient(): ?\Predis\Client
    {
        try {
            return new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_REALTIME_HOST'] ?? 'redis-realtime',
                'port' => (int) ($_ENV['REDIS_REALTIME_PORT'] ?? 6379),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
