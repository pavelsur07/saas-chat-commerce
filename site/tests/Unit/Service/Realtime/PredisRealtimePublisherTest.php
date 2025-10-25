<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Realtime;

use App\Service\Realtime\PredisRealtimePublisher;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

use function json_decode;
use function strlen;

/**
 * @covers \App\Service\Realtime\PredisRealtimePublisher
 */
final class PredisRealtimePublisherTest extends TestCase
{
    public function testToClientPublishesFlattenedPayload(): void
    {
        $redis = $this->createMock(ClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $redis
            ->expects(self::once())
            ->method('publish')
            ->with(
                'chat.client.42',
                self::callback(static function (string $message): bool {
                    $decoded = json_decode($message, true, flags: JSON_THROW_ON_ERROR);

                    self::assertIsArray($decoded);
                    self::assertSame('message.inbound', $decoded['event']);
                    self::assertSame('42', $decoded['clientId']);
                    self::assertSame('hello', $decoded['text']);
                    self::assertSame([
                        'clientId' => '42',
                        'text' => 'hello',
                    ], $decoded['data']);
                    self::assertArrayHasKey('occurred_at', $decoded);
                    self::assertArrayHasKey('correlation_id', $decoded);
                    self::assertSame(16, strlen($decoded['correlation_id']));

                    return true;
                })
            );

        $publisher = new PredisRealtimePublisher($redis, $logger);
        $publisher->toClient('42', 'message.inbound', [
            'clientId' => '42',
            'text' => 'hello',
        ]);
    }
}
