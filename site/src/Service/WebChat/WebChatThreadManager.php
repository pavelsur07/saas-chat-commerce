<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Client;
use App\Entity\WebChat\WebChatSite;
use App\Entity\WebChat\WebChatThread;
use App\Repository\WebChat\WebChatThreadRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class WebChatThreadManager
{
    private DateInterval $staleAfter;

    public function __construct(
        private readonly WebChatThreadRepository $threads,
        private readonly EntityManagerInterface $em,
        ?DateInterval $staleAfter = null,
    ) {
        $this->staleAfter = $staleAfter ?? new DateInterval('P30D');
    }

    public function ensureActiveThread(Client $client, WebChatSite $site): WebChatThread
    {
        $existing = $this->threads->findOpenForClient($client);
        $now = new DateTimeImmutable();

        if ($existing !== null && !$existing->isStale($this->staleAfter, $now)) {
            return $existing;
        }

        $latest = $existing ?? $this->threads->findLatestForClient($client);
        if ($latest !== null && !$latest->isOpen() && !$latest->isStale($this->staleAfter, $now)) {
            $latest->reopen($now);

            return $latest;
        }

        if ($latest !== null && !$latest->isStale($this->staleAfter, $now)) {
            $latest->reopen($now);

            return $latest;
        }

        $thread = new WebChatThread(Uuid::uuid4()->toString(), $site, $client);
        $this->em->persist($thread);

        return $thread;
    }
}
