<?php

declare(strict_types=1);

namespace App\Service\Messaging\Middleware;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Repository\Messaging\ClientRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;

final class PersistMiddleware implements MessageMiddlewareInterface
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(InboundMessage $m, callable $next): void
    {
        /** @var Client $client */
        $client = $this->clients->find($m->clientId);

        $bot = $client->getTelegramBot(); // если канал telegram; иначе приспособь под свою модель

        $msg = Message::messageIn(
            Uuid::uuid4()->toString(),
            $client,
            $bot,
            $m->text,
            $m->meta
        );

        $this->em->persist($msg);
        $this->em->flush();

        $m->meta['_persisted_message_id'] = $msg->getId();
        $next($m);
    }
}
