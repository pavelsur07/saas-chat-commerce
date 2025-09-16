<?php

declare(strict_types=1);

namespace App\AI;

use App\Entity\AI\AiCompanyProfile;
use App\Entity\Company\Company;
use App\Repository\AI\AiCompanyProfileRepository;
use App\Repository\AI\CompanyKnowledgeRepository;

final class AiSuggestionContextService
{
    // внутри класса, метод normalizeQuery(string $q): array{query:string,hintType:?string,original:string}
    private array $stopSingles = [
        'ок', 'ага', 'да', 'нет', 'привет', 'здравствуйте', 'добрый день', 'алло', 'спс', 'спасибо',
    ];

    public function __construct(
        private readonly AiCompanyProfileRepository $profileRepo,
        private readonly CompanyKnowledgeRepository $knowledgeRepo,
        private int $maxContextChars = 1200,
    ) {
        // защита от случайного 0 из конфига
        $this->maxContextChars = max(0, $this->maxContextChars);
    }

    /**
     * Склеивает профиль (ToV/Brand) и знания.
     * ВАЖНО: общий размер блока ≤ $this->maxContextChars.
     */
    public function buildBlock(Company $company, string $incomingText, int $limit = 5): string
    {
        $parts = [];

        // 1) Профиль компании (не режем отдельно, но учитываем в общем бюджете)
        $profile = $this->fetchCompanyProfile($company);
        $profileBlock = $this->buildProfileBlock($profile);
        if ('' !== $profileBlock) {
            $parts[] = $profileBlock;
        }

        // 2) Знания с мягким ограничением с учётом уже занятых символов
        $normalizedQuery = $this->normalizeQuery($incomingText);
        $query = '';
        $hintType = null;

        if (is_array($normalizedQuery)) {
            $query = trim((string) ($normalizedQuery['query'] ?? ''));
            $hintValue = $normalizedQuery['hintType'] ?? null;
            $hintType = is_string($hintValue) && '' !== $hintValue ? $hintValue : null;
        } else {
            $query = trim((string) $normalizedQuery);
        }

        if ('' !== $query) {
            $budget = $this->maxContextChars;

            // если лимит задан, уменьшаем бюджет на профиль + разделитель между блоками
            if ($budget > 0 && '' !== $profileBlock) {
                $budget -= mb_strlen($profileBlock);
                // учтём два перевода строки между блоками
                $budget -= 2;
                if ($budget < 0) {
                    $budget = 0;
                }
            }

            $knowledgeBlock = $this->buildKnowledgeBlock($company, $query, $limit, $budget, $hintType);
            if ('' !== $knowledgeBlock) {
                $parts[] = $knowledgeBlock;
            }
        }

        $result = $parts ? implode("\n\n", $parts) : '';

        // финальная страховка: если по каким-то причинам общий блок всё ещё > лимита — мягко режем хвост целиком
        if ($this->maxContextChars > 0 && mb_strlen($result) > $this->maxContextChars) {
            $result = $this->softTruncate($result, $this->maxContextChars);
        }

        return $result;
    }

    /* =========================
     *      ВСПОМОГАТЕЛЬНЫЕ
     * ========================= */

    private function fetchCompanyProfile(Company $company): ?AiCompanyProfile
    {
        // корректно для вашего маппинга: профиль по связи company
        $profile = $this->profileRepo->findOneBy(['company' => $company]);

        return $profile instanceof AiCompanyProfile ? $profile : null;
    }

    private function buildProfileBlock(?AiCompanyProfile $profile): string
    {
        if (!$profile) {
            return '';
        }

        $chunks = [];

        $tov = trim((string) ($profile->getToneOfVoice() ?? ''));
        if ('' !== $tov) {
            $chunks[] = "Tone of Voice:\n{$tov}";
        }

        $brand = trim((string) ($profile->getBrandNotes() ?? ''));
        if ('' !== $brand) {
            $chunks[] = "Brand Notes:\n{$brand}";
        }

        return $chunks ? implode("\n\n", $chunks) : '';
    }

    private function buildKnowledgeBlock(
        Company $company,
        string $query,
        int $topN,
        int $maxCharsForKnowledge,
        ?string $hintType = null,
    ): string {
        $items = $this->knowledgeRepo->findTopByQuery($company, $query, $topN, $hintType);
        if (empty($items)) {
            return '';
        }

        $lines = ['Knowledge Snippets:'];

        foreach ($items as $row) {
            $title = '';
            $content = '';

            if (is_array($row)) {
                $title = (string) ($row['title'] ?? '');
                $content = (string) ($row['content'] ?? '');
            } elseif (is_object($row)) {
                if (method_exists($row, 'getTitle')) {
                    $title = (string) $row->getTitle();
                } elseif (property_exists($row, 'title')) {
                    /** @phpstan-ignore-next-line */
                    $title = (string) $row->title;
                }
                if (method_exists($row, 'getContent')) {
                    $content = (string) $row->getContent();
                } elseif (property_exists($row, 'content')) {
                    /** @phpstan-ignore-next-line */
                    $content = (string) $row->content;
                }
            }

            // нормализация
            $title = trim($title);
            $content = trim($content);
            if ('' !== $content) {
                // убираем переводы строк и лишние пробелы
                $content = preg_replace('/\s+/u', ' ', $content) ?? $content;
                $content = trim($content);

                // ВАЖНО: в блок знаний кладём именно КОНТЕНТ
                $lines[] = '- '.$content;
                continue;
            }

            // fallback — если нет content, но есть заголовок (хотя это нежелательно)
            if ('' !== $title) {
                $lines[] = '- '.$title;
            }
        }

        $block = implode("\n", $lines);

        return $this->applySoftLimitToKnowledge($block, $maxCharsForKnowledge);
    }

    public function normalizeQuery(string $q): array
    {
        $orig = $q;
        $q = trim($q);

        // 1) стоп-фразы, если всё сообщение - пустышка
        if (in_array(mb_strtolower($q), $this->stopSingles, true)) {
            return ['query' => '', 'hintType' => null, 'original' => $orig];
        }

        // 2) вырезаем мусорные вводные
        $q = preg_replace('~^(скажите\s+пожалуйста|подскажите|можно ли|а у вас)\s+~ui', '', $q);
        $q = preg_replace('~\s+(пожалуйста|спс|спасибо)$~ui', '', $q);

        // 3) нормализация
        $q = str_replace(['ё', 'йо'], ['е', 'е'], $q);
        $q = preg_replace('~\s+~u', ' ', $q);
        $q = trim($q);

        // 4) (опц.) простой hintType (для мягкого буста)
        $lower = mb_strtolower($q);
        $hintType = null;
        if (preg_match('~доставк|курьер|самовывоз|срок(и)?~u', $lower)) {
            $hintType = 'delivery';
        } elseif (preg_match('~оплат|карт|наличн|чек|возврат~u', $lower)) {
            $hintType = 'policy';
        } elseif (preg_match('~товар|продукт|наличи|остатк~u', $lower)) {
            $hintType = 'product';
        }

        return ['query' => $q, 'hintType' => $hintType, 'original' => $orig];
    }

    /** deprecated */
    /*private function normalizeQuery(string $text): string
    {
        $q = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ('' === $q || mb_strlen($q) < 2) {
            return '';
        }
        $stop = ['ок', 'мск', 'спб', 'ага', 'да', 'нет'];

        return in_array(mb_strtolower($q), $stop, true) ? '' : $q;
    }*/

    /**
     * Публичная обёртка — тестируем мягкую обрезку отдельно.
     * Сохраняет заголовок "Knowledge Snippets:" и режет только тело.
     */
    public function applySoftLimitToKnowledge(string $block, int $maxChars): string
    {
        if ($maxChars <= 0) {
            return $block;
        }
        if (mb_strlen($block) <= $maxChars) {
            return $block;
        }

        $header = 'Knowledge Snippets:';
        $blockTrim = trim($block);

        if (0 === mb_strpos($blockTrim, $header)) {
            $pos = mb_strpos($block, "\n");
            if (false === $pos) {
                return $blockTrim;
            }

            $head = mb_substr($block, 0, $pos + 1);   // "Knowledge Snippets:\n"
            $body = mb_substr($block, $pos + 1);

            $maxForBody = max(0, $maxChars - mb_strlen($head));
            $body = $this->softTruncate($body, $maxForBody);

            return rtrim($head.$body);
        }

        return $this->softTruncate($block, $maxChars);
    }

    private function softTruncate(string $text, int $maxChars): string
    {
        if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
            return $text;
        }
        $cut = max(0, $maxChars - 1);
        $out = rtrim(mb_substr($text, 0, $cut));

        return $out.'…';
    }

    /** Оставлено для совместимости с прежними тестами/кодом */
    private function pushBlock(array &$parts, string $block, int &$remain, bool $allowPartial = false): void
    {
        if ('' === $block || $remain <= 0) {
            return;
        }

        $sep = empty($parts) ? '' : "\n\n";
        $need = mb_strlen($block) + mb_strlen($sep);

        if ($need <= $remain) {
            $parts[] = $sep.$block;
            $remain -= $need;

            return;
        }

        if ($allowPartial) {
            $cut = $remain - mb_strlen($sep);
            if ($cut > 20) {
                $parts[] = $sep.mb_substr($block, 0, $cut - 1).'…';
                $remain = 0;
            }
        }
    }
}
