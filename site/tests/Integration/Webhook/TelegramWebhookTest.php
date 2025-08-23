<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\LlmClient;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Doubles\LlmClientSpy;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TelegramWebhookTest extends WebTestCase
{
    public function testStickerIsSavedWithoutLlmCall(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

        // Владелец + Компания
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);
        // Компания и активный бот
        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        $bot = (new TelegramBot(Uuid::uuid4()->toString(), $company));
        $bot->setToken('tkn_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        // Подмена LLM на Spy
        /** @var LlmClientSpy $spy */
        $spy = $c->get(LlmClientSpy::class);
        $c->set(LlmClient::class, $spy);

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 123,
                'date' => time(),
                'chat' => ['id' => 555, 'type' => 'private', 'username' => 'u1'],
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'U'],
                'sticker' => ['file_id' => 'abc', 'emoji' => '🙂'],
            ],
        ];

        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseIsSuccessful();
        self::assertSame(0, $spy->calls, 'LLM не должен вызываться для не‑текстовых сообщений');
    }

    public function testTextCreatesClientAndCallsLlm(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

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

        $bot = (new TelegramBot(Uuid::uuid4()->toString(), $company));
        $bot->setToken('tkn_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        /** @var LlmClientSpy $spy */
        $spy = $c->get(LlmClientSpy::class);
        $c->set(LlmClient::class, $spy);

        $chatId = random_int(1_000_000, 9_999_999);
        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 321,
                'date' => time(),
                'chat' => ['id' => $chatId, 'type' => 'private', 'username' => 'buyer'],
                'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'Buyer'],
                'text' => 'Здравствуйте! Есть ли в наличии?',
            ],
        ];

        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $spy->calls, 'LLM должен вызваться для текстового сообщения');

        /** @var ClientRepository $clients */
        $clients = $c->get(ClientRepository::class);
        $found = $clients->findOneBy([
            'company' => $company,
            'externalId' => (string) $chatId,
        ]);
        self::assertNotNull($found, 'Клиент должен быть создан/найден по externalId=chat.id в рамках компании');
    }

    public function testInvalidBotReturns403(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

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

        $bot = (new TelegramBot(Uuid::uuid4()->toString(), $company));
        $bot->setToken('inactive_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(false);
        $em->persist($bot);
        $em->flush();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => 111, 'type' => 'private'],
                'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'U'],
                'text' => 'test',
            ],
        ];

        // Тестируем обращение к несуществующему токену
        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.('not_exists_'.$bot->getToken()),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseStatusCodeSame(403);
        $json = json_decode($browser->getResponse()->getContent(), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('error', $json);
    }

    public function testSameUpdateProcessedOnce(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);
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

        $bot = (new TelegramBot(Uuid::uuid4()->toString(), $company));
        $bot->setToken('tkn_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        $updateId = random_int(1_000_000, 9_999_999);
        $payload = [
            'update_id' => $updateId,
            'message' => [
                'message_id' => 777,
                'date' => time(),
                'chat' => ['id' => 222, 'type' => 'private'],
                'from' => ['id' => 222, 'is_bot' => false, 'first_name' => 'U2'],
                'text' => 'Привет',
            ],
        ];

        // Первый раз
        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        // Повторно с тем же update_id
        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        /** @var MessageRepository $messages */
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertGreaterThanOrEqual(1, count($list), 'Сообщение должно сохраняться один раз (идемпотентность)');
    }
}
