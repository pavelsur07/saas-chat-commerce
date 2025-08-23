<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Service\Company\CompanyContextService;
use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use Doctrine\ORM\EntityManagerInterface;
// ваши билдеры
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class SuggestionsControllerTest extends WebTestCase
{
    public function testHappyPath(): void
    {
        // 1) Поднимаем браузера (Kernel поднимется ровно один раз)
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // 2) Владелец компании: ОБЯЗАТЕЛЬНО с УНИКАЛЬНЫМ email и ЗАДАННЫМ ПАРОЛЕМ
        // Если в билдере другие имена методов (например, withPlainPassword), замени здесь.
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')  // <-- пароль обязателен по вашей доменной логике
            ->build();
        $em->persist($owner);

        // 3) Компания с владельцем (уникальный slug)
        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build($em); // возвращаем [Company, Owner?] — подстрой при необходимости
        $em->persist($company);
        $em->flush();

        // 4) ЛОГИНИМСЯ владельцем
        $browser->loginUser($owner);

        // 5) ИНИЦИАЛИЗИРУЕМ СЕССИЮ перед setCompany(), чтобы не было SessionNotFoundException
        $session = $container->get('session');
        $requestStack = $container->get('request_stack');

        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($company);

        // Сохраняем сессию и подкладываем cookie браузеру, чтобы следующий HTTP‑запрос видел активную компанию
        $session->save();
        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        // 6) Клиент этой же компании (делаем externalId уникальным)
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build($em);
        $em->persist($client);
        $em->flush();

        // 7) Делаем запрос к защищённому эндпоинту — это и проверка, что юзер реально залогинен:
        //    если бы не был — получили бы 302 на /login (или 403).
        $payload = ['lastMessage' => 'Здравствуйте', 'historyLimit' => 2];
        $browser->request(
            'POST',
            '/api/suggestions/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        // 8) Убеждаемся, что нас НЕ редиректит на /login и ответ успешный
        self::assertResponseIsSuccessful(); // если был бы редирект — тут бы упало

        // 9) Базовая проверка структуры
        $json = json_decode($browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertArrayHasKey('suggestions', $json);
        self::assertIsArray($json['suggestions']);
    }
}
