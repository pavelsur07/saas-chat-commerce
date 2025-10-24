<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\WebChat\WebChatSite;
use App\Service\Messaging\Dto\InboundMessage;

/**
 * Создаёт InboundMessage на основе данных web-чата для унифицированного конвейера.
 */
final class WebChatInboundMessageFactory
{
    /**
     * @param array<string, mixed> $payload
     */
    public function createFromPayload(
        WebChatSite $site,
        array $payload,
        string $sessionId,
        string $text,
        ?string $pageUrl = null,
        ?string $clientIp = null,
        ?string $userAgent = null,
    ): InboundMessage {
        $referrer = null;
        if (isset($payload['referrer']) && is_scalar($payload['referrer'])) {
            $referrerCandidate = trim((string) $payload['referrer']);
            if ($referrerCandidate !== '') {
                $referrer = $referrerCandidate;
            }
        }

        $utm = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'utm_')) {
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $utm[$key] = $stringValue;
            }
        }

        $threadId = null;
        if (isset($payload['thread_id']) && is_scalar($payload['thread_id'])) {
            $threadCandidate = trim((string) $payload['thread_id']);
            if ($threadCandidate !== '') {
                $threadId = $threadCandidate;
            }
        }

        if ($pageUrl === null && isset($payload['page_url']) && is_scalar($payload['page_url'])) {
            $pageUrlCandidate = trim((string) $payload['page_url']);
            if ($pageUrlCandidate !== '') {
                $pageUrl = $pageUrlCandidate;
            }
        }

        $meta = [
            'company' => $site->getCompany(),
            'source' => [
                'site_id' => $site->getId(),
                'page_url' => $pageUrl,
                'referrer' => $referrer,
                'utm' => $utm,
                'ip' => $clientIp,
                'ua' => $userAgent,
            ],
            'raw' => $payload,
        ];

        if (null !== $threadId) {
            $meta['thread'] = [
                'id' => $threadId,
            ];
        }

        return new InboundMessage(
            channel: Channel::WEB->value,
            externalId: $sessionId,
            text: $text,
            meta: $meta,
        );
    }
}
