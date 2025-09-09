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

        // если после normalizeQuery пришло пусто — безопасный fallback по заголовку
        if ('' === $query) {
            $sql = '
            SELECT id, title, LEFT(answer, 300) AS snippet, answer AS content,
                   (0.2 * CASE WHEN priority > 0 THEN 1 ELSE 0 END) AS score,
                   created_at
            FROM company_knowledge
            WHERE company_id = :company
            ORDER BY priority DESC, created_at DESC
            LIMIT :limit
        ';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('company', $company->getId(), \PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

            return $stmt->executeQuery()->fetchAllAssociative();
        }

        $sql = "
        SELECT id, title, LEFT(answer, 300) AS snippet, answer AS content,
               (
                   0.7 * ts_rank_cd(ts_ru, plainto_tsquery('russian', :q))
                 + 0.3 * similarity(title, :q)
                 + 0.2 * CASE WHEN priority > 0 THEN 1 ELSE 0 END
                 ".($hintType ? ' + 0.2 * CASE WHEN type = :hintType THEN 1 ELSE 0 END ' : '')."
               ) AS score,
               created_at
        FROM company_knowledge
        WHERE company_id = :company
          AND (
                ts_ru @@ plainto_tsquery('russian', :q)
             OR title ILIKE '%'||:q||'%'
             OR answer ILIKE '%'||:q||'%'
          )
          ".(/* актуальность, если поля есть: */ false ? ' AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW()) ' : '').'
        ORDER BY score DESC, created_at DESC
        LIMIT :limit
    ';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('company', $company->getId(), \PDO::PARAM_STR);
        $stmt->bindValue('q', $query, \PDO::PARAM_STR);
        if ($hintType) {
            $stmt->bindValue('hintType', $hintType, \PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
