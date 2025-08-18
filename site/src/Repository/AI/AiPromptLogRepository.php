<?php

namespace App\Repository\AI;

use App\Entity\AI\AiPromptLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiPromptLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPromptLog::class);
    }

    /** @return AiPromptLog[] */
    public function latest(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return AiPromptLog[] */
    public function latestByFeature(string $feature, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.feature = :f')->setParameter('f', $feature)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /*    public function save(
            string $feature,
            int $companyId,
            int $clientId,
            ?string $prompt,
            ?string $response,
            array $meta = []
        ): void {
            $log = new AiPromptLog();
            $log->setFeature($feature);
            $log->setCompanyId($companyId);
            $log->setClientId($clientId);
            $log->setPrompt($prompt);
            $log->setResponse($response);
            $log->setMeta($meta);
            $log->setCreatedAt(new \DateTimeImmutable());

            $em = $this->getEntityManager();
            $em->persist($log);
            $em->flush();
        }*/
}
