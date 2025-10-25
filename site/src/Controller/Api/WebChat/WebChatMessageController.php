<?php

declare(strict_types=1);

namespace App\Controller\Api\WebChat;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Message;
use App\Repository\Messaging\MessageRepository;
use App\Repository\WebChat\WebChatSiteRepository;
use App\Repository\WebChat\WebChatThreadRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\WebChat\WebChatToken;
use App\Service\WebChat\WebChatTokenService;
use App\Service\WebChat\WebChatRealtimePublisher;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class WebChatMessageController extends AbstractController
{
    use WebChatCorsTrait;

    #[Route('/api/webchat/messages', name: 'api.webchat.messages.list', methods: ['GET', 'OPTIONS'])]
    public function list(
        Request $request,
        WebChatSiteRepository $sites,
        WebChatThreadRepository $threads,
        MessageRepository $messages,
        WebChatTokenService $tokens,
    ): Response {
        if ($response = $this->handlePreflight($request, $sites)) {
            return $response;
        }

        $pageUrl = $request->query->get('page_url');
        if (is_string($pageUrl)) {
            $pageUrl = trim($pageUrl);
            if ($pageUrl === '') {
                $pageUrl = null;
            }
        } else {
            $pageUrl = null;
        }

        $fallbackOrigin = $this->guessRequestOrigin($request, $pageUrl);

        $siteKey = trim((string) $request->query->get('site_key', ''));
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

        try {
            $token = $this->requireToken($request, $tokens);
        } catch (AccessDeniedHttpException $exception) {
            return $this->applyCors(new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }
        if ($token->getSiteKey() !== $siteKey) {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid token audience'], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }

        $threadId = (string) $request->query->get('thread_id', '');
        if ($threadId === '' || !Uuid::isValid($threadId)) {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid thread'], Response::HTTP_BAD_REQUEST), $request, $allowedOrigin);
        }

        if ($token->getThreadId() !== $threadId) {
            return $this->applyCors(new JsonResponse(['error' => 'Token thread mismatch'], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }

        $thread = $threads->find($threadId);
        if (!$thread || $thread->getSite()->getId() !== $site->getId()) {
            return $this->applyCors(new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND), $request, $allowedOrigin);
        }

        $limit = (int) $request->query->get('limit', 50);
        $limit = max(1, min(200, $limit));

        $beforeId = $request->query->get('before_id');
        $since = $request->query->get('since');

        $items = [];
        if (is_string($since) && $since !== '') {
            try {
                $sinceMoment = new DateTimeImmutable($since);
                $items = $messages->findThreadSince($thread, $sinceMoment);
            } catch (\Exception) {
                $items = $messages->findLatestForThread($thread, $limit);
            }
        } elseif (is_string($beforeId) && $beforeId !== '') {
            $beforeMessage = $messages->find($beforeId);
            if ($beforeMessage instanceof Message && $beforeMessage->getThread()?->getId() === $thread->getId()) {
                $items = $messages->findThreadBefore($thread, $beforeMessage, $limit);
            } else {
                $items = [];
            }
        } else {
            $items = $messages->findLatestForThread($thread, $limit);
        }

        $response = new JsonResponse([
            'messages' => array_map([$this, 'mapMessage'], $items),
        ]);

        return $this->applyCors($response, $request, $allowedOrigin);
    }

    #[Route('/api/webchat/messages', name: 'api.webchat.messages.create', methods: ['POST', 'OPTIONS'])]
    public function create(
        Request $request,
        WebChatSiteRepository $sites,
        WebChatThreadRepository $threads,
        MessageRepository $messages,
        MessageIngressService $ingress,
        WebChatTokenService $tokens,
        EntityManagerInterface $em,
        WebChatRealtimePublisher $publisher,
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

        $site = $sites->findActiveBySiteKey($siteKey);
        if (!$site) {
            return $this->applyCors(new JsonResponse(['error' => 'Site not found'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        $originHeader = $request->headers->get('Origin');
        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);
        if ($allowedOrigin === null) {
            return $this->applyCors(new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN), $request, $fallbackOrigin);
        }

        try {
            $token = $this->requireToken($request, $tokens);
        } catch (AccessDeniedHttpException $exception) {
            return $this->applyCors(new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }
        if ($token->getSiteKey() !== $siteKey) {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid token audience'], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }

        $threadId = isset($data['thread_id']) ? (string) $data['thread_id'] : '';
        if ($threadId === '' || !Uuid::isValid($threadId)) {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid thread'], Response::HTTP_BAD_REQUEST), $request, $allowedOrigin);
        }
        if ($token->getThreadId() !== $threadId) {
            return $this->applyCors(new JsonResponse(['error' => 'Token thread mismatch'], Response::HTTP_FORBIDDEN), $request, $allowedOrigin);
        }

        $thread = $threads->find($threadId);
        if (!$thread || $thread->getSite()->getId() !== $site->getId()) {
            return $this->applyCors(new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND), $request, $allowedOrigin);
        }

        $text = isset($data['text']) ? trim((string) $data['text']) : '';
        if ($text === '' || mb_strlen($text) > 2000) {
            return $this->applyCors(new JsonResponse(['error' => 'Invalid message text'], Response::HTTP_BAD_REQUEST), $request, $allowedOrigin);
        }

        $dedupeKey = null;
        if (isset($data['dedupe_key']) && is_string($data['dedupe_key'])) {
            $dedupeKey = trim($data['dedupe_key']);
        }
        $tmpId = isset($data['tmp_id']) && is_string($data['tmp_id']) ? trim($data['tmp_id']) : null;
        if ($dedupeKey === null && $tmpId !== null && $tmpId !== '') {
            $dedupeKey = hash('sha256', $threadId . ':' . $tmpId . ':' . $text);
        }

        if ($dedupeKey !== null && $dedupeKey !== '') {
            $existingMessage = $messages->findOneByThreadAndDedupe($thread, $dedupeKey);
            if ($existingMessage instanceof Message) {
                $response = new JsonResponse([
                    'message_id' => $existingMessage->getId(),
                    'created_at' => $existingMessage->getCreatedAt()->format(DATE_ATOM),
                    'status' => 'delivered',
                ]);

                return $this->applyCors($response, $request, $allowedOrigin);
            }
        }

        $client = $thread->getClient();
        $inbound = new InboundMessage(
            channel: Channel::WEB->value,
            externalId: $client->getExternalId(),
            text: $text,
            meta: [
                'company' => $client->getCompany(),
                'source' => [
                    'site_id' => $site->getId(),
                    'thread_id' => $thread->getId(),
                    'page_url' => $pageUrl,
                    'ip' => $request->getClientIp(),
                    'ua' => $request->headers->get('User-Agent'),
                ],
                'webchat' => [
                    'thread_id' => $thread->getId(),
                    'site_key' => $siteKey,
                ],
            ]
        );
        $inbound->clientId = $client->getId();

        try {
            $ingress->accept($inbound);
        } catch (\Throwable) {
            return $this->applyCors(new JsonResponse(['error' => 'Message processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR), $request, $allowedOrigin);
        }

        $persistedId = $inbound->meta['_persisted_message_id'] ?? null;
        if (!is_string($persistedId) || $persistedId === '') {
            return $this->applyCors(new JsonResponse(['error' => 'Message processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR), $request, $allowedOrigin);
        }

        $message = $messages->find($persistedId);
        if (!$message instanceof Message) {
            return $this->applyCors(new JsonResponse(['error' => 'Message processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR), $request, $allowedOrigin);
        }

        $message->setThread($thread);
        if ($dedupeKey !== null && $dedupeKey !== '') {
            $message->setDedupeKey($dedupeKey);
        }
        if ($tmpId !== null && $tmpId !== '') {
            $message->setSourceId($tmpId);
        }

        $em->flush();
        $publisher->publishMessage($thread, $message);

        $response = new JsonResponse([
            'message_id' => $message->getId(),
            'created_at' => $message->getCreatedAt()->format(DATE_ATOM),
            'status' => 'delivered',
        ]);

        return $this->applyCors($response, $request, $allowedOrigin);
    }

    private function mapMessage(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'direction' => $message->getDirection(),
            'text' => $message->getText(),
            'payload' => $message->getPayload(),
            'created_at' => $message->getCreatedAt()->format(DATE_ATOM),
            'delivered_at' => $message->getDeliveredAt()?->format(DATE_ATOM),
            'read_at' => $message->getReadAt()?->format(DATE_ATOM),
        ];
    }

    private function requireToken(Request $request, WebChatTokenService $tokens): WebChatToken
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            throw $this->createAccessDeniedException('Missing authorization token');
        }

        $tokenString = trim(substr($header, 7));
        if ($tokenString === '') {
            throw $this->createAccessDeniedException('Missing authorization token');
        }

        try {
            return $tokens->parse($tokenString);
        } catch (\Throwable $e) {
            throw $this->createAccessDeniedException($e->getMessage());
        }
    }
}
