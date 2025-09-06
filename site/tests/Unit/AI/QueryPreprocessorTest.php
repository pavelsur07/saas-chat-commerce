<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI;

use App\Service\AI\QueryPreprocessor;
use PHPUnit\Framework\TestCase;

final class QueryPreprocessorTest extends TestCase
{
    public function testTrimCollapseLowerYoLimit(): void
    {
        $prep = new QueryPreprocessor();

        $res = $prep->preprocess("  Срок   возврата\t\n и Ёмкость 😀  ");
        self::assertSame('срок возврата и емкость 😀', $res->cleaned);
        self::assertFalse($res->isTooShort);
        self::assertTrue($res->hasEnoughTokens);
        self::assertContains('срок', $res->tokens);
        self::assertContains('возврата', $res->tokens);
        self::assertContains('емкость', $res->tokens);
    }

    public function testMaxLen(): void
    {
        $prep = new QueryPreprocessor();
        $long = str_repeat('абв', 200);
        $res = $prep->preprocess($long, maxLen: 160);
        self::assertLessThanOrEqual(160, mb_strlen($res->cleaned, 'UTF-8'));
    }

    public function testTooShort(): void
    {
        $prep = new QueryPreprocessor();
        $res = $prep->preprocess(' a ', maxLen: 160, minLen: 2);
        self::assertTrue($res->isEmptyOrTooShortForSearch());
    }

    public function testSingleDigitAllowed(): void
    {
        $prep = new QueryPreprocessor();
        $res = $prep->preprocess('7', maxLen: 160, minLen: 1);
        self::assertFalse($res->isEmptyOrTooShortForSearch());
        self::assertSame(['7'], $res->tokens);
    }
}
