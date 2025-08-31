<?php

declare(strict_types=1);

namespace App\AI;

final class SuggestionPromptBuilder
{
    // ⬇️ конструктор — как в вашей предыдущей версии (сохраняем совместимость)
    public function __construct(
        private readonly int $maxSuggestions = 4,
    ) {
    }

    /** Совместимость с тестами/старым кодом */
    public function build(int $count, string $companyContext = ''): string
    {
        return $this->buildSystemBlock($count, $companyContext);
    }

    /**
     * Блок для SYSTEM: правила + (опц.) companyContext.
     * Сигнатура и порядок параметров — прежние.
     */
    public function buildSystemBlock(int $count, string $companyContext = ''): string
    {
        $companyContext = trim($companyContext);
        $ctx = '' !== $companyContext
            ? "\n---\nCOMPANY CONTEXT (use to personalize tone & content):\n{$companyContext}\n---\n"
            : '';

        return <<<PROMPT

Задача: предложи {$count} варианта коротких ответов оператору на ПОСЛЕДНЮЮ реплику клиента.
Правила:
- На русском языке.
- 1–2 коротких предложения максимум.
- Допускается 0–1 эмодзи.
- Если данных мало — один из вариантов должен мягко уточнять детали.
- НЕЛЬЗЯ упоминать, что ты ИИ.
- Верни СТРОГО валидный JSON без комментариев, префиксов и постфиксов:
{"suggestions":["...","...","...","..."]}

{$ctx}
PROMPT;
    }
}
