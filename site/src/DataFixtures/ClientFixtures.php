<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Message;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class ClientFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $company1 = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_1, Company::class);
        $company2 = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_2, Company::class);

        // === Telegram Client ===
        $telegramClient = new Client(Uuid::uuid4()->toString(), Client::TELEGRAM, '123456789', $company1);
        $telegramClient->setUsername('telegram_user');
        $telegramClient->setFirstName('Alex');
        $telegramClient->setRawData(['chat' => ['id' => 123456789, 'username' => 'telegram_user']]);
        $manager->persist($telegramClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(), $telegramClient, 'in', 'Привет!', null, null));
        $manager->persist(new Message(Uuid::uuid4()->toString(), $telegramClient, 'out', 'Здравствуйте, чем могу помочь?', null, null));

        // === WhatsApp Client ===
        $whatsappClient = new Client(Uuid::uuid4()->toString(), Client::WHATSAPP, '79001234567', $company1);
        $whatsappClient->setFirstName('Ivan');
        $whatsappClient->setUsername('+7 900 123-45-67');
        $whatsappClient->setRawData(['wa_id' => '79001234567']);
        $manager->persist($whatsappClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(), $whatsappClient, 'in', 'Здравствуйте, вы доставляете в Казань?', null, null));
        $manager->persist(new Message(Uuid::uuid4()->toString(), $whatsappClient, 'out', 'Да, доставка в Казань занимает 2 дня', null, null));

        // === Instagram Client ===
        $instaClient = new Client(Uuid::uuid4()->toString(), 'instagram', 'insta_001', $company2);
        $instaClient->setUsername('@insta_user');
        $instaClient->setFirstName('Olga');
        $instaClient->setRawData(['profile' => ['username' => '@insta_user']]);
        $manager->persist($instaClient);

        $manager->persist(new Message(Uuid::uuid4()->toString(), $instaClient, 'in', 'Это точно хлопок?', null, null));
        $manager->persist(new Message(Uuid::uuid4()->toString(), $instaClient, 'out', 'Да, 100% органический хлопок!', null, null));

        // Сохранение всех
        $manager->flush();
    }
}
