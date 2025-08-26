<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Service\AI\Provider\MockLlmClient;

final class LlmClientSelector implements LlmClient
{
    public function __construct(
        private readonly string $provider,   // 'mock' | 'openai' | ...
        private readonly MockLlmClient $mock,
        // private readonly OpenAiClient $openai, // добавите позже
    ) {
    }

    public function chat(array $params): array
    {
        return match ($this->provider) {
            'mock' => $this->mock->chat($params),
            default => $this->mock->chat($params), // временно: всё в мок
        };
    }
}
