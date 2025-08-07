<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\TelegramBot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findOneByTelegramIdAndBot(int $telegramId, TelegramBot $bot): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.telegramId = :telegramId')
            ->andWhere('c.telegramBot = :bot')
            ->setParameters([
                'telegramId' => $telegramId,
                'bot' => $bot,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
