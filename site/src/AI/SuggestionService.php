<?php

namespace App\AI;

use App\Entity\Company\Company;
use App\Repository\Messaging\ClientRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SuggestionService
{
    private const FALLBACK_SUGGESTIONS = [
        'Подскажите, какой размер/цвет вас интересует?',
        'Куда удобнее доставка — пункт выдачи или курьером?',
        'Ищете для спорта или на каждый день? Помогу подобрать 👍',
        'Если важно быстро — подскажу, что есть в наличии сейчас.',
    ];

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
        // опционально, если сервис контекста подключён
        private readonly ?AiSuggestionContextService $contextService = null,
    ) {
    }

    /**
     * @return array{suggestions:string[], knowledgeHitsCount:int}
     */
    public function suggest(Company $company, string $clientId): array
    {
        $fallback = self::FALLBACK_SUGGESTIONS;

        // 1) История диалога — безопасно: даже при ошибке продолжаем без истории
        try {
            $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);
        } catch (\Throwable $e) {
            $context = [];
        }

        // 2) Последняя пользовательская реплика (нужна для знаний)
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

        // 3) Контекст компании (ToV/знания) + телеметрия запроса
        $companyBlock = '';
        $normQuery = '';
        $knowledgeHitsCount = 0;
        if ($this->contextService) {
            try {
                $companyBlock = $this->contextService->buildBlock($company, $lastUserText, 5);
                $normQuery = $this->contextService->normalizeQuery($lastUserText);
                $knowledgeHitsCount = $this->contextService->getLastHitsCount();
            } catch (\Throwable $e) {
                $companyBlock = '';
                $normQuery = '';
                $knowledgeHitsCount = 0;
            }
        }

        // 4) SYSTEM правила + бренд-контекст
        $system = "Ты помощник оператора. Верни строго валидный JSON {\"suggestions\":[...]}. \n\n"
            .$this->promptBuilder->buildSystemBlock(4, $companyBlock);

        // 5) История в ChatML
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $system];

        $prevRole = null;
        $prevText = null;
        foreach ($context as $row) {
            $role = (($row['role'] ?? 'user') === 'agent') ? 'assistant' : 'user';
            $text = trim((string) ($row['text'] ?? ''));
            if ('' === $text) {
                continue;
            }
            if ($role === $prevRole && $text === $prevText) {
                continue; // пропускаем точные дубли подряд
            }
            $messages[] = ['role' => $role, 'content' => $text];
            $prevRole = $role;
            $prevText = $text;
        }

        // 6) Вызов LLM — если упадёт, вернём понятный fallback, чтобы UI не пустел
        try {
            $result = $this->llm->chat([
                'company' => $company,
                'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
                'channel' => 'api',
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => 400,
                'timeout' => $this->timeoutSeconds,
                'metadata' => [
                    'search' => [
                        'raw_query' => (string) $lastUserText,
                        'norm_query' => (string) $normQuery,
                        'hits_count' => $knowledgeHitsCount,
                        'client_id' => $clientId,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return [
                'suggestions' => $fallback,
                'knowledgeHitsCount' => $knowledgeHitsCount,
            ];
        }

        // 7) Парсинг — устойчивый к "грязному" JSON
        $content = (string) ($result['content'] ?? '');
        $itemsRaw = $this->parseSuggestionsRobust($content);

        if (!is_array($itemsRaw)) {
            $itemsRaw = [];
        }

        // Нормализация: строки, трим, убираем пустые
        $items = array_values(
            array_filter(
                array_map(
                    static function ($v) {
                        return trim((string) $v);
                    },
                    $itemsRaw
                ),
                static function ($s) {
                    return '' !== $s;
                }
            )
        );

        // Ограничение до 4 штук
        if (count($items) > 4) {
            $items = array_slice($items, 0, 4);
        }

        // Если пусто — вернём fallback, чтобы UI не пустел
        if (empty($items)) {
            $items = $fallback;
        }

        return [
            'suggestions' => $items,
            'knowledgeHitsCount' => $knowledgeHitsCount,
        ];
    }

    /**
     * Устойчивый парсер JSON от модели (```json …```; лишний текст, запятые, одинарные кавычки).
     * Возвращает массив строк.
     */
    private function parseSuggestionsRobust(string $raw): array
    {
        $s = trim($raw);
        if ('' === $s) {
            return [];
        }

        // Снимаем code fences ```...```
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```[a-zA-Z]*\s*/u', '', $s);
            $s = preg_replace('/```$/u', '', $s);
            $s = trim($s ?? '');
        }

        // Прямая попытка
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            if (isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
                return $decoded['suggestions'];
            }
            if ($this->isFlatStringArray($decoded)) {
                return $decoded;
            }
        }

        // Вытаскиваем JSON-объект с "suggestions" из текста
        if (preg_match('/\{.*"suggestions"\s*:\s*\[.*?\].*\}/su', $s, $m)) {
            $candidate = $m[0];

            // Чиним запятые перед ] или }
            $candidate = preg_replace('/,(\s*[\]\}])/u', '$1', $candidate);
            $decoded2 = json_decode($candidate, true);

            if (!is_array($decoded2)) {
                // Пробуем заменить одинарные кавычки
                $candidate2 = str_replace("'", '"', $candidate);
                $decoded2 = json_decode($candidate2, true);
            }

            if (is_array($decoded2) && isset($decoded2['suggestions']) && is_array($decoded2['suggestions'])) {
                return $decoded2['suggestions'];
            }
        }

        // Последняя попытка — маркированный список в тексте
        $lines = preg_split('/\r\n|\r|\n/', $s);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            if (preg_match('/^(\d+[\)\.-]|[-•\*])\s*(.+)$/u', $line, $mm)) {
                $out[] = trim($mm[2]);
            }
        }

        return $out;
    }

    private function isFlatStringArray(array $a): bool
    {
        foreach ($a as $v) {
            if (!is_string($v)) {
                return false;
            }
        }

        return true;
    }
}
