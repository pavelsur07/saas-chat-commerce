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
}
