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
     * Публичный фасад: склеивает профиль (ToV/Brand) и знания.
     * Профиль всегда включается, знания — только при осмысленном запросе.
     */
    public function buildBlock(Company $company, string $incomingText, int $limit = 5): string
    {
        $parts = [];

        // 1) профиль компании (не режем по длине)
        $profile = $this->fetchCompanyProfile($company);
        $profileBlock = $this->buildProfileBlock($profile);
        if ('' !== $profileBlock) {
            $parts[] = $profileBlock;
        }

        // 2) знания (мягкая обрезка применяется только к этому блоку)
        $query = $this->normalizeQuery($incomingText);
        if ('' !== $query) {
            $knowledgeBlock = $this->buildKnowledgeBlock($company, $query, $limit, $this->maxContextChars);
            if ('' !== $knowledgeBlock) {
                $parts[] = $knowledgeBlock;
            }
        }

        return $parts ? implode("\n\n", $parts) : '';
    }

    /* =========================
     *      ВСПОМОГАТЕЛЬНЫЕ
     * ========================= */

    private function fetchCompanyProfile(Company $company): ?AiCompanyProfile
    {
        // У AiCompanyProfile PK = company_id (OneToOne) — можно искать по id компании
        #$profile = $this->profileRepo->find($company->getId());
        $profile = $this->profileRepo->findOneBy(['company' => $company]);
        if ($profile instanceof AiCompanyProfile) {
            return $profile;
        }

        // запасной вариант на случай иного маппинга
        return $this->profileRepo->findOneBy(['company' => $company]);
    }

    private function buildProfileBlock(?AiCompanyProfile $profile): string
    {
        if (!$profile) {
            return '';
        }

        $chunks = [];

        // Имена геттеров соответствуют вашей форме AiCompanyProfileType (toneOfVoice, brandNotes)
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
    ): string {
        // используем ваш контракт репозитория
        $items = $this->knowledgeRepo->findTopByQuery($company, $query, $topN);
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

            $title = trim($title);
            $content = trim($content);

            if ('' !== $title && '' !== $content) {
                $lines[] = "- {$title}: {$content}";
            } elseif ('' !== $content) {
                $lines[] = "- {$content}";
            } elseif ('' !== $title) {
                $lines[] = "- {$title}";
            }
        }

        $block = implode("\n", $lines);

        // мягко режем только знания
        if ($maxCharsForKnowledge > 0 && mb_strlen($block) > $maxCharsForKnowledge) {
            $block = $this->softTruncate($block, $maxCharsForKnowledge);
        }

        return 'Knowledge Snippets:' !== trim($block) ? $block : '';
    }

    private function normalizeQuery(string $text): string
    {
        $q = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ('' === $q || mb_strlen($q) < 2) {
            return '';
        }
        $stop = ['ок', 'мск', 'спб', 'ага', 'да', 'нет'];

        return in_array(mb_strtolower($q), $stop, true) ? '' : $q;
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

    /**
     * Служебный метод совместимости: оставлен как был (если где-то дергается в тестах).
     */
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
