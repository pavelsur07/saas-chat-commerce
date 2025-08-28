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
        #[Autowire('%ai.suggestions.model%')] private readonly string $model = 'gpt-4o-mini',
        #[Autowire('%ai.suggestions.temperature%')] private readonly float $temperature = 0.7,
        #[Autowire('%ai.suggestions.max_history%')] private readonly int $maxHistory = 12,
        #[Autowire('%ai.suggestions.max_chars%')] private readonly int $maxChars = 4000,
        #[Autowire('%ai.suggestions.timeout_seconds%')] private readonly int $timeoutSeconds = 10,
        private readonly ?AiSuggestionContextService $contextService = null,
    ) {
    }

    /**
     * @return string[] max 4 suggestions
     */
    public function suggest(Company $company, string $clientId): array
    {
        try {
            // 1) История диалога (массивами), уже ограниченная maxHistory/maxChars
            $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);

            // 2) Последняя пользовательская реплика — нужна для поиска знаний
            $lastUserText = '';
            for ($i = count($context) - 1; $i >= 0; --$i) {
                if (($context[$i]['role'] ?? '') === 'user') {
                    $lastUserText = (string) ($context[$i]['text'] ?? '');
                    break;
                }
            }
            if ('' === $lastUserText && !empty($context)) {
                $last = $context[count($context) - 1];
                $lastUserText = (string) ($last['text'] ?? '');
            }

            // 3) Контекст компании: ToV/УТП + релевантные знания (<=5), с обрезкой по AI_MAX_CONTEXT_CHARS
            $companyBlock = '';
            if ($this->contextService && '' !== $lastUserText) {
                $companyBlock = $this->contextService->buildBlock($company, $lastUserText, 5);
            }

            // 4) SYSTEM: строгие правила + контекст бренда/знаний (без истории!)
            $system = "Ты помощник оператора. Верни строго валидный JSON {\"suggestions\":[...]}.\n\n"
                .$this->promptBuilder->buildSystemBlock(4, $companyBlock);

            // 5) Историю отдаём отдельными ChatML-сообщениями (user/assistant), без «Верни JSON» в конце
            $messages = [
                ['role' => 'system', 'content' => $system],
            ];

            // Уберём подряд идущие дубли строк (иногда клиент шлёт один и тот же текст)
            $prevRole = null;
            $prevText = null;
            foreach ($context as $row) {
                $role = ($row['role'] ?? 'user') === 'agent' ? 'assistant' : 'user';
                $text = trim((string) ($row['text'] ?? ''));
                if ('' === $text) {
                    continue;
                }
                if ($role === $prevRole && $text === $prevText) {
                    continue; // пропустим точный дубль подряд
                }
                $messages[] = ['role' => $role, 'content' => $text];
                $prevRole = $role;
                $prevText = $text;
            }

            // 6) Вызов LLM (как у вас принято: ожидаем JSON в контенте ответа)
            $result = $this->llm->chat([
                'company' => $company,
                'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
                'channel' => 'api',                    // ← ДОБАВИТЬ
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => 400,
                'timeout' => $this->timeoutSeconds,
            ]);

            // 7) Парсинг ответа — без изменений по вашему проекту
            $content = (string) ($result['content'] ?? '');
            $decoded = json_decode($content, true);
            $list = is_array($decoded['suggestions'] ?? null) ? $decoded['suggestions'] : [];

            $list = array_values(
                array_filter(
                    array_map(static fn ($s) => trim((string) $s), $list),
                    static fn ($s) => '' !== $s
                )
            );

            return array_slice($list, 0, 4);
        } catch (\Throwable $e) {
            // В MVP оставим мягкий фоллбек
            return [];
        }
    }
}
