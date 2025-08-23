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

        // 1) владелец с уникальным email и паролем
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        // 2) компания с этим владельцем (уникальный slug)
        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);
        $em->flush();

        // 3) создаём ЕДИНУЮ сессию и подкладываем её и в RequestStack, и в браузер ДО loginUser()
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = $container->get('session.factory'); // стандартный сервис Symfony
        $session = $sessionFactory->createSession();
        $session->start();
        // cookie в браузер
        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
        // запрос в стек с той же сессией
        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);

        // 4) логиним пользователя под файрволом "main" (из security.yaml)
        $browser->loginUser($owner, 'main');

        // 5) активируем компанию в контексте (запишется в ту же сессию)
        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($company);

        // 6) клиент этой же компании
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();
        $em->persist($client);
        $em->flush();

        // 7) запрос к API
        $payload = ['lastMessage' => 'Здравствуйте', 'historyLimit' => 2];
        $browser->request(
            'POST',
            '/api/suggestions/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        // 8) проверки
        self::assertResponseIsSuccessful();
        $json = json_decode($browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertArrayHasKey('suggestions', $json);
        self::assertIsArray($json['suggestions']);
    }
}
