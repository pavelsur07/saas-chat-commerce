<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use App\Entity\WebChat\WebChatThread;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
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

    public function findLastOneByClient(string $clientId): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countInboundAfter(string $clientId, \DateTimeImmutable $after): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.client = :clientId')
            ->andWhere('m.direction = :direction')
            ->andWhere('m.createdAt > :after')
            ->setParameter('clientId', $clientId)
            ->setParameter('direction', Message::IN)
            ->setParameter('after', $after)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAllInbound(string $clientId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.client = :clientId')
            ->andWhere('m.direction = :direction')
            ->setParameter('clientId', $clientId)
            ->setParameter('direction', Message::IN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Возвращает последние сообщения клиента (новые в конце массива),
     * упорядоченные по createdAt ASC (для удобства построения истории).
     *
     * @return Message[]
     */
    public function findLastByClient(string $clientId, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        // Переворачиваем, чтобы сверху были старые, снизу новые
        return array_reverse($items);
    }

    /**
     * Возвращает сообщения клиента, созданные до указанного момента (не включая его),
     * упорядоченные по возрастанию createdAt.
     *
     * @return Message[]
     */
    public function findBefore(Client $client, \DateTimeImmutable $before, int $limit = 30): array
    {
        $items = $this->createQueryBuilder('m')
            ->andWhere('m.client = :client')
            ->andWhere('m.createdAt < :before')
            ->setParameter('client', $client)
            ->setParameter('before', $before)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($items);
    }

    public function findLastBeforeOrEqual(Client $client, \DateTimeImmutable $moment): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.client = :client')
            ->andWhere('m.createdAt <= :moment')
            ->setParameter('client', $client)
            ->setParameter('moment', $moment)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Message[]
     */
    public function findLatestForThread(WebChatThread $thread, int $limit = 50): array
    {
        $items = $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($items);
    }

    /**
     * @return Message[]
     */
    public function findThreadBefore(WebChatThread $thread, Message $before, int $limit = 50): array
    {
        $items = $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->andWhere('m.createdAt < :before')
            ->setParameter('thread', $thread)
            ->setParameter('before', $before->getCreatedAt())
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($items);
    }

    /**
     * @return Message[]
     */
    public function findThreadSince(WebChatThread $thread, DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->andWhere('m.createdAt > :since')
            ->setParameter('thread', $thread)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneInThread(WebChatThread $thread, string $messageId): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->andWhere('m.id = :id')
            ->setParameter('thread', $thread)
            ->setParameter('id', $messageId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByThreadAndDedupe(WebChatThread $thread, string $dedupeKey): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.thread = :thread')
            ->andWhere('m.dedupeKey = :dedupe')
            ->setParameter('thread', $thread)
            ->setParameter('dedupe', $dedupeKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
