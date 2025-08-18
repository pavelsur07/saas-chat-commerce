<?php

namespace App\Tests\Integration\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SuggestionControllerTest extends WebTestCase
{
    public function testSuggestReturnsJsonArray(): void
    {
        $client = static::createClient(); // не вызывать kernel::boot перед этим!

        // Логинимся тестовым пользователем (подставь свой способ)
        // $this->login($client);

        $clientId = 1; // фикстура
        $client->request('POST', "/api/suggestions/{$clientId}");

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertIsArray($data['suggestions']);
    }
}
