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

    /**
     * Возвращает последовательность сообщений клиента, ограниченную лимитом.
     * Сообщения отсортированы по времени создания по возрастанию (старые сверху).
     *
     * @param int                   $limit  Максимальное количество записей (включая служебную «лишнюю» для определения hasMore).
     * @param \DateTimeImmutable|null $before Сообщения строго раньше указанной даты (используется для пагинации «вверх»).
     *
     * @return Message[]
     */
    public function findChunkByClient(Client $client, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.client = :client')
            ->setParameter('client', $client)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($before) {
            $qb->andWhere('m.createdAt < :before')
                ->setParameter('before', $before);
        }

        $items = $qb->getQuery()->getResult();

        return array_reverse($items);
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
}
