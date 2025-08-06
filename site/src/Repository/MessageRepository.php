<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findLastInboundByClient(Client $client): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.client = :client')
            ->andWhere('m.direction = :direction')
            ->setParameter('client', $client)
            ->setParameter('direction', Message::IN)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
