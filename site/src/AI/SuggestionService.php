<?php

namespace App\AI;

use App\Entity\Company\Company;
use App\Repository\Messaging\ClientRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SuggestionService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ConversationContextProvider $contextProvider,
        private readonly SuggestionPromptBuilder $promptBuilder,
        private readonly ClientRepository $clients,
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
    public function suggest(Company $company, string $clientId): array
    {
        $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);
        $prompt = $this->promptBuilder->build($context);

        // Определяем канал клиента (telegram/whatsapp/instagram/...)
        $client = $this->clients->find($clientId);
        $channel = $client?->getChannel() ?? 'system'; // Нужно разработать механизм точного определения канала

        try {
            /*$result = $this->llm->chat([
                'company' => $company, // для логирования через декоратор
                'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
                'channel' => $channel,
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник оператора. Верни строго JSON вида {"suggestions":[...]}'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 400,
            ]);*/

            $result = $this->llm->chat([
                'company' => $company,
                'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
                'channel' => $channel->value,             // enum → строка
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник оператора. Верни строго JSON вида {"suggestions":[...]}'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => 400,
                'timeout' => $this->timeoutSeconds,           // ← таймаут в секундах
            ]);

            $content = (string) ($result['content'] ?? '');
            $decoded = json_decode($content, true);
            $list = is_array($decoded['suggestions'] ?? null) ? $decoded['suggestions'] : [];

            // Нормализация и ограничение 3–4 вариантов
            $list = array_values(
                array_filter(
                    array_map(static fn ($s) => trim((string) $s), $list),
                    static fn ($s) => '' !== $s
                )
            );

            return array_slice($list, 0, 4);
        } catch (\Throwable $e) {
            // В MVP — тихий фоллбек
            return [];
        }
    }
}
