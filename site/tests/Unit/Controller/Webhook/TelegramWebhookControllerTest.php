<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Webhook;

use App\Controller\Webhook\TelegramWebhookController;
use App\Entity\Company\Company;
use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\Messaging\TelegramInboundMessageFactory;
use App\Service\Realtime\RealtimePublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;
use function json_encode;

final class TelegramWebhookControllerTest extends TestCase
{
    public function testPublishesRealtimeEventsForCompanyAndClient(): void
    {
        $company = $this->createConfiguredMock(Company::class, [
            'getId' => 'company-1',
        ]);

        $bot = $this->createConfiguredMock(TelegramBot::class, [
            'getCompany' => $company,
        ]);

        $botRepo = $this->createMock(TelegramBotRepository::class);
        $botRepo
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['token' => 'secret-token', 'isActive' => true])
            ->willReturn($bot);

        $ingress = $this->createMock(MessageIngressService::class);
        $ingress
            ->expects(self::once())
            ->method('accept')
            ->with(self::callback(static function (InboundMessage $message): bool {
                self::assertSame('client-9', $message->clientId);

                return true;
            }));

        $inbound = new InboundMessage(
            channel: 'telegram',
            externalId: 'chat-7',
            text: 'Hello from Telegram',
            clientId: 'client-9',
            meta: [
                'company' => $company,
                '_client' => new \stdClass(),
                '_persisted_message_id' => 'msg-42',
                'ingest' => ['date' => 1_700_000_000],
                'extra' => 'value',
            ],
        );

        $factory = $this->createMock(TelegramInboundMessageFactory::class);
        $factory
            ->expects(self::once())
            ->method('createFromUpdate')
            ->willReturn($inbound);

        $publisher = $this->createMock(RealtimePublisher::class);

        $expectedPayloadAssertion = static function (array $payload): bool {
            self::assertSame('telegram', $payload['channel']);
            self::assertSame('chat-7', $payload['externalId']);
            self::assertSame('Hello from Telegram', $payload['text']);
            self::assertSame('in', $payload['direction']);
            self::assertSame('client-9', $payload['clientId']);
            self::assertSame('msg-42', $payload['id']);
            self::assertSame('2023-11-14T22:13:20+00:00', $payload['timestamp']);
            self::assertSame('value', $payload['meta']['extra']);
            self::assertArrayNotHasKey('company', $payload['meta']);
            self::assertArrayNotHasKey('_client', $payload['meta']);
            self::assertArrayNotHasKey('_persisted_message_id', $payload['meta']);

            return true;
        };

        $publisher
            ->expects(self::once())
            ->method('toCompany')
            ->with('company-1', 'message.inbound', self::callback($expectedPayloadAssertion));

        $publisher
            ->expects(self::once())
            ->method('toClient')
            ->with('client-9', 'message.inbound', self::callback($expectedPayloadAssertion));

        $controller = new TelegramWebhookController();

        $request = new Request(content: json_encode(['update_id' => 1], JSON_THROW_ON_ERROR));

        $response = $controller->handleWebhook(
            'secret-token',
            $request,
            $botRepo,
            $ingress,
            $factory,
            $publisher,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['ok' => true], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }
}
