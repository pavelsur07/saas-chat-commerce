<?php

declare(strict_types=1);

namespace App\ReadModel\AI;

final class KnowledgeHit
{
    public function __construct(
        public string $id,
        public string $title,
        public string $snippet,
        public float $score,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
