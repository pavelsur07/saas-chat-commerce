<?php

namespace App\AI;

final class SuggestionPromptBuilder
{
    public function __construct(
        private readonly int $maxSuggestions = 4,
    ) {
    }

    /**
     * Блок для SYSTEM: правила + (опц.) контекст бренда/знаний. Историю не включает.
     */
    public function buildSystemBlock(int $count, string $companyContext = ''): string
    {
        $rules = <<<TXT
Задача: предложи {$count} варианта коротких ответов оператору на ПОСЛЕДНЮЮ реплику клиента.
Правила:
- На русском языке.
- 1–2 коротких предложения максимум.
- Допускается 0–1 эмодзи.
- Если данных мало — один из вариантов должен мягко уточнять детали.
- НЕЛЬЗЯ упоминать, что ты ИИ.
- Верни СТРОГО валидный JSON без комментариев, префиксов и постфиксов:
{"suggestions":["...","...","...","..."]}
TXT;

        $brand = '';
        if ('' !== trim($companyContext)) {
            $brand = "\n\nКонтекст бренда и знания (используй как основу ответа):\n".$companyContext;
        }

        return $rules.$brand;
    }

    /**
     * СТАРЫЙ метод (оставлен для совместимости). Пример JSON исправлен на 4 пункта.
     * Сейчас НЕ используется в SuggestionService (историю передаём отдельными сообщениями).
     *
     * @param array<int,array{role:string,text:string}> $context
     */
    public function build(array $context, string $companyContext = ''): string
    {
        $count = $this->maxSuggestions;

        $historyTxt = '';
        foreach ($context as $row) {
            $role = $row['role'] ?? 'user';
            $text = $row['text'] ?? '';
            $historyTxt .= sprintf("[%s] %s\n", $role, $text);
        }
        $historyTxt = trim($historyTxt);

        $brandBlock = '';
        if ('' !== trim($companyContext)) {
            $brandBlock = <<<CTX
Контекст бренда и знания (используй как основу ответа):
{$companyContext}

---
CTX;
        }

        return <<<PROMPT
Задача: предложи {$count} варианта коротких ответов оператору на ПОСЛЕДНЮЮ реплику клиента из истории.
Правила:
- На русском языке.
- 1–2 коротких предложения максимум.
- Допускается 0–1 эмодзи.
- Если данных мало — один из вариантов должен мягко уточнять детали.
- НЕЛЬЗЯ упоминать, что ты ИИ.
- Выведи СТРОГО валидный JSON без комментариев, префиксов и постфиксов:
{"suggestions":["...","...","...","..."]}

{$brandBlock}
История диалога (сверху старые, снизу новые):
{$historyTxt}

Верни только JSON.
PROMPT;
    }
}
