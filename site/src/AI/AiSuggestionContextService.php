<?php

declare(strict_types=1);

namespace App\AI;

use App\Entity\AI\AiCompanyProfile;
use App\Entity\Company\Company;
use App\Repository\AI\AiCompanyProfileRepository;
use App\Repository\AI\CompanyKnowledgeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiSuggestionContextService
{
    private array $stopSingles = [
        'ок', 'окей', 'ага', 'да', 'нет', 'привет', 'здравствуйте', 'добрый день', 'алло', 'спс', 'спасибо',
    ];

    private array $lastHitIds = [];

    public function __construct(
        private readonly AiCompanyProfileRepository $profileRepo,
        private readonly CompanyKnowledgeRepository $knowledgeRepo,
        #[Autowire(service: 'monolog.logger.ai.knowledge')]
        private readonly LoggerInterface $knowledgeLogger,
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
        $this->lastHitIds = [];
        $parts = [];

        // 1) Профиль компании (не режем отдельно, но учитываем в общем бюджете)
        $profile = $this->fetchCompanyProfile($company);
        $profileBlock = $this->buildProfileBlock($profile);
        if ('' !== $profileBlock) {
            $parts[] = $profileBlock;
        }

        // 2) Знания с мягким ограничением с учётом уже занятых символов
        $normalizedQuery = $this->normalizeQuery($incomingText);

        $knowledgeHits = [];
        if ('' !== $normalizedQuery && $limit > 0) {
            $startedAt = microtime(true);
            $baseLogContext = [
                'company_id' => $company->getId(),
                'raw_query' => $incomingText,
                'norm_query' => $normalizedQuery,
                'engine' => 'fts+trgm+ilike',
            ];

            try {
                $knowledgeHits = $this->knowledgeRepo->findTopByQuery($company, $normalizedQuery, $limit);
                $elapsedMs = (microtime(true) - $startedAt) * 1000;
                $resultCount = \is_countable($knowledgeHits) ? \count($knowledgeHits) : 0;
                $this->knowledgeLogger->info('AI_KNOWLEDGE_SEARCH', array_merge($baseLogContext, [
                    'result_count' => $resultCount,
                    'elapsed_ms' => round($elapsedMs, 3),
                    'top_ids' => $this->collectTopIds($knowledgeHits),
                ]));
            } catch (\Throwable $e) {
                $elapsedMs = (microtime(true) - $startedAt) * 1000;
                $this->knowledgeLogger->error('AI_KNOWLEDGE_SEARCH_ERROR', array_merge($baseLogContext, [
                    'result_count' => 0,
                    'elapsed_ms' => round($elapsedMs, 3),
                    'top_ids' => [],
                    'exception_message' => $e->getMessage(),
                ]));
                $knowledgeHits = [];
            }
        }

        if (!empty($knowledgeHits)) {
            foreach ($knowledgeHits as $hit) {
                $id = $this->extractHitId($hit);
                if (null !== $id) {
                    $this->lastHitIds[] = $id;
                }
            }

            if ($this->lastHitIds) {
                $this->lastHitIds = array_values(array_unique($this->lastHitIds));
            }

            $hasGlobalLimit = $this->maxContextChars > 0;
            $knowledgeBudget = 0;
            if ($hasGlobalLimit) {
                $knowledgeBudget = $this->maxContextChars;
                if ('' !== $profileBlock) {
                    $knowledgeBudget -= mb_strlen($profileBlock) + 2; // два перевода строки между блоками
                }
                $knowledgeBudget = max(0, $knowledgeBudget);
            }

            $knowledgeAppended = false;
            if (!$hasGlobalLimit || $knowledgeBudget > 0) {
                $knowledgeBlock = $this->buildKnowledgeBlock($knowledgeHits, $hasGlobalLimit ? $knowledgeBudget : 0);
                if ('' !== $knowledgeBlock) {
                    $parts[] = $knowledgeBlock;
                    $knowledgeAppended = true;
                }
            }

            if (!$knowledgeAppended) {
                $this->lastHitIds = [];
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

    private function buildKnowledgeBlock(array $items, int $maxCharsForKnowledge): string
    {
        if (empty($items)) {
            return '';
        }

        $lines = ['Knowledge Snippets:'];

        foreach ($items as $row) {
            $title = '';
            $content = '';

            if (is_array($row)) {
                $title = (string) ($row['title'] ?? '');
                $content = (string) ($row['snippet'] ?? $row['content'] ?? '');
            } elseif (is_object($row)) {
                if (method_exists($row, 'getTitle')) {
                    $title = (string) $row->getTitle();
                } elseif (property_exists($row, 'title')) {
                    /** @phpstan-ignore-next-line */
                    $title = (string) $row->title;
                }
                if (method_exists($row, 'getContent')) {
                    $content = (string) $row->getContent();
                } elseif (method_exists($row, 'getSnippet')) {
                    $content = (string) $row->getSnippet();
                } elseif (property_exists($row, 'content')) {
                    /** @phpstan-ignore-next-line */
                    $content = (string) $row->content;
                } elseif (property_exists($row, 'snippet')) {
                    /** @phpstan-ignore-next-line */
                    $content = (string) $row->snippet;
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

    public function normalizeQuery(string $q): string
    {
        $q = str_replace(["\r", "\n"], ' ', $q);
        $q = trim($q);

        if ('' === $q) {
            return '';
        }

        $q = str_replace(['ё', 'Ё'], ['е', 'е'], $q);
        $q = mb_strtolower($q);

        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $q = trim($q);

        $startPatterns = [
            '~^(скажите\s*(?:,\s*)?пожалуйста)\s*[,:;-]*\s*~u',
            '~^(подскажите(?:\s*,?\s*пожалуйста)?)\s*[,:;-]*\s*~u',
            '~^(можно\s+ли)\s*[,:;-]*\s*~u',
            '~^(а\s+у\s+вас)\s*[,:;-]*\s*~u',
        ];
        $endPatterns = [
            '~[,\s]*(пожалуйста|спс|спасибо)\s*[!.,…]*$~u',
        ];

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($startPatterns as $pattern) {
                $next = preg_replace($pattern, '', $q);
                if (null !== $next && $next !== $q) {
                    $q = $next;
                    $changed = true;
                }
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($endPatterns as $pattern) {
                $next = preg_replace($pattern, '', $q);
                if (null !== $next && $next !== $q) {
                    $q = $next;
                    $changed = true;
                }
            }
        }

        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $q = trim($q);
        $q = preg_replace('/^[,.;:\-\s]+/u', '', $q) ?? $q;
        $q = preg_replace('/[,.;:\-\s]+$/u', '', $q) ?? $q;
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        $q = trim($q);

        if ('' === $q) {
            return '';
        }

        $lettersOnly = preg_replace('/[^\p{L}\p{N}]+/u', '', $q) ?? '';
        if ('' === $lettersOnly) {
            return '';
        }

        foreach ($this->stopSingles as $stop) {
            $normalizedStop = preg_replace(
                '/[^\p{L}\p{N}]+/u',
                '',
                mb_strtolower(str_replace(['ё', 'Ё'], ['е', 'е'], $stop))
            ) ?? '';

            if ('' !== $normalizedStop && $lettersOnly === $normalizedStop) {
                return '';
            }
        }

        if (mb_strlen($lettersOnly) < 2) {
            return '';
        }

        return $q;
    }

    public function getLastHitsCount(): int
    {
        return \count($this->lastHitIds);
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

    /**
     * @param iterable<mixed> $hits
     *
     * @return list<string>
     */
    private function collectTopIds(iterable $hits, int $max = 3): array
    {
        if ($max <= 0) {
            return [];
        }

        $topIds = [];

        foreach ($hits as $hit) {
            $id = $this->extractHitId($hit);
            if (null === $id) {
                continue;
            }

            $topIds[] = $id;

            if (\count($topIds) >= $max) {
                break;
            }
        }

        return $topIds;
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

    private function extractHitId(mixed $hit): ?string
    {
        if (is_array($hit)) {
            $candidate = $hit['id'] ?? null;
            if (null !== $candidate && '' !== (string) $candidate) {
                return (string) $candidate;
            }

            return null;
        }

        if (is_object($hit)) {
            if (method_exists($hit, 'getId')) {
                $candidate = $hit->getId();
                if (null !== $candidate && '' !== (string) $candidate) {
                    return (string) $candidate;
                }
            } elseif (property_exists($hit, 'id')) {
                /** @phpstan-ignore-next-line */
                $candidate = $hit->id;
                if (null !== $candidate && '' !== (string) $candidate) {
                    return (string) $candidate;
                }
            }
        }

        return null;
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
