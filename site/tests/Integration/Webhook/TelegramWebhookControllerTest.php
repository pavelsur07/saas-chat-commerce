<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TelegramWebhookControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $http;
    private string $botToken;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // 1) Поднимаем kernel и создаём единственный HTTP-клиент
        $this->http = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Чистим базу (если используешь отдельную тест-БД, этого обычно достаточно)
        // $this->truncateTables(['messages', 'clients', 'telegram_bots', 'companies']);
        $this->truncateTables([
            'messages',
            'clients',
            'telegram_bots',
            'companies',
            'user',            // <-- добавь
            'user_companies',     // <-- если есть
        ]);

        // Готовим минимальные фикстуры: user + компания + активный бот
        $owner = new User(Uuid::uuid4()->toString());
        $owner->setRoles(['ROLE_OWNER']);
        $owner->setEmail('int@test.ia');
        $owner->setPassword('secret');
        $this->em->persist($owner);

        $company = new Company(Uuid::uuid4()->toString(), $owner); // подставь реальный конструктор
        $company->setName('Test LLC');
        $company->setSlug('test-llc');
        $this->em->persist($company);

        $this->botToken = '123:TEST_TOKEN';
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);
        $bot->setToken($this->botToken);
        $bot->setIsActive(true);
        $this->em->persist($bot);

        $this->em->flush();
    }

    public function testWebhookCreatesIncomingMessageWithCompanyAndBot(): void
    {
        $payload = [
            'update_id' => 9990001,
            'message' => [
                'message_id' => 1001,
                'from' => ['id' => 777123, 'is_bot' => false, 'first_name' => 'Alice', 'username' => 'alice777'],
                'chat' => ['id' => 777123, 'type' => 'private'],
                'date' => time(),
                'text' => 'Привет! Хочу узнать статус заказа.',
            ],
        ];

        // Используем клиента из setUp — НЕ вызываем createClient() заново
        $this->http->request(
            'POST',
            '/webhook/telegram/bot/'.$this->botToken,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseIsSuccessful();
        $json = json_decode($this->http->getResponse()->getContent(), true);
        self::assertTrue($json['ok'] ?? false);

        /** @var Message|null $msg */
        $msg = $this->em->getRepository(Message::class)->findOneBy([], ['createdAt' => 'DESC']);
        self::assertNotNull($msg);
        self::assertSame(Message::IN, $msg->getDirection());
        self::assertNotNull($msg->getCompany());
        self::assertNotNull($msg->getTelegramBot());
        self::assertSame('Привет! Хочу узнать статус заказа.', $msg->getText());

        /** @var Client $client */
        $client = $msg->getClient();
        self::assertSame($msg->getCompany()->getId(), $client->getCompany()->getId());
    }

    /**
     * @throws Exception
     */
    public function testWebhookWithNoFromIdDoesNothingButOk(): void
    {
        $payload = [
            'update_id' => 9990002,
            'message' => [
                'message_id' => 1002,
                // нет from.id
                'date' => time(),
                'text' => 'Это сообщение без from.id',
            ],
        ];

        $before = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messages');

        $this->http->request(
            'POST',
            '/webhook/telegram/bot/'.$this->botToken,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        self::assertResponseIsSuccessful();
        $json = json_decode($this->http->getResponse()->getContent(), true);
        self::assertTrue($json['ok'] ?? false);

        $after = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messages');
        self::assertSame($before, $after);
    }

    /**
     * @throws Exception
     */
    private function truncateTables(array $tables): void
    {
        // Для PostgreSQL: отключаем FK проверки в транзакции
        $conn = $this->em->getConnection();
        $conn->executeStatement('BEGIN');
        $conn->executeStatement('SET CONSTRAINTS ALL DEFERRED');
        foreach ($tables as $t) {
            $conn->executeStatement('TRUNCATE TABLE "'.$t.'" RESTART IDENTITY CASCADE');
        }
        $conn->executeStatement('COMMIT');
    }
}
