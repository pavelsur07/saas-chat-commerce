<?php

namespace App\Tests\Unit\AI;

use App\AI\SuggestionService;
use App\Service\AI\LlmClient;
use PHPUnit\Framework\TestCase;

class SuggestionServiceParseTest extends TestCase
{
    public function testParsesStrictJson(): void
    {
        $llm = $this->createMock(LlmClient::class);
        $llm->method('chat')->willReturn([
            'content' => '{"suggestions":
            [
                {"text":"Вам подойдёт...","confidence":0.71},
                {"text":"Мы доставляем...","confidence":0.66}
            ]
        }', // валидный JSON
        ]);

        $svc = new SuggestionService($llm/* ... deps можно замокать или null */);
        $resp = $svc->suggest(/* company */ null, /* client */ null, 'Текст клиента', 3);
        $this->assertCount(2, $resp->getSuggestions());
        $this->assertSame('Вам подойдёт...', $resp->getSuggestions()[0]->text);
    }

    public function testReturnsEmptyOnInvalidJson(): void
    {
        $llm = $this->createMock(LlmClient::class);
        $llm->method('chat')->willReturn(['content' => 'not a json']);

        $svc = new SuggestionService($llm/* ... */);
        $resp = $svc->suggest(null, null, 'Привет', 3);
        $this->assertSame([], $resp->getSuggestions());
    }
}
