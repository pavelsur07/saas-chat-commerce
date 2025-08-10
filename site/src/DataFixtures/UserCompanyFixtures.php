<?php

namespace App\DataFixtures;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Company\UserCompany;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class UserCompanyFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $user1 = $this->getReference(UserFixtures::REFERENCE_USER_1_ADMIN, User::class);
        $user2 = $this->getReference(UserFixtures::REFERENCE_USER_2_OPERATOR, User::class);
        $user3 = $this->getReference(UserFixtures::REFERENCE_USER_3_USER, User::class);

        $company1 = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_1, Company::class);
        $company2 = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_2, Company::class);

        // === User 1: Admin в Company 1 ===
        $userCompany1 = new UserCompany(Uuid::uuid4()->toString(), $user1, $company1);
        $userCompany1->setRole('admin');
        $manager->persist($userCompany1);

        // === User 1: Admin в Company 2 ===
        $userCompany1 = new UserCompany(Uuid::uuid4()->toString(), $user1, $company2);
        $userCompany1->setRole('admin');
        $manager->persist($userCompany1);

        // === User 2: Operator в Company 1 ===
        $userCompany2 = new UserCompany(Uuid::uuid4()->toString(), $user2, $company1);
        $userCompany2->setRole('operator');
        $manager->persist($userCompany2);

        // === User 3: User в Company 2 ===
        $userCompany3 = new UserCompany(Uuid::uuid4()->toString(), $user3, $company2);
        $userCompany3->setRole('operator');
        $manager->persist($userCompany3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CompanyFixtures::class,
        ];
    }
}
