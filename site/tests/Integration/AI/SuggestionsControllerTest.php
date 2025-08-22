<?php

namespace App\Tests\Integration\AI;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SuggestionsControllerTest extends WebTestCase
{
    public function testHappyPath(): void
    {
        $client = static::createClient();
        // логинимся, активируем company в сессии
        $client->request('POST', '/api/suggestions/{clientId}', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'lastMessage' => 'Подскажите про доставку',
            'historyLimit' => 2,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data['suggestions'] ?? null);
    }

    public function testForbiddenWhenNotSameCompany(): void
    {
        // ...
        $this->assertResponseStatusCodeSame(403);
    }

    /*Эндпойнт POST /api/suggestions/{clientId}:
    happy path,
    403 при чужой компании,
    429 при rate‑limit (если включён).
    Скелет:*/
}
