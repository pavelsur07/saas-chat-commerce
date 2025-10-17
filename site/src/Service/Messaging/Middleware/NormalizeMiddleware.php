<?php

namespace App\Service\Messaging\Middleware;

use App\Entity\Messaging\Channel\Channel;
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
        // Нормализуем канал к enum (вход у нас строка)
        $channelEnum = Channel::tryFromCaseInsensitive($m->channel);
        $channelValue = $channelEnum?->value ?? $m->channel; // храним строку, как в проекте

        // 1) Поиск клиента по ключу: (channel, externalId)
        $client = $this->clients->findOneByChannelAndExternalId($channelValue, $m->externalId);

        // 2) Создаём при отсутствии
        if (!$client) {
            $client = new Client(
                id: Uuid::uuid4()->toString(),
                channel: $channelValue,              // строка, НЕ enum-объект
                externalId: $m->externalId,
                company: $m->meta['company'] ?? null
            );
        }

        // 3) Обновляем базовые поля (без перетирания пустыми)
        $client->setUsername($m->meta['username'] ?? $client->getUsername());
        $client->setFirstName($m->meta['firstName'] ?? $client->getFirstName());
        $client->setLastName($m->meta['lastName'] ?? $client->getLastName());

        // 4) Специфика Telegram
        if ($channelEnum === Channel::TELEGRAM) {
            if (ctype_digit($m->externalId)) {
                $client->setTelegramId((int) $m->externalId); // chat.id
            }
            if (!empty($m->meta['bot_id']) && !$client->getTelegramBot()) {
                $bot = $this->botRepo->find($m->meta['bot_id']);
                if ($bot) {
                    $client->setTelegramBot($bot);
                }
            }
        }

        // 5) Сохраняем клиента и прокидываем вниз по конвейеру
        $this->em->persist($client);
        $this->em->flush();

        $m->meta['_client'] = $client;

        // 6) Продолжаем обработку
        $next($m);
    }
}
