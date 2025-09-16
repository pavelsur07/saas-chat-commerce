<?php

namespace App\Service\AI;

use App\Entity\AI\Enum\PromptStatus;
use App\Entity\Company\Company;

/**
 * Контракт payload для chat():
 * [
 *   'company'  => Company,               // ОБЯЗАТЕЛЬНО
 *   'feature'  => 'intent_classify',     // ОБЯЗАТЕЛЬНО
 *   'channel'  => 'telegram',            // ОБЯЗАТЕЛЬНО
 *   'model'    => 'gpt-4o-mini',         // ОБЯЗАТЕЛЬНО
 *   'messages' => [['role'=>'user','content'=>'...'], ...], // ОБЯЗАТЕЛЬНО
 *   // ... другие поля по необходимости
 * ]
 */
final class LlmClientWithLogging implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,
        private readonly AiPromptLogService $logger,
    ) {
    }

    public function chat(array $payload): array
    {
        $t0 = microtime(true);

        /** @var Company|null $company */
        $company = $payload['company'] ?? null;
        $feature = (string) ($payload['feature'] ?? 'unknown');
        $channel = (string) ($payload['channel'] ?? 'system');
        $model = (string) ($payload['model'] ?? 'unknown');

        if (!$company instanceof Company) {
            throw new \InvalidArgumentException('payload["company"] must be Company');
        }
        if (empty($payload['messages']) || !is_array($payload['messages'])) {
            throw new \InvalidArgumentException('payload["messages"] must be non-empty array');
        }

        $prompt = self::composePrompt($payload['messages']);

        try {
            $result = $this->inner->chat($payload);
            $elapsed = (int) round((microtime(true) - $t0) * 1000);

            // Пытаемся извлечь токены/стоимость, если провайдер вернул
            $promptTokens = (int) ($result['usage']['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($result['usage']['completion_tokens'] ?? 0);
            $costUsd = isset($result['cost_usd']) ? (string) $result['cost_usd'] : null;

            $this->logger->log(
                company: $company,
                feature: $feature,
                channel: $channel,
                model: $model,
                prompt: $prompt,
                status: PromptStatus::OK,
                latencyMs: $elapsed,
                response: (string) ($result['content'] ?? ''),
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                errorMessage: null,
                costUsd: $costUsd,
                meta: array_merge(
                    is_array($payload['metadata'] ?? null) ? (array) $payload['metadata'] : [],
                    ['llm' => $result]
                ),
            );

            return $result;
        } catch (\Throwable $e) {
            $elapsed = (int) round((microtime(true) - $t0) * 1000);

            $this->logger->log(
                company: $company,
                feature: $feature,
                channel: $channel,
                model: $model,
                prompt: $prompt,
                status: PromptStatus::ERROR,
                latencyMs: $elapsed,
                response: null,
                promptTokens: 0,
                completionTokens: 0,
                errorMessage: $e->getMessage(),
                costUsd: null,
                meta: array_merge(
                    is_array($payload['metadata'] ?? null) ? (array) $payload['metadata'] : [],
                    ['exception' => get_class($e)]
                )
            );

            throw $e;
        }
    }

    private static function composePrompt(array $messages): string
    {
        // простой читабельный prompt для логов
        return implode("\n", array_map(
            static fn (array $m) => sprintf('%s: %s', $m['role'] ?? 'user', (string) ($m['content'] ?? '')),
            $messages
        ));
    }
}
