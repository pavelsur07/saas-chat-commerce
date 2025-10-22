<?php

declare(strict_types=1);

namespace App\Controller\Api\WebChat;

use App\Repository\WebChat\WebChatSiteRepository;
use App\Service\WebChat\WebChatSessionService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebChatHandshakeController extends AbstractController
{
    use WebChatCorsTrait;

    #[Route('/api/webchat/handshake', name: 'api.webchat.handshake', methods: ['POST', 'OPTIONS'])]
    public function __invoke(
        Request $request,
        WebChatSiteRepository $sites,
        WebChatSessionService $sessions,
    ): Response {
        if ($response = $this->handlePreflight($request, $sites)) {
            return $response;
        }

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

        $site = $sites->findActiveBySiteKey($siteKey);
        if (!$site) {
            return new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN);
        }

        $originHeader = $request->headers->get('Origin');
        $pageUrl = isset($data['page_url']) ? (string) $data['page_url'] : $request->query->get('page_url');
        if (!is_string($pageUrl)) {
            $pageUrl = null;
        }

        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);
        if (!$this->isHostAllowed($host, $site->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);

        $visitorId = isset($data['visitor_id']) ? trim((string) $data['visitor_id']) : '';
        if ($visitorId !== '' && !Uuid::isValid($visitorId)) {
            $visitorId = '';
        }
        if ($visitorId === '') {
            $cookieVisitor = $request->cookies->get('wc_vid');
            if (is_string($cookieVisitor) && Uuid::isValid($cookieVisitor)) {
                $visitorId = $cookieVisitor;
            }
        }
        if ($visitorId === '') {
            $visitorId = Uuid::uuid4()->toString();
        }

        $sessionId = $request->cookies->get('wc_sid');
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = Uuid::uuid4()->toString();
        }

        $meta = [
            'ip' => $request->getClientIp(),
            'ua' => $request->headers->get('User-Agent'),
            'page' => $pageUrl,
        ];

        $session = $sessions->handshake($site, $visitorId, $sessionId, $meta);

        $payload = $session->toArray();
        $payload['socket_path'] = $_ENV['SOCKET_PATH'] ?? '/socket.io';
        $payload['site_key'] = $siteKey;

        $response = new JsonResponse($payload);

        $visitorCookie = Cookie::create('wc_vid', $session->getClient()->getExternalId())
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withExpires((new \DateTimeImmutable('+365 days')));

        $sessionCookie = Cookie::create('wc_sid', $session->getSessionId())
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($visitorCookie);
        $response->headers->setCookie($sessionCookie);

        return $this->applyCors($response, $request, $allowedOrigin);
    }
}
