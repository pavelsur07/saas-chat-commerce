<?php

namespace App\Tests\Unit\AI;

use App\Service\AI\Suggestions\SuggestionPromptBuilder;
use PHPUnit\Framework\TestCase;

class SuggestionPromptBuilderTest extends TestCase
{
    public function testBuildsPromptWithToneAndKnowledge(): void
    {
        $builder = new SuggestionPromptBuilder(
            maxContextChars: 1200,
            suggestionsCount: 4
        );

        $tone = "Дружелюбный, краткий, без воды";
        $brand = "Мы продаём детские игрушки, доставка по РФ";
        $knowledgeItems = [
            ['type'=>'faq', 'title'=>'Оплата', 'content'=>'Оплата картой или СБП'],
            ['type'=>'delivery', 'title'=>'Сроки доставки', 'content'=>'2-5 дней по РФ'],
        ];
        $userText = "Сколько идёт доставка и как оплатить?";

        $prompt = $builder->build($tone, $brand, $knowledgeItems, $userText, []);
        $this->assertStringContainsString('Дружелюбный', $prompt['system']);
        $this->assertStringContainsString('детские игрушки', $prompt['system']);
        $this->assertStringContainsString('Сроки доставки', $prompt['context']);
        $this->assertStringContainsString('Строго верни JSON', $prompt['format']); // или как у тебя формулировка
    }
}
