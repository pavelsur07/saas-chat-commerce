<?php

declare(strict_types=1);

namespace App\Controller\Api\WebChat;

use App\Entity\Messaging\Message;
use App\Repository\Messaging\MessageRepository;
use App\Repository\WebChat\WebChatSiteRepository;
use App\Repository\WebChat\WebChatThreadRepository;
use App\Service\WebChat\WebChatMessageService;
use App\Service\WebChat\WebChatToken;
use App\Service\WebChat\WebChatTokenService;
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
        if (!is_string($pageUrl)) {
            $pageUrl = null;
        }

        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);
        if (!$this->isHostAllowed($host, $site->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);

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
        WebChatMessageService $messageService,
        WebChatTokenService $tokens,
        EntityManagerInterface $em,
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
        $pageUrl = isset($data['page_url']) ? (string) $data['page_url'] : null;
        $host = $this->extractHost($originHeader) ?? $this->extractHost($pageUrl);
        if (!$this->isHostAllowed($host, $site->getAllowedOrigins())) {
            return new JsonResponse(['error' => 'Origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $allowedOrigin = $this->resolveAllowedOrigin($originHeader, $site->getAllowedOrigins(), $pageUrl);

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

        $message = $messageService->createInbound($thread, $text, $dedupeKey, $tmpId);
        $em->flush();

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
