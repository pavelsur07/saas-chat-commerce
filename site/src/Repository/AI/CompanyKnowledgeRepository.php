<?php

namespace App\Repository\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\Company\Company;
use App\ReadModel\AI\KnowledgeHit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

class CompanyKnowledgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, CompanyKnowledge::class);
    }

    /**
     * @return KnowledgeHit[]
     *
     * @throws Exception
     */
    public function findTopByQuery(Company $company, string $query, int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
WITH q AS (
  SELECT
    id, title, content, created_at,
    ts_rank_cd(ts_ru, plainto_tsquery('russian', :q))               AS rank_fts,
    GREATEST(similarity(title,   :q), 0)                             AS sim_title,
    GREATEST(similarity(content, :q), 0)                             AS sim_content,
    1.0 / (1.0 + EXTRACT(EPOCH FROM (NOW() - created_at)) / 2592000) AS fresh
  FROM company_knowledge
  WHERE company_id = :company
    AND (
      ts_ru @@ plainto_tsquery('russian', :q)
      OR similarity(title,   :q) > 0.2
      OR similarity(content, :q) > 0.2
    )
)
SELECT
  id,
  title,
  content,
    LEFT(content, 320) AS snippet,
    created_at,
  ((rank_fts * 1.0 + sim_title * 0.8 + sim_content * 0.3) * (0.6 + 0.4 * fresh)) AS score
FROM q
ORDER BY score DESC, created_at DESC
LIMIT :limit
SQL;

        // ✅ без депрекейта: через Connection::executeQuery
        $stmt = $conn->executeQuery($sql, [
            'company' => $company->getId(),
            'q' => $query,
            'limit' => $limit,
        ]);

        $rows = $stmt->fetchAllAssociative();

        $out = [];
        foreach ($rows as $r) {
            $out[] = new KnowledgeHit(
                (string) $r['id'],
                (string) $r['title'],
                (string) $r['snippet'],
                (string) $r['content'],
                (float) $r['score'],
                new \DateTimeImmutable((string) $r['created_at'])
            );
        }

        return $out;
    }
}
