<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\TelegramBot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findOneByTelegramIdAndBot(int $telegramId, TelegramBot $bot): ?Client
    {
        $results = $this->createQueryBuilder('c')
            ->andWhere('c.telegramId = :telegramId')
            ->andWhere('c.telegramBot = :bot')
            ->setParameters(new ArrayCollection([
                new Parameter('telegramId', $telegramId),
                new Parameter('bot', $bot),
            ]))
            ->getQuery()
            ->getResult();
        /* ->getOneOrNullResult(); */

        return $results[0] ?? null;
    }

    public function findOneByChannelAndExternalId(string $channel, string $externalId): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.channel = :ch')->setParameter('ch', $channel)
            ->andWhere('c.externalId = :ex')->setParameter('ex', $externalId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
