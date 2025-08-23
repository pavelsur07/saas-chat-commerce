<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Service\Company\CompanyContextService;
use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class SuggestionsControllerTest extends WebTestCase
{
    public function testHappyPath(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // 1) владелец + компания
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

        // 2) создаём ОДНУ сессию и подключаем её к клиенту и RequestStack ДО логина
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();

        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);

        // 3) логиним пользователя под firewall "main"
        $browser->loginUser($owner, 'main');

        // 4) сейчас в стеке ЕСТЬ активный Request с той же сессией — безопасно выставляем компанию
        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($company);
        $session->save(); // фиксируем изменения в сессии

        // 5) клиент этой же компании
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();
        $em->persist($client);
        $em->flush();

        // 6) запрос к API
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

        // 1) владелец A + компания A
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

        // 2) владелец B + компания B
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

        // 3) одна сессия: подключаем её к клиенту и стеку ДО логина
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();

        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);

        // 4) логинимся как ownerA и выставляем активную компанию A через сервис
        $browser->loginUser($ownerA, 'main');

        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($companyA);
        $session->save();

        // 5) создаём клиента в ЧУЖОЙ компании (B)
        $foreignClient = ClientBuild::make()
            ->withCompany($companyB)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();
        $em->persist($foreignClient);
        $em->flush();

        // 6) запрос к API — ожидаем 403
        $browser->request(
            'POST',
            '/api/suggestions/'.$foreignClient->getId(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['lastMessage' => 'hi', 'historyLimit' => 1], JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseStatusCodeSame(403);
    }
}
