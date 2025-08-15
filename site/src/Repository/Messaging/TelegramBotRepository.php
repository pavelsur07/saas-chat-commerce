<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\TelegramBot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TelegramBotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramBot::class);
    }

    /** @return TelegramBot[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.isActive = :true')->setParameter('true', true)
            ->andWhere('b.token IS NOT NULL AND b.token <> \'\'')
            ->orderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
