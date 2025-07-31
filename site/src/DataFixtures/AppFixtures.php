<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\Company;
use App\Entity\UserCompany;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {

        // === User 1: Admin в Company 1 ===
        $user1 = new User(Uuid::uuid4()->toString());
        $user1->setEmail('admin@convotech.io');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $user1->setRoles(['ROLE_ADMIN']);
        $manager->persist($user1);

        // === Company 1 ===
        $company1 = new Company(Uuid::uuid4()->toString(), $user1);
        $company1->setName('ConvoTech');
        $company1->setSlug('convotech');
        $manager->persist($company1);

        // === Company 2 ===
        $company2 = new Company(Uuid::uuid4()->toString(), $user1);
        $company2->setName('FlowAI Inc');
        $company2->setSlug('flowai');
        $manager->persist($company2);

        // === User 1: Admin в Company 1 ===
        $userCompany1 = new UserCompany(Uuid::uuid4()->toString(),$user1, $company1);
        $userCompany1->setRole('admin');
        $manager->persist($userCompany1);

        // === User 1: Admin в Company 2 ===
        $userCompany1 = new UserCompany(Uuid::uuid4()->toString(),$user1, $company2);
        $userCompany1->setRole('admin');
        $manager->persist($userCompany1);


        // === User 2: Оператор в Company 1 ===
        $user2 = new User(Uuid::uuid4()->toString());
        $user2->setEmail('operator@convotech.io');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $user2->setRoles(['ROLE_OPERATOR']);
        $manager->persist($user2);

        $userCompany2 = new UserCompany(Uuid::uuid4()->toString(),$user2, $company1);
        $userCompany2->setRole('operator');
        $manager->persist($userCompany2);

        // === User 3: Пользователь во второй компании ===
        $user3 = new User(Uuid::uuid4()->toString());
        $user3->setEmail('user@flowai.com');
        $user3->setPassword($this->passwordHasher->hashPassword($user3, 'password'));
        $user3->setRoles(['ROLE_USER']);
        $manager->persist($user3);

        $userCompany3 = new UserCompany(Uuid::uuid4()->toString(),$user3, $company2);
        $userCompany3->setRole('operator');
        $manager->persist($userCompany3);


        // === Telegram Client ===
        $telegramClient = new Client(Uuid::uuid4()->toString(),'telegram', '123456789', $company1);
        $telegramClient->setUsername('telegram_user');
        $telegramClient->setFirstName('Alex');
        $telegramClient->setRawData(['chat' => ['id' => 123456789, 'username' => 'telegram_user']]);
        $manager->persist($telegramClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(),$telegramClient, 'in', 'Привет!'));
        $manager->persist(new Message(Uuid::uuid4()->toString(),$telegramClient, 'out', 'Здравствуйте, чем могу помочь?'));

        // === WhatsApp Client ===
        $whatsappClient = new Client(Uuid::uuid4()->toString(),'whatsapp', '79001234567', $company1);
        $whatsappClient->setFirstName('Ivan');
        $whatsappClient->setUsername('+7 900 123-45-67');
        $whatsappClient->setRawData(['wa_id' => '79001234567']);
        $manager->persist($whatsappClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(),$whatsappClient, 'in', 'Здравствуйте, вы доставляете в Казань?'));
        $manager->persist(new Message(Uuid::uuid4()->toString(),$whatsappClient, 'out', 'Да, доставка в Казань занимает 2 дня'));

        // === Instagram Client ===
        $instaClient = new Client(Uuid::uuid4()->toString(),'instagram', 'insta_001', $company2);
        $instaClient->setUsername('@insta_user');
        $instaClient->setFirstName('Olga');
        $instaClient->setRawData(['profile' => ['username' => '@insta_user']]);
        $manager->persist($instaClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(),$instaClient, 'in', 'Это точно хлопок?'));
        $manager->persist(new Message(Uuid::uuid4()->toString(),$instaClient, 'out', 'Да, 100% органический хлопок!'));

        // Сохранение всех
        $manager->flush();
    }
}
