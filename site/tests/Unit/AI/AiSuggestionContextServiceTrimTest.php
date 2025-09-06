<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI;

use App\AI\AiSuggestionContextService;
use App\Repository\AI\AiCompanyProfileRepository;
use App\Repository\AI\CompanyKnowledgeRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiSuggestionContextService::class)]
final class AiSuggestionContextServiceTrimTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testApplySoftLimitToKnowledgePreservesHeaderAndAddsEllipsis(): void
    {
        // репозитории не нужны для этого теста — подставляем моки
        $svc = new AiSuggestionContextService(
            $this->createMock(AiCompanyProfileRepository::class),
            $this->createMock(CompanyKnowledgeRepository::class),
            5000 // не используется в данном тесте, передаем что-то валидное
        );

        $header = 'Knowledge Snippets:';
        $body = '- Строка 1: '.str_repeat('A', 2000)."\n"
            .'- Строка 2: '.str_repeat('B', 2000)."\n"
            .'- Строка 3: '.str_repeat('C', 2000);
        $block = $header."\n".$body;

        // ограничим, чтобы точно сработала обрезка
        $limited = $svc->applySoftLimitToKnowledge($block, 1200);

        // 1) хедер сохранён
        self::assertStringStartsWith($header, $limited);

        // 2) длина не превышает лимит + небольшой зазор на переносы
        self::assertTrue(mb_strlen($limited) <= 1200 + 2, 'Длина мягко ограничена');

        // 3) есть многоточие
        self::assertStringEndsWith('…', $limited);
    }

    /**
     * @throws Exception
     */
    public function testApplySoftLimitToKnowledgeNoLimitOrShort(): void
    {
        $svc = new AiSuggestionContextService(
            $this->createMock(AiCompanyProfileRepository::class),
            $this->createMock(CompanyKnowledgeRepository::class),
            1200
        );

        $block = "Knowledge Snippets:\n- A\n- B\n- C";

        // лимит не применяем
        self::assertSame($block, $svc->applySoftLimitToKnowledge($block, 0));
        // короткий текст — без изменений
        self::assertSame($block, $svc->applySoftLimitToKnowledge($block, 9999));
    }
}
