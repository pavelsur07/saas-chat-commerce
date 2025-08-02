<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    public function findBySlug(string $slug): Company
    {
        /* $this->findOneBy(['slug' => $slug]); */
        $object = $this->em->getRepository(Company::class)->findOneBy(['slug' => $slug]);
        if ($object) {
            throw new \DomainException('Компания с таким slug уже существует.');
        }

        return $object;
    }
}
