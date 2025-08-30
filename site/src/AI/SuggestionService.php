<?php

namespace App\AI;

use App\Entity\Company\Company;
use App\Repository\Messaging\ClientRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use Psr\Log\LoggerInterface;
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
        // опционально, если сервис контекста подключён
        private readonly ?AiSuggestionContextService $contextService = null,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return string[] max 4 suggestions
     */
    public function suggest(Company $company, string $clientId): array
    {
        // 1) Сигнальный лог — чтобы на проде наконец появились следы
        // Логер у вас уже есть в сервисе; если нет — добавьте LoggerInterface в __construct
        $this->logger->info('AI_SUGGEST_START', [
            'company_id' => (string) $company->getId(),
            'client_id'  => $clientId,
        ]);

        $started = microtime(true);

        try {
            // 2) Сбор промпта как раньше (ВАШ существующий код — не трогаю)
            // Предполагаю, что у вас тут формируется $system/$user/$messages или $prompt
            // Оставьте вашу реализацию полностью.
            $prompt = $this->buildPrompt($company, $clientId); // <- это ваш существующий метод
            // Если у вас другой интерфейс — оставьте как было, важно только дальше парсинг

            // 3) Вызов LLM (ВАШ существующий клиент) — не меняю, только сохраняю «сырой» ответ
            // Например:
            $raw = $this->llm->complete($prompt, [
                'timeout' => 10, // мягкий таймаут (если ваш клиент поддерживает)
            ]);

            // 4) Логируем сырой ответ (обрежем, чтобы не раздувать лог)
            $preview = is_string($raw) ? mb_substr($raw, 0, 1200) : json_encode($raw, JSON_UNESCAPED_UNICODE);
            $this->logger->info('AI_SUGGEST_RAW', [
                'took_ms' => (int) ((microtime(true) - $started) * 1000),
                'raw'     => $preview,
            ]);

            // 5) УСТОЙЧИВЫЙ ПАРСИНГ
            $items = $this->parseSuggestionsRobust($raw);

            // 6) Нормализация и ограничение
            $items = array_values(array_filter(array_map(static function ($v) {
                $s = trim((string) $v);
                // вырезаем обратные кавычки/маркдаун по краям
                $s = trim($s, "` \t\n\r\0\x0B");
                return $s;
            }, $items)));

            if (count($items) > 4) {
                $items = array_slice($items, 0, 4);
            }

            // 7) Если пусто — вернём ясный fallback, чтобы оператор не сидел с пустым экраном
            if (empty($items)) {
                $this->logger->warning('AI_SUGGEST_EMPTY_AFTER_PARSE');
                $items = [
                    'Подскажите, какой размер/цвет вас интересует?',
                    'Куда удобнее доставка — пункт выдачи или курьером?',
                    'Ищете для спорта или на каждый день? Помогу подобрать 👍',
                    'Если важно быстро — подскажу, что есть в наличии сейчас.',
                ];
            }

            $this->logger->info('AI_SUGGEST_OK', [
                'count'   => count($items),
                'took_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);

            return $items;
        } catch (\Throwable $e) {
            // 8) Любая ошибка — не роняем UI, даём fallback и логируем
            $this->logger->error('AI_SUGGEST_FAIL', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'took_ms' => (int)((microtime(true) - $started) * 1000),
            ]);

            return [
                'Могу помочь! Уточните, пожалуйста, модель/цвет/размер?',
                'Подскажите, куда удобнее доставка: пункт выдачи или курьером?',
                'Если нужно быстро — подскажу ближайшую готовую к отправке позицию 👍',
                'Опишите, для каких тренировок/условий ищете — подберу варианты.',
            ];
        }
    }

    /**
    * Устойчивый парсер JSON от LLM.
    * Принимает строки вида:
    *  - ```json { "suggestions": ["..."] } ```
    *  - текст до/после JSON
    *  - одинарные кавычки
    *  - запятые в конце
    *  - код в Markdown
    * Возвращает массив строк или [].
    */
    private function parseSuggestionsRobust(mixed $raw): array
    {
        // 0) Если уже массив с ключом suggestions — вернём сразу
        if (is_array($raw)) {
            if (isset($raw['suggestions']) && is_array($raw['suggestions'])) {
                return $raw['suggestions'];
            }
            // Если массив строк — тоже ок
            if ($this->isFlatStringArray($raw)) {
                return $raw;
            }
            // Иначе попробуем ниже приведение к строке
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($raw)) {
            return [];
        }

        $s = trim($raw);

        // 1) Убираем код-фенсы ```...```
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```[a-zA-Z]*\s*/u', '', $s);
            $s = preg_replace('/```$/u', '', $s);
            $s = trim($s);
        }

        // 2) Если это «чистый» JSON — пробуем декодировать
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            if (isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
                return $decoded['suggestions'];
            }
            if ($this->isFlatStringArray($decoded)) {
                return $decoded;
            }
        }

        // 3) Попробуем вытащить JSON-объект с ключом "suggestions" из смешанного текста
        if (preg_match('/\{.*"suggestions"\s*:\s*\[.*?\].*\}/su', $s, $m)) {
            $candidate = $m[0];

            // Лечим одинарные кавычки → двойные (аккуратно)
            if (!str_contains($candidate, '"suggestions"')) {
                $candidate = str_replace("'", '"', $candidate);
            }

            // Удаляем запятые перед закрывающими скобками `,]` и `,}`
            $candidate = preg_replace('/,(\s*[\]\}])/u', '$1', $candidate);

            $decoded2 = json_decode($candidate, true);
            if (is_array($decoded2) && isset($decoded2['suggestions']) && is_array($decoded2['suggestions'])) {
                return $decoded2['suggestions'];
            }
        }

        // 4) Попытка достать строки вида " - ..." / "1) ..." / "• ..." (последний шанс)
        $lines = preg_split('/\r\n|\r|\n/', $s);
        $guessed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // берем явные маркеры списков
            if (preg_match('/^(\d+[\)\.\-]|[-•\*])\s*(.+)$/u', $line, $mm)) {
                $guessed[] = trim($mm[2]);
            }
        }
        if (!empty($guessed)) {
            return $guessed;
        }

        return [];
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
