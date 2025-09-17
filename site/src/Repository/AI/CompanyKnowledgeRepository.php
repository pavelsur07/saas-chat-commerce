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
    public function findTopByQuery(Company $company, string $query, int $limit = 5, ?string $hintType = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        if ('' === $query) {
            $sql = <<<SQL
SELECT id, title, LEFT(content, 300) AS snippet, content,
       (0.2 * CASE WHEN priority > 0 THEN 1 ELSE 0 END) AS score,
       created_at
FROM company_knowledge
WHERE company_id = :company
ORDER BY priority DESC, created_at DESC
LIMIT :limit
SQL;

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('company', $company->getId(), \PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

            return $stmt->executeQuery()->fetchAllAssociative();
        }

        $scoreParts = [
            "0.7 * ts_rank_cd(ts_ru, plainto_tsquery('russian', :q))",
            "0.3 * similarity(title, :q)",
            "0.2 * CASE WHEN priority > 0 THEN 1 ELSE 0 END",
        ];

        if (null !== $hintType) {
            $scoreParts[] = "0.2 * CASE WHEN type = :hintType THEN 1 ELSE 0 END";
        }

        $scoreExpr = implode(' + ', $scoreParts);

        $sql = <<<SQL
SELECT id, title, LEFT(content, 300) AS snippet, content,
       ({$scoreExpr}) AS score,
       created_at
FROM company_knowledge
WHERE company_id = :company
  AND (
        ts_ru @@ plainto_tsquery('russian', :q)
     OR title ILIKE '%'||:q||'%'
     OR content ILIKE '%'||:q||'%'
  )
ORDER BY score DESC, created_at DESC
LIMIT :limit
SQL;

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('company', $company->getId(), \PDO::PARAM_STR);
        $stmt->bindValue('q', $query, \PDO::PARAM_STR);
        if (null !== $hintType) {
            $stmt->bindValue('hintType', $hintType, \PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
