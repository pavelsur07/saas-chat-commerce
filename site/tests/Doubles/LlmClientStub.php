<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Service\AI\LlmClient;

/**
 * Стаб LLM для тестов. Важно: реализует интерфейс App\Service\AI\LlmClient.
 */
final class LlmClientStub implements LlmClient
{
    /** Следующий «контент» ответа чат‑модели, который вернём в тесте */
    public string $nextContent = '{"suggestions":[]}';

    /**
     * Если у интерфейса есть иные методы — добавь их здесь с пустыми реализациями,
     * но сигнатуры должны совпадать с интерфейсом!
     *
     * @param array<string,mixed> $payload
     *
     * @return array{content:string}
     */
    public function chat(array $payload): array
    {
        return ['content' => $this->nextContent];
    }
}
