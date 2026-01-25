<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Entity\WebChat\WebChatSite;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use App\Repository\WebChat\WebChatThreadRepository;
use App\Service\AI\LlmClient;
use App\Tests\Build\CompanyBuild;
use App\Tests\Builders\Company\CompanyUserBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebChatHandshakeMessageTest extends WebTestCase
{
    public function testClientCreatedOnlyAfterFirstMessage(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuilder::aCompanyUser()
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
            'Site without eager clients',
            'site_'.bin2hex(random_bytes(4)),
            ['https://chat.example.com']
        );
        $em->persist($site);
        $em->flush();

        // ensure AI client doesn't leak across tests (mirrors embed tests behaviour)
        $container->set(LlmClient::class, new class implements LlmClient {
            public function chat(array $params): array
            {
                return ['choices' => []];
            }
        });

        $handshakePayload = [
            'site_key' => $site->getSiteKey(),
            'page_url' => 'https://chat.example.com/welcome',
        ];

        $browser->request(
            'POST',
            '/api/webchat/handshake?site_key='.$site->getSiteKey().'&page_url='.urlencode('https://chat.example.com/welcome'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://chat.example.com',
            ],
            content: json_encode($handshakePayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();

        $handshakeData = json_decode($browser->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('visitor_id', $handshakeData);
        self::assertArrayHasKey('thread_id', $handshakeData);
        self::assertNull($handshakeData['thread_id']);
        self::assertArrayHasKey('token', $handshakeData);
        self::assertNull($handshakeData['token']);
        self::assertArrayHasKey('session_id', $handshakeData);
        self::assertIsString($handshakeData['session_id']);
        self::assertNotSame('', $handshakeData['session_id']);

        /** @var ClientRepository $clients */
        $clients = $container->get(ClientRepository::class);
        self::assertCount(0, $clients->findBy(['company' => $company]));

        $messagePayload = [
            'site_key' => $site->getSiteKey(),
            'text' => 'Hello from visitor',
            'page_url' => 'https://chat.example.com/welcome',
            'tmp_id' => 'tmp_'.bin2hex(random_bytes(4)),
            'session_id' => $handshakeData['session_id'],
            'visitor_id' => $handshakeData['visitor_id'],
        ];

        $browser->request(
            'POST',
            '/api/webchat/messages?site_key='.$site->getSiteKey().'&page_url='.urlencode('https://chat.example.com/welcome'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://chat.example.com',
            ],
            content: json_encode($messagePayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();

        $messageData = json_decode($browser->getResponse()->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message_id', $messageData);
        self::assertArrayHasKey('thread_id', $messageData);
        self::assertIsString($messageData['thread_id']);
        self::assertNotSame('', $messageData['thread_id']);
        self::assertArrayHasKey('token', $messageData);
        self::assertIsString($messageData['token']);
        self::assertArrayHasKey('expires_in', $messageData);
        self::assertGreaterThan(0, (int) $messageData['expires_in']);
        self::assertArrayHasKey('client_id', $messageData);

        $createdClient = $clients->find($messageData['client_id']);
        self::assertNotNull($createdClient);
        self::assertSame($handshakeData['visitor_id'], $createdClient->getExternalId());

        /** @var WebChatThreadRepository $threads */
        $threads = $container->get(WebChatThreadRepository::class);
        $thread = $threads->find($messageData['thread_id']);
        self::assertNotNull($thread);
        self::assertSame($createdClient->getId(), $thread->getClient()->getId());

        /** @var MessageRepository $messages */
        $messages = $container->get(MessageRepository::class);
        $storedMessages = $messages->findBy(['client' => $createdClient]);

        self::assertCount(1, $storedMessages);
        self::assertSame('Hello from visitor', $storedMessages[0]->getText());
    }
}
