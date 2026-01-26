<?php

declare(strict_types=1);

namespace App\Channel\Repository;

use App\Channel\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    public function findOneByToken(string $token): ?Channel
    {
        return $this->findOneBy(['token' => $token]);
    }
}
