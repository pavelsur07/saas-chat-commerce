<?php

declare(strict_types=1);

namespace App\Service\WebChat;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Entity\WebChat\WebChatSite;
use App\Entity\WebChat\WebChatThread;
use App\Repository\Messaging\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class WebChatSessionService
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly WebChatThreadManager $threads,
        private readonly WebChatTokenService $tokens,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handshake(WebChatSite $site, string $visitorId, ?string $sessionId = null, array $meta = []): WebChatSession
    {
        $client = $this->clients->findOneByWebChatSiteAndVisitor($site, $visitorId);

        if (null === $client) {
            $client = new Client(Uuid::uuid4()->toString(), Channel::WEB, $visitorId, $site->getCompany());
            $client->setWebChatSite($site);
            $client->setUsername(null);
            $client->setFirstName(null);
            $client->setLastName(null);
            $client->setMeta([]);
            $this->em->persist($client);
        }

        $client->setWebChatSite($site);
        if ($meta !== []) {
            $client->mergeMeta($meta);
        }
        $client->touchLastSeen();

        $thread = $this->threads->ensureActiveThread($client, $site);

        $this->em->flush();

        $token = $this->tokens->issue($site->getSiteKey(), $visitorId, $thread->getId());

        return new WebChatSession($client, $thread, $token, $sessionId ?? Uuid::uuid4()->toString());
    }

    public function rotateToken(WebChatSite $site, Client $client, WebChatThread $thread): WebChatToken
    {
        return $this->tokens->issue($site->getSiteKey(), $client->getExternalId(), $thread->getId());
    }
}
