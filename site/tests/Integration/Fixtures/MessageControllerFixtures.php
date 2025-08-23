<?php

namespace App\Tests\Integration\Fixtures;

use App\Entity\Messaging\Message;
use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Build\MessageBuild;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class MessageControllerFixtures extends Fixture
{
    public const REF_COMPANY_A = 'fx.company.a';
    public const REF_COMPANY_B = 'fx.company.b';
    public const REF_CLIENT_A1 = 'fx.client.a1';
    public const REF_CLIENT_B1 = 'fx.client.b1';

    public function load(ObjectManager $om): void
    {
        // Company A (+ owner)
        $ownerA = CompanyUserBuild::make()->build();
        $ownerA->setEmail('oneo@emqil.com');
        $companyA = CompanyBuild::make()
            ->withName('Company A')
            ->withSlug('company-a')
            ->withOwner($ownerA)
            ->build();

        // Company B (+ owner)
        $ownerB = CompanyUserBuild::make()->build();
        $ownerB->setEmail('two@emqil.com');
        $companyB = CompanyBuild::make()
            ->withName('Company B')
            ->withSlug('company-b')
            ->withOwner($ownerB)
            ->build();

        // Client A1 (telegram)
        $clientA1 = ClientBuild::make()
            ->withCompany($companyA)
            ->build(); // по умолчанию Channel::TELEGRAM

        // Client B1 (telegram)
        $clientB1 = ClientBuild::make()
            ->withCompany($companyB)
            ->build();

        // Немного истории для A1 (ASC по времени)
        $msgIn = MessageBuild::make()
            ->withCompany($companyA)
            ->withClient($clientA1)
            ->withDirection(Message::IN)
            ->withText('Здравствуйте! Есть размер S?')
            ->build();

        // сделаем второе сообщение на +1 минуту
        $msgOut = MessageBuild::make()
            ->withCompany($companyA)
            ->withClient($clientA1)
            ->withDirection(Message::OUT)
            ->withText('Да, есть 🤍')
            ->build();

        $om->persist($ownerA);
        $om->persist($ownerB);
        $om->persist($companyA);
        $om->persist($companyB);
        $om->persist($clientA1);
        $om->persist($clientB1);
        $om->persist($msgIn);
        $om->persist($msgOut);
        $om->flush();

        $this->addReference(self::REF_COMPANY_A, $companyA);
        $this->addReference(self::REF_COMPANY_B, $companyB);
        $this->addReference(self::REF_CLIENT_A1, $clientA1);
        $this->addReference(self::REF_CLIENT_B1, $clientB1);
    }
}
