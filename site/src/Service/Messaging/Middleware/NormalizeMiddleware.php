<?php

declare(strict_types=1);

namespace App\Service\Messaging\Middleware;

use App\Entity\Messaging\Client;
use App\Repository\Messaging\ClientRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;

final class NormalizeMiddleware implements MessageMiddlewareInterface
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(InboundMessage $m, callable $next): void
    {
        if ($m->clientId) {
            $next($m);

            return;
        }

        $client = $this->clients->findOneByChannelAndExternalId($m->channel, $m->externalId);
        if (!$client) {
            $client = new Client(
                id: Uuid::uuid4()->toString(),
                channel: $m->channel,
                externalId: $m->externalId,
                company: $m->meta['company'] ?? null
            );
            $client->setUsername($m->meta['username'] ?? null);
            $client->setFirstName($m->meta['firstName'] ?? null);
            $client->setLastName($m->meta['lastName'] ?? null);

            $this->em->persist($client);
            $this->em->flush();
        }

        $m->clientId = $client->getId();
        $next($m);
    }
}
