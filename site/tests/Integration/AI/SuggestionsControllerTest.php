<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Traits\CompanySessionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SuggestionsControllerTest extends WebTestCase
{
    use CompanySessionHelperTrait;

    public function testHappyPath(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Владелец + Компания
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);
        $em->flush();

        // Единая сессия + логин + активируем компанию
        $this->loginAndActivateCompany($browser, $owner, $company, $em);

        // Клиент этой же компании
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();
        $em->persist($client);
        $em->flush();

        // Запрос к API
        $payload = ['lastMessage' => 'Здравствуйте', 'historyLimit' => 2];
        $browser->request(
            'POST',
            '/api/suggestions/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseIsSuccessful();
        $json = json_decode($browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertArrayHasKey('suggestions', $json);
        self::assertIsArray($json['suggestions']);
    }

    public function testForbiddenWhenNotSameCompany(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Компания A + владелец A
        $ownerA = CompanyUserBuild::make()
            ->withEmail('ua_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($ownerA);

        $companyA = CompanyBuild::make()
            ->withOwner($ownerA)
            ->withSlug('ca_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($companyA);

        // Компания B + владелец B
        $ownerB = CompanyUserBuild::make()
            ->withEmail('ub_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($ownerB);

        $companyB = CompanyBuild::make()
            ->withOwner($ownerB)
            ->withSlug('cb_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($companyB);
        $em->flush();

        // Логинимся владельцем A и активируем компанию A
        $this->loginAndActivateCompany($browser, $ownerA, $companyA, $em);

        // Клиент из чужой компании B
        $foreignClient = ClientBuild::make()
            ->withCompany($companyB)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();
        $em->persist($foreignClient);
        $em->flush();

        // Запрос к API → ожидаем 403
        $browser->request(
            'POST',
            '/api/suggestions/'.$foreignClient->getId(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['lastMessage' => 'hi', 'historyLimit' => 1], JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseStatusCodeSame(403);
    }
}
