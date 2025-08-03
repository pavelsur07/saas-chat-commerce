<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TelegramBotFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ]; // TODO: Implement getDependencies() method.
    }
}
