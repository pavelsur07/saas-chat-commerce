<?php

declare(strict_types=1);

namespace App\Repository\WebChat;

use App\Entity\Messaging\Client;
use App\Entity\WebChat\WebChatThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebChatThread>
 */
final class WebChatThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebChatThread::class);
    }

    public function findLatestForClient(Client $client): ?WebChatThread
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.client = :client')
            ->setParameter('client', $client)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOpenForClient(Client $client): ?WebChatThread
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.client = :client')
            ->andWhere('t.isOpen = true')
            ->setParameter('client', $client)
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
