<?php

declare(strict_types=1);

namespace App\AI;

use App\Entity\AI\AiCompanyProfile;
use App\Entity\AI\CompanyKnowledge;
use App\Entity\Company\Company;
use App\Repository\AI\CompanyKnowledgeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Собирает блок контекста по КОНКРЕТНОЙ компании:
 *  - Tone of Voice
 *  - Brand Notes
 *  - Топ-N сниппетов знаний по запросу пользователя
 *
 * Ограничивает общий размер блока по символам (ENV AI_MAX_CONTEXT_CHARS),
 * приоритизируя сохранение профиля и мягко обрезая знания.
 */
final class AiSuggestionContextService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyKnowledgeRepository $knowledgeRepo,
        private readonly int $maxContextChars = 2000,
    ) {
    }

    /**
     * @return string Готовый многострочный блок контекста (можно прямо вставлять в system prompt)
     */
    public function buildBlock(Company $company, string $incomingText, int $limit = 5): string
    {
        $remain = max(0, $this->maxContextChars);
        $parts = [];

        // 1) Профиль
        $profileRepo = $this->em->getRepository(AiCompanyProfile::class);
        /** @var AiCompanyProfile|null $profile */
        $profile = $profileRepo->findOneBy(['company' => $company]);

        if ($profile) {
            $tone = trim((string) $profile->getToneOfVoice());
            $notes = trim((string) $profile->getBrandNotes());

            if ('' !== $tone) {
                $block = "Tone of Voice:\n{$tone}";
                $this->pushBlock($parts, $block, $remain);
            }
            if ('' !== $notes) {
                $block = "Brand Notes:\n{$notes}";
                $this->pushBlock($parts, $block, $remain);
            }
        }

        // 2) Знания (мягкая обрезка в пределах остатка)
        $items = $this->knowledgeRepo->findTopByQuery($company, $incomingText, $limit);
        if ($items) {
            $lines = [];
            foreach ($items as $k) {
                /** @var CompanyKnowledge $k */
                $title = trim((string) $k->getTitle());
                $content = trim((string) $k->getContent());
                $one = '' !== $title ? ("**{$title}**\n{$content}") : $content;
                $lines[] = '- '.$one;
            }
            $kb = "Knowledge Snippets:\n".implode("\n\n", $lines);
            $this->pushBlock($parts, $kb, $remain, allowPartial: true);
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Добавляет блок к $parts, соблюдая лимит по символам.
     * При allowPartial=true — разрешает частично добавить блок с троеточием.
     */
    private function pushBlock(array &$parts, string $block, int &$remain, bool $allowPartial = false): void
    {
        if ($remain <= 0 || '' === $block) {
            return;
        }
        $sep = empty($parts) ? '' : "\n\n---\n\n";
        $need = ('' === $sep ? 0 : mb_strlen($sep)) + mb_strlen($block);

        if ($need <= $remain) {
            $parts[] = $block;
            $remain -= $need;

            return;
        }

        if ($allowPartial) {
            $cut = $remain - ('' === $sep ? 0 : mb_strlen($sep));
            if ($cut > 20) {
                $parts[] = mb_substr($block, 0, $cut - 3).'...';
                $remain = 0;
            }
        }
    }
}
