<?php

namespace App\Tests\Integration\Webhook;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TelegramWebhookNonTextTest extends WebTestCase
{
    public function testStickerIsSavedWithoutLlmCall(): void
    {
        $client = static::createClient();

        $payload = [
            'message' => [
                'message_id' => 123,
                'chat' => ['id' => 111, 'username' => 'u'],
                'sticker' => ['file_unique_id' => 'abc'], // нет text
            ],
        ];

        $client->request(
            'POST',
            '/webhook/telegram/bot/{token}', // подставьте тестовый
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        $this->assertResponseIsSuccessful();
        // проверь через репозитории, что Message(in) создан, meta.ingest.type=sticker и т.п.
    }
}
