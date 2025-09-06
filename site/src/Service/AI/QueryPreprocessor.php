<?php

namespace App\Service\AI;

/**
 * P1 — Базовая гигиена входящей строки для поиска.
 */
final class QueryPreprocessor
{
    public const DEFAULT_MAX_LEN = 160;

    public function preprocess(
        ?string $raw,
        int $maxLen = self::DEFAULT_MAX_LEN,
        int $minLen = 2,
        int $minTokens = 1,
    ): PreprocessResult {
        $q = (string) ($raw ?? '');

        // 1) trim + схлопывание пробелов/табов/переводов строк
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');

        // 2) "ё" -> "е"
        $q = str_replace(['Ё', 'ё'], ['Е', 'е'], $q);

        // 3) lower-case (mb)
        $q = mb_strtolower($q, 'UTF-8');

        // 4) мягкий лимит длины
        if (mb_strlen($q, 'UTF-8') > $maxLen) {
            $q = mb_substr($q, 0, $maxLen, 'UTF-8');
        }

        // 5) эвристики "пусто/коротко"
        $tooShort = mb_strlen($q, 'UTF-8') < $minLen;

        // 6) грубые токены
        $rawTokens = preg_split('/[^\p{L}\p{N}\-]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($rawTokens as $t) {
            $len = mb_strlen($t, 'UTF-8');
            if ($len >= 2) {
                $tokens[] = $t;
                continue;
            }
            if ($len >= 1 && preg_match('/^\p{N}+$/u', $t)) { // разрешаем одиночные цифры
                $tokens[] = $t;
            }
        }

        return new PreprocessResult(
            cleaned: $q,
            tokens: $tokens,
            isTooShort: $tooShort,
            hasEnoughTokens: count($tokens) >= $minTokens
        );
    }
}

final readonly class PreprocessResult
{
    public function __construct(
        public string $cleaned,
        /** @var string[] */
        public array $tokens,
        public bool $isTooShort,
        public bool $hasEnoughTokens,
    ) {
    }

    public function isEmptyOrTooShortForSearch(): bool
    {
        return $this->isTooShort || !$this->hasEnoughTokens;
    }
}
