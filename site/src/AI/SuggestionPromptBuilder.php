<?php

namespace App\AI;

final class SuggestionPromptBuilder
{
    public function __construct(
        private readonly int $maxSuggestions = 4,
    ) {
    }

    /**
     * @param array<int,array{role:string,text:string}> $context
     */
    public function build(array $context): string
    {
        $history = array_map(
            fn (array $m) => ('user' === $m['role'] ? 'Клиент: ' : 'Оператор: ').$m['text'],
            $context
        );
        $historyTxt = implode("\n", $history);

        $count = max(3, min($this->maxSuggestions, 4)); // 3–4

        return <<<PROMPT
Ты — помощник оператора интернет-магазина женской спортивной одежды.
Тон: «подружка делится» — дружелюбно, по делу, без канцелярита.

Задача: предложи {$count} варианта коротких ответов оператору на ПОСЛЕДНЮЮ реплику клиента из истории.
Правила:
- На русском языке.
- 1–2 коротких предложения максимум.
- Допускается 0–1 эмодзи.
- Если данных мало — один из вариантов должен мягко уточнять детали.
- НЕЛЬЗЯ упоминать, что ты ИИ.
- Выведи СТРОГО валидный JSON без комментариев, префиксов и постфиксов:
{"suggestions":["...","...","..."]}

История диалога (сверху старые, снизу новые):
{$historyTxt}

Верни только JSON.
PROMPT;
    }
}
