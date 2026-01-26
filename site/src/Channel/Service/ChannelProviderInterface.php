<?php

declare(strict_types=1);

namespace App\Channel\Service;

use App\Channel\DTO\IncomingMessageDTO;
use App\Channel\Entity\Channel;
use Symfony\Component\HttpFoundation\Request;

interface ChannelProviderInterface
{
    public function supports(string $type): bool;

    /**
     * @return IncomingMessageDTO[]
     */
    public function handleWebhook(Channel $channel, Request $request): array;
}
