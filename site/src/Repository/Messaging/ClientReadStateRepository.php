<?php

namespace App\Repository\Messaging;

use App\Entity\Messaging\ClientReadState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientReadState>
 */
class ClientReadStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientReadState::class);
    }
}
