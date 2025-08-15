<?php

namespace App\Service\Messaging\Middleware;

use App\Entity\Messaging\Client;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class NormalizeMiddleware implements MessageMiddlewareInterface
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly TelegramBotRepository $botRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(InboundMessage $m, callable $next): void
    {
        // 1) ищем клиента по единому ключу: channel + externalId
        $client = $this->clients->findOneByChannelAndExternalId($m->channel, $m->externalId);

        if (!$client) {
            $client = new Client(
                id: Uuid::uuid4()->toString(),
                channel: $m->channel,
                externalId: $m->externalId,
                company: $m->meta['company'] ?? null
            );
        }

        // 2) обновим базовые поля
        $client->setUsername($m->meta['username'] ?? $client->getUsername());
        $client->setFirstName($m->meta['firstName'] ?? $client->getFirstName());
        $client->setLastName($m->meta['lastName'] ?? $client->getLastName());

        // 3) для Telegram — заполним telegramId и привяжем бота
        if (Client::TELEGRAM === $m->channel) {
            // telegramId (int) из externalId (string chat.id)
            if (ctype_digit($m->externalId)) {
                $client->setTelegramId((int) $m->externalId);
            }

            if (!empty($m->meta['bot_id']) && !$client->getTelegramBot()) {
                $bot = $this->botRepo->find($m->meta['bot_id']);
                if ($bot) {
                    $client->setTelegramBot($bot);
                }
            }
        }

        $this->em->persist($client);
        $this->em->flush();

        $m->clientId = $client->getId();
        $next($m);
    }
}
