<?php

declare(strict_types=1);

namespace App\AI;

use App\Entity\AI\AiCompanyProfile;
use App\Entity\Company\Company;
use App\Repository\AI\AiCompanyProfileRepository;
use App\Repository\AI\CompanyKnowledgeRepository;

final class AiSuggestionContextService
{
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
        if ($profileBlock !== '') {
            $parts[] = $profileBlock;
        }

        // 2) Знания с мягким ограничением с учётом уже занятых символов
        $query = $this->normalizeQuery($incomingText);
        if ($query !== '') {
            $budget = $this->maxContextChars;

            // если лимит задан, уменьшаем бюджет на профиль + разделитель между блоками
            if ($budget > 0 && $profileBlock !== '') {
                $budget -= mb_strlen($profileBlock);
                // учтём два перевода строки между блоками
                $budget -= 2;
                if ($budget < 0) {
                    $budget = 0;
                }
            }

            $knowledgeBlock = $this->buildKnowledgeBlock($company, $query, $limit, $budget);
            if ($knowledgeBlock !== '') {
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
        if ($tov !== '') {
            $chunks[] = "Tone of Voice:\n{$tov}";
        }

        $brand = trim((string) ($profile->getBrandNotes() ?? ''));
        if ($brand !== '') {
            $chunks[] = "Brand Notes:\n{$brand}";
        }

        return $chunks ? implode("\n\n", $chunks) : '';
    }

    private function buildKnowledgeBlock(
        Company $company,
        string $query,
        int $topN,
        int $maxCharsForKnowledge
    ): string {
        $items = $this->knowledgeRepo->findTopByQuery($company, $query, $topN);
        if (empty($items)) {
            return '';
        }

        $lines = ['Knowledge Snippets:'];
        foreach ($items as $row) {
            $title = '';
            $content = '';

            if (is_array($row)) {
                $title   = (string) ($row['title']   ?? '');
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

            $title = trim($title);
            $content = trim($content);

            if ($title !== '' && $content !== '') {
                $lines[] = "- {$title}: {$content}";
            } elseif ($content !== '') {
                $lines[] = "- {$content}";
            } elseif ($title !== '') {
                $lines[] = "- {$title}";
            }
        }

        $block = implode("\n", $lines);

        // публичная обёртка — для изолированного юнит-теста
        return $this->applySoftLimitToKnowledge($block, $maxCharsForKnowledge);
    }

    private function normalizeQuery(string $text): string
    {
        $q = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($q === '' || mb_strlen($q) < 2) {
            return '';
        }
        $stop = ['ок', 'мск', 'спб', 'ага', 'да', 'нет'];

        return in_array(mb_strtolower($q), $stop, true) ? '' : $q;
    }

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

        $header = "Knowledge Snippets:";
        $blockTrim = trim($block);

        if (mb_strpos($blockTrim, $header) === 0) {
            $pos = mb_strpos($block, "\n");
            if ($pos === false) {
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
        if ($block === '' || $remain <= 0) {
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
