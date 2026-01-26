<?php

declare(strict_types=1);

namespace App\Channel\Service;

use App\Channel\DTO\IncomingMessageDTO;
use App\Channel\Entity\Channel;
use Symfony\Component\HttpFoundation\Request;

class AvitoProvider implements ChannelProviderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'avito';
    }

    public function handleWebhook(Channel $channel, Request $request): array
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        return [
            new IncomingMessageDTO(
                channelType: $channel->getType(),
                externalId: (string) ($payload['sender']['id'] ?? ''),
                text: (string) ($payload['message']['text'] ?? ''),
                raw: $payload,
            ),
        ];
    }
}
