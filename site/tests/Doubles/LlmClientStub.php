<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

/**
 * Минимальный стаб клиента LLM.
 * В services_test.yaml мы маппим ID App\Service\AI\LlmClient на этот класс.
 * В тесте можно менять $nextContent перед вызовом сервиса.
 */
final class LlmClientStub
{
    public string $nextContent = '{"suggestions":[]}';

    /**
     * Соответствует публичному API, который дергает SuggestionService.
     * Возвращаем ровно ту структуру, которую ожидает сервис (ключ 'content').
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
