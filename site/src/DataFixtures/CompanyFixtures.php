<?php

namespace App\DataFixtures;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class CompanyFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_COMPANY_1 = 'company_1';
    public const REFERENCE_COMPANY_2 = 'company_2';

    public function load(ObjectManager $manager): void
    {
        $user1 = $this->getReference(UserFixtures::REFERENCE_USER_1_ADMIN, User::class);

        // === Company 1 ===
        $company1 = new Company(Uuid::uuid4()->toString(), $user1);
        $company1->setName('ConvoTech');
        $company1->setSlug('convotech');
        $this->setReference(self::REFERENCE_COMPANY_1, $company1);
        $manager->persist($company1);

        // === Company 2 ===
        $company2 = new Company(Uuid::uuid4()->toString(), $user1);
        $company2->setName('FlowAI Inc');
        $company2->setSlug('flowai');
        $this->setReference(self::REFERENCE_COMPANY_2, $company2);
        $manager->persist($company2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
