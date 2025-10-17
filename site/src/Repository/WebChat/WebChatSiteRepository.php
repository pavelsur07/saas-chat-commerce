<?php

namespace App\Repository\WebChat;

use App\Entity\WebChat\WebChatSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WebChatSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebChatSite::class);
    }

    public function siteKeyExists(string $siteKey): bool
    {
        return (bool) $this->createQueryBuilder('site')
            ->select('1')
            ->andWhere('site.siteKey = :key')
            ->setParameter('key', $siteKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
