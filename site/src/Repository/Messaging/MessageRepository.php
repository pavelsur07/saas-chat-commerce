<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
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
        return $this->findByClientLatest($clientId, $limit);
    }

    /**
     * @return Message[]
     */
    public function findByClientLatest(string $clientId, int $limit): array
    {
        $items = $this->createQueryBuilder('m')
            ->andWhere('m.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($items);
    }

    /**
     * @return Message[]
     */
    public function findByClientBeforeId(string $clientId, string $beforeId, int $limit): array
    {
        /** @var Message|null $anchor */
        $anchor = $this->find($beforeId);

        if (!$anchor || $anchor->getClient()->getId() !== $clientId) {
            return [];
        }

        $items = $this->createQueryBuilder('m')
            ->andWhere('m.client = :clientId')
            ->andWhere('m.createdAt < :anchorCreatedAt')
            ->setParameter('clientId', $clientId)
            ->setParameter('anchorCreatedAt', $anchor->getCreatedAt())
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($items);
    }

    /**
     * @return Message[]
     */
    public function findByClientAfterId(string $clientId, string $afterId, int $limit): array
    {
        /** @var Message|null $anchor */
        $anchor = $this->find($afterId);

        if (!$anchor || $anchor->getClient()->getId() !== $clientId) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.client = :clientId')
            ->andWhere('m.createdAt > :anchorCreatedAt')
            ->setParameter('clientId', $clientId)
            ->setParameter('anchorCreatedAt', $anchor->getCreatedAt())
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findFirstUnreadId(string $clientId, ?\DateTimeImmutable $lastReadAt): ?string
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.id')
            ->andWhere('m.client = :clientId')
            ->andWhere('m.direction = :direction')
            ->setParameter('clientId', $clientId)
            ->setParameter('direction', Message::IN)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1);

        if (null !== $lastReadAt) {
            $qb
                ->andWhere('m.createdAt > :lastReadAt')
                ->setParameter('lastReadAt', $lastReadAt);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        if (!$result) {
            return null;
        }

        if (is_array($result)) {
            return $result['id'] ?? null;
        }

        return is_string($result) ? $result : null;
    }
}
