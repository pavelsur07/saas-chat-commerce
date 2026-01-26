<?php

namespace App\DataFixtures;

use App\Account\Entity\Company;
use App\Entity\Messaging\TelegramBot;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class TelegramBotFixtures extends Fixture implements DependentFixtureInterface
{
    public const TELEGRAM_BOT_REFERENCE = 'telegram_bot';

    public function load(ObjectManager $manager): void
    {
        $company1 = $this->getReference(CompanyFixtures::REFERENCE_COMPANY_1, Company::class);
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company1);
        $bot->setToken(Uuid::uuid4()->toString());
        $bot->setIsActive(true);
        $this->setReference(self::TELEGRAM_BOT_REFERENCE, $bot);

        $manager->persist($bot);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
