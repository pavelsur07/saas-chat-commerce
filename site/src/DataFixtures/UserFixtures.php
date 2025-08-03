<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public const REFERENCE_USER_1_ADMIN = 'user_admin_1';
    public const REFERENCE_USER_2_OPERATOR = 'user_operator_1';
    public const REFERENCE_USER_3_USER = 'user_user_1';

    public function load(ObjectManager $manager): void
    {
        // === User 1: Admin в Company 1 ===
        $user1 = new User(Uuid::uuid4()->toString());
        $user1->setEmail('admin@convotech.io');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $user1->setRoles(['ROLE_ADMIN']);
        $this->setReference(self::REFERENCE_USER_1_ADMIN, $user1);
        $manager->persist($user1);

        // === User 2: Оператор в Company 1 ===
        $user2 = new User(Uuid::uuid4()->toString());
        $user2->setEmail('operator@convotech.io');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $user2->setRoles(['ROLE_OPERATOR']);
        $this->setReference(self::REFERENCE_USER_2_OPERATOR, $user2);
        $manager->persist($user2);

        // === User 3: Пользователь во второй компании ===
        $user3 = new User(Uuid::uuid4()->toString());
        $user3->setEmail('user@flowai.com');
        $user3->setPassword($this->passwordHasher->hashPassword($user3, 'password'));
        $user3->setRoles(['ROLE_USER']);
        $this->setReference(self::REFERENCE_USER_3_USER, $user3);
        $manager->persist($user3);

        $manager->flush();
    }
}
