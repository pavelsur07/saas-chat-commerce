<?php

namespace App\Controller\Webhook;

use App\Entity\Company\Company;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\Messaging\TelegramInboundMessageFactory;
use App\Service\Realtime\RealtimePublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookController extends AbstractController
{
    #[Route(
        '/webhook/telegram/bot/{token}',
        name: 'telegram.webhook',
        requirements: ['token' => '.+'],
        methods: ['POST']
    )]
    public function handleWebhook(
        string $token,
        Request $request,
        TelegramBotRepository $botRepo,
        MessageIngressService $ingress,
        TelegramInboundMessageFactory $messageFactory,
        RealtimePublisher $realtimePublisher,
    ): JsonResponse {
        $bot = $botRepo->findOneBy(['token' => $token, 'isActive' => true]);
        if (!$bot) {
            return new JsonResponse(['error' => 'Invalid bot'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $inbound = $messageFactory->createFromUpdate($bot, $data);

        if (!$inbound instanceof InboundMessage) {
            return new JsonResponse(['ok' => true]);
        }

        $ingress->accept($inbound);

        $company = $inbound->meta['company'] ?? null;
        if ($company instanceof Company && null !== $company->getId()) {
            $meta = $inbound->meta;
            unset($meta['company'], $meta['_client']);

            $payload = [
                'channel' => $inbound->channel,
                'externalId' => $inbound->externalId,
                'text' => $inbound->text,
                'direction' => 'in',
                'meta' => $meta,
            ];

            if (null !== $inbound->clientId) {
                $payload['clientId'] = $inbound->clientId;
            }

            if (isset($payload['meta']['_persisted_message_id'])) {
                $payload['id'] = (string) $payload['meta']['_persisted_message_id'];
                unset($payload['meta']['_persisted_message_id']);
            }

            $ingestTimestamp = $payload['meta']['ingest']['date'] ?? null;
            if (is_numeric($ingestTimestamp)) {
                try {
                    $payload['timestamp'] = (new \DateTimeImmutable('@'.(int) $ingestTimestamp))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    // ignore invalid ingest timestamp, we'll fallback to "now"
                }
            }

            $payload['timestamp'] ??= (new \DateTimeImmutable())->format(DATE_ATOM);

            $realtimePublisher->toCompany($company->getId(), 'message.inbound', $payload);

            if (null !== ($payload['clientId'] ?? null)) {
                $realtimePublisher->toClient($payload['clientId'], 'message.inbound', $payload);
            }
        }

        return new JsonResponse(['ok' => true]);
    }
}
