<?php

namespace App\Tests\Unit\AI;

use App\AI\SuggestionPromptBuilder;
use PHPUnit\Framework\TestCase;

final class SuggestionPromptBuilderCompanyContextTest extends TestCase
{
    public function testBuildInjectsCompanyContextAndHistory(): void
    {
        $builder = new SuggestionPromptBuilder(4);

        $context = [
            ['role' => 'user', 'text' => 'Привет! Сколько доставка?'],
            ['role' => 'assistant', 'text' => 'Здравствуйте! Уточните город, пожалуйста.'],
            ['role' => 'user', 'text' => 'Москва.'],
        ];

        $companyContext = "Tone of Voice:\nКоротко и по делу\n\n---\n\nKnowledge Snippets:\n- **Доставка**\n2-5 дней по РФ";

        $prompt = $builder->build($context, $companyContext);

        self::assertStringContainsString('Контекст бренда и знания', $prompt);
        self::assertStringContainsString('Knowledge Snippets', $prompt);
        self::assertStringContainsString('История диалога', $prompt);
        self::assertStringContainsString('Верни только JSON', $prompt);
        self::assertStringContainsString('Москва', $prompt);
    }
}
