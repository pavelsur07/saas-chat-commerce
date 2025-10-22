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

final class WebChatAckController extends AbstractController
{
    use WebChatCorsTrait;

    #[Route('/api/webchat/ack', name: 'api.webchat.ack', methods: ['POST', 'OPTIONS'])]
    public function __invoke(
        Request $request,
        WebChatSiteRepository $sites,
        WebChatThreadRepository $threads,
        MessageRepository $messages,
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

        $deliveredIds = $this->normalizeIds($data['delivered'] ?? []);
        $readIds = $this->normalizeIds($data['read'] ?? []);

        $now = new DateTimeImmutable();
        $updatedDelivered = [];
        foreach ($deliveredIds as $id) {
            $message = $messages->findOneInThread($thread, $id);
            if ($message instanceof Message && $message->getDirection() === Message::OUT) {
                if ($message->getDeliveredAt() === null) {
                    $message->markDelivered($now);
                    $updatedDelivered[] = $message->getId();
                }
            }
        }

        $updatedRead = [];
        foreach ($readIds as $id) {
            $message = $messages->findOneInThread($thread, $id);
            if ($message instanceof Message && $message->getDirection() === Message::OUT) {
                if ($message->getReadAt() === null) {
                    $message->markRead($now);
                    $updatedRead[] = $message->getId();
                }
            }
        }

        $em->flush();

        if ($updatedDelivered !== []) {
            $messageService->publishStatus($thread, $updatedDelivered, 'delivered');
        }
        if ($updatedRead !== []) {
            $messageService->publishStatus($thread, $updatedRead, 'read');
        }

        $response = new JsonResponse([
            'ok' => true,
            'updated' => [
                'delivered' => $updatedDelivered,
                'read' => $updatedRead,
            ],
        ]);

        return $this->applyCors($response, $request, $allowedOrigin);
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function normalizeIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && Uuid::isValid($item)) {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
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
