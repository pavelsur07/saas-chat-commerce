<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Entity\WebChat\WebChatSite;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\LlmClient;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Doubles\LlmClientSpy;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EmbedControllerTest extends WebTestCase
{
    public function testSendPersistsMessageAndProvidesIdentifiers(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('owner_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('cmp_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            'Test Site',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com']
        );
        $em->persist($site);
        $em->flush();

        /** @var LlmClientSpy $llmSpy */
        $llmSpy = $container->get(LlmClientSpy::class);
        $container->set(LlmClient::class, $llmSpy);

        $sessionId = 'sess_'.bin2hex(random_bytes(8));

        $payload = [
            'site_key' => $site->getSiteKey(),
            'text' => 'Hello from embed',
            'session_id' => $sessionId,
            'page_url' => 'https://chat.example.com/welcome',
        ];

        $browser->request(
            'POST',
            '/api/embed/message',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://chat.example.com',
            ],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();

        $responseData = json_decode($browser->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('ok', $responseData);
        self::assertTrue($responseData['ok']);
        self::assertArrayHasKey('clientId', $responseData);
        self::assertIsString($responseData['clientId']);
        self::assertNotSame('', $responseData['clientId']);
        self::assertArrayHasKey('room', $responseData);
        self::assertSame('client-'.$responseData['clientId'], $responseData['room']);
        self::assertArrayHasKey('socket_path', $responseData);
        self::assertSame('/socket.io', $responseData['socket_path']);

        /** @var ClientRepository $clients */
        $clients = $container->get(ClientRepository::class);
        $dbClient = $clients->findOneBy(['company' => $company, 'externalId' => $sessionId]);
        self::assertNotNull($dbClient, 'Клиент должен быть создан для веб-чата');

        /** @var MessageRepository $messages */
        $messages = $container->get(MessageRepository::class);
        $storedMessages = $messages->findBy(['client' => $dbClient]);

        self::assertCount(1, $storedMessages, 'Сообщение должно быть сохранено');
        self::assertSame('Hello from embed', $storedMessages[0]->getText());
    }

    public function testInitReturnsForbiddenWhenSiteIsDisabled(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('owner_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('cmp_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            'Disabled Site',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com'],
            false,
        );
        $em->persist($site);
        $em->flush();

        $payload = [
            'site_key' => $site->getSiteKey(),
            'page_url' => 'https://chat.example.com/landing',
        ];

        $browser->request(
            'POST',
            '/api/embed/init',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://chat.example.com',
            ],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSendReturnsForbiddenWhenSiteIsDisabled(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('owner_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('cmp_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            'Disabled Site',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com'],
            false,
        );
        $em->persist($site);
        $em->flush();

        $payload = [
            'site_key' => $site->getSiteKey(),
            'text' => 'Should not be delivered',
            'session_id' => 'sess_'.bin2hex(random_bytes(8)),
            'page_url' => 'https://chat.example.com/landing',
        ];

        $browser->request(
            'POST',
            '/api/embed/message',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://chat.example.com',
            ],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testInitPreflightAllowsAllowedOrigin(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('owner_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('cmp_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            'Preflight Site',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com']
        );
        $em->persist($site);
        $em->flush();

        $browser->request(
            'OPTIONS',
            '/api/embed/init?site_key='.$site->getSiteKey(),
            server: [
                'HTTP_ORIGIN' => 'https://chat.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $response = $browser->getResponse();
        self::assertSame('https://chat.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('content-type', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
    }

    public function testMessagePreflightAllowsAllowedOrigin(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('owner_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('cmp_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            'Preflight Message Site',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com']
        );
        $em->persist($site);
        $em->flush();

        $browser->request(
            'OPTIONS',
            '/api/embed/message?site_key='.$site->getSiteKey(),
            server: [
                'HTTP_ORIGIN' => 'https://chat.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $response = $browser->getResponse();
        self::assertSame('https://chat.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('content-type', strtolower((string) $response->headers->get('Access-Control-Allow-Headers')));
    }
}
