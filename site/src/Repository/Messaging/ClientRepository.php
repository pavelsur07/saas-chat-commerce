<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\TelegramBot;
use App\Entity\WebChat\WebChatSite;
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

    public function belongsToCompany(string $clientId, string $companyId): bool
    {
        return (bool) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.id = :cid')
            ->andWhere('c.company = :coid')
            ->setParameter('cid', $clientId)
            ->setParameter('coid', $companyId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByWebChatSiteAndVisitor(WebChatSite $site, string $visitorId): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.webChatSite = :site')
            ->andWhere('c.externalId = :visitor')
            ->setParameter('site', $site)
            ->setParameter('visitor', $visitorId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
