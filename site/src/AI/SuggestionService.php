<?php

namespace App\AI;

use App\Repository\AI\AiPromptLogRepository;
use App\Service\AI\LlmClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SuggestionService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly AiPromptLogRepository $logRepo,
        private readonly ConversationContextProvider $contextProvider,
        private readonly SuggestionPromptBuilder $promptBuilder,
        #[Autowire('%ai.suggestions.model%')] private readonly string $model,
        #[Autowire('%ai.suggestions.temperature%')] private readonly float $temperature,
        #[Autowire('%ai.suggestions.max_history%')] private readonly int $maxHistory,
        #[Autowire('%ai.suggestions.max_chars%')] private readonly int $maxChars,
        #[Autowire('%ai.suggestions.timeout_seconds%')] private readonly int $timeoutSeconds,
    ) {
    }

    /**
     * @return string[] max 4 suggestions
     */
    public function suggest(string $companyId, string $clientId): array
    {
        $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);
        $prompt = $this->promptBuilder->build($context);

        $started = \microtime(true);
        $meta = [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'timeout_seconds' => $this->timeoutSeconds,
        ];

        try {
            // Предполагается, что completeJson гарантирует JSON-строку либо бросает исключение/возвращает строку
            $raw = $this->llm->completeJson(
                prompt: $prompt,
                model: $this->model,
                temperature: $this->temperature,
                timeoutSeconds: $this->timeoutSeconds
            );

            $latency = (int) ((\microtime(true) - $started) * 1000);
            $meta['latency_ms'] = $latency;

            $decoded = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $suggestions = $decoded['suggestions'] ?? [];

            if (!\is_array($suggestions)) {
                $suggestions = [];
            }

            // Ограничим 3–4 вариантов
            $suggestions = array_values(array_filter(array_map('trim', $suggestions), fn ($s) => '' !== $s));
            $suggestions = \array_slice($suggestions, 0, 4);

            $this->logRepo->save(
                feature: 'suggest',
                companyId: $companyId,
                clientId: $clientId,
                prompt: $prompt,
                response: \json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE),
                meta: $meta
            );

            return $suggestions;
        } catch (\Throwable $e) {
            $latency = (int) ((\microtime(true) - $started) * 1000);
            $meta['latency_ms'] = $latency;
            $meta['error'] = [
                'type' => \get_class($e),
                'message' => $e->getMessage(),
            ];

            $this->logRepo->save(
                feature: 'suggest',
                companyId: $companyId,
                clientId: $clientId,
                prompt: $prompt,
                response: null,
                meta: $meta
            );

            // В MVP не валим UX — возвращаем пустой массив
            return [];
        }
    }
}
