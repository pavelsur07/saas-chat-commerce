<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messaging;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\WebChat\WebChatSite;
use App\Service\Messaging\WebChatInboundMessageFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WebChatInboundMessageFactoryTest extends TestCase
{
    public function testCreateFromPayloadBuildsInboundMessage(): void
    {
        $owner = new User(Uuid::v4()->toRfc4122());
        $company = new Company(Uuid::v4()->toRfc4122(), $owner);
        $company->setName('Acme Inc.');
        $company->setSlug('acme');

        $site = new WebChatSite(
            Uuid::v4()->toRfc4122(),
            $company,
            'Main site',
            'site-key',
        );

        $payload = [
            'thread_id' => 'thread-42',
            'referrer' => 'https://example.com/ref',
            'utm_source' => ' newsletter ',
            'utm_medium' => null,
            'page_url' => 'https://example.com/start',
        ];

        $factory = new WebChatInboundMessageFactory();

        $message = $factory->createFromPayload(
            site: $site,
            payload: $payload,
            sessionId: 'session-123',
            text: 'Hello from web chat',
            pageUrl: 'https://example.com/landing',
            clientIp: '203.0.113.10',
            userAgent: 'Mozilla/5.0',
        );

        self::assertSame(Channel::WEB->value, $message->channel);
        self::assertSame('session-123', $message->externalId);
        self::assertSame('Hello from web chat', $message->text);

        $meta = $message->meta;
        self::assertSame($company, $meta['company']);
        self::assertSame($site->getId(), $meta['source']['site_id']);
        self::assertSame('https://example.com/landing', $meta['source']['page_url']);
        self::assertSame('https://example.com/ref', $meta['source']['referrer']);
        self::assertSame(['utm_source' => 'newsletter'], $meta['source']['utm']);
        self::assertSame('203.0.113.10', $meta['source']['ip']);
        self::assertSame('Mozilla/5.0', $meta['source']['ua']);
        self::assertSame(['id' => 'thread-42'], $meta['thread']);
        self::assertSame($payload, $meta['raw']);
    }
}
