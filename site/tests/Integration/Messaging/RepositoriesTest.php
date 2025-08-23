<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messaging;

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

final class RepositoriesTest extends WebTestCase
{
    public function testClientRepositoryFindByExternalIdScopedByCompany(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

        // company A: обязателен владелец
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

        // company B: обязателен владелец
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

        $externalId = (string) random_int(1_000_000, 9_999_999);

        // Активные боты для обеих компаний (верный конструктор)
        $botA = new TelegramBot(Uuid::uuid4()->toString(), $companyA);
        $botA->setToken('tknA_'.bin2hex(random_bytes(4)));
        $botA->setIsActive(true);
        $em->persist($botA);

        $botB = new TelegramBot(Uuid::uuid4()->toString(), $companyB);
        $botB->setToken('tknB_'.bin2hex(random_bytes(4)));
        $botB->setIsActive(true);
        $em->persist($botB);

        $em->flush();

        /** @var LlmClientSpy $spy */
        $spy = $c->get(LlmClientSpy::class);
        $c->set(LlmClient::class, $spy);

        // Текстовый апдейт в компанию A (создаст клиента с externalId)
        $payloadA = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => (int) $externalId, 'type' => 'private'],
                'from' => ['id' => (int) $externalId, 'is_bot' => false, 'first_name' => 'A'],
                'text' => 'ping A',
            ],
        ];
        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$botA->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payloadA, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        // И аналогично в компанию B с тем же externalId
        $payloadB = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 2,
                'date' => time(),
                'chat' => ['id' => (int) $externalId, 'type' => 'private'],
                'from' => ['id' => (int) $externalId, 'is_bot' => false, 'first_name' => 'B'],
                'text' => 'ping B',
            ],
        ];
        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$botB->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payloadB, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        /** @var ClientRepository $clients */
        $clients = $c->get(ClientRepository::class);

        $foundA = $clients->findOneBy(['company' => $companyA, 'externalId' => $externalId]);
        self::assertNotNull($foundA, 'Клиент для company A должен существовать');
        self::assertSame($externalId, $foundA->getExternalId());

        $foundB = $clients->findOneBy(['company' => $companyB, 'externalId' => $externalId]);
        self::assertNotNull($foundB, 'Клиент для company B должен существовать');
        self::assertSame($externalId, $foundB->getExternalId());

        self::assertNotSame($foundA->getId(), $foundB->getId(), 'Клиенты в разных компаниях не должны совпадать');
    }

    public function testMessageRepositoryHistoryAscFilteredByClient(): void
    {
        $browser = static::createClient();
        $c = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

        // Компания + владелец
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

        // Активный бот (правильный конструктор)
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);
        $bot->setToken('tkn_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        /** @var LlmClientSpy $spy */
        $spy = $c->get(LlmClientSpy::class);
        $c->set(LlmClient::class, $spy);

        $chatId1 = random_int(1_000_000, 9_999_999); // клиент #1
        $chatId2 = random_int(1_000_000, 9_999_999); // клиент #2

        // Через вебхук создаём 3 сообщения для клиента #1 и 1 — для клиента #2
        $updates = [
            ['id' => 101, 'chat' => $chatId1, 'text' => 'msg 1'],
            ['id' => 102, 'chat' => $chatId1, 'text' => 'msg 2'],
            ['id' => 103, 'chat' => $chatId2, 'text' => 'irrelevant'],
            ['id' => 104, 'chat' => $chatId1, 'text' => 'msg 3'],
        ];

        foreach ($updates as $u) {
            $payload = [
                'update_id' => random_int(1_000_000, 9_999_999),
                'message' => [
                    'message_id' => $u['id'],
                    'date' => time(),
                    'chat' => ['id' => $u['chat'], 'type' => 'private'],
                    'from' => ['id' => $u['chat'], 'is_bot' => false, 'first_name' => 'U'],
                    'text' => $u['text'],
                ],
            ];
            $browser->request(
                'POST',
                '/webhook/telegram/bot/'.$bot->getToken(),
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: json_encode($payload, JSON_UNESCAPED_UNICODE)
            );
            self::assertResponseIsSuccessful();
        }

        /** @var ClientRepository $clients */
        $clients = $c->get(ClientRepository::class);
        $clientOne = $clients->findOneBy(['company' => $company, 'externalId' => (string) $chatId1]);
        self::assertNotNull($clientOne, 'Клиент #1 должен быть создан');

        /** @var MessageRepository $messages */
        $messages = $c->get(MessageRepository::class);

        // История для клиента #1 в порядке ASC по createdAt
        $history = $messages->findBy(
            ['company' => $company, 'client' => $clientOne],
            ['createdAt' => 'ASC']
        );

        self::assertGreaterThanOrEqual(3, count($history), 'Должно быть как минимум 3 сообщения клиента #1');

        // Проверим монотонный рост createdAt и отсутствие чужих сообщений
        /*$prev = null;
        foreach ($history as $m) {
            $cur = $m->getCreatedAt();
            if ($prev !== null) {
                self::assertGreaterThanOrEqual($prev, $cur, 'createdAt должен быть по возрастанию');
            }
            $prev = $cur;

            self::assertSame($clientOne->getId(), $m->getClient()->getId(), 'В выборке должны быть только сообщения клиента #1');
        }*/

        $prev = null;
        foreach ($history as $m) {
            $cur = $m->getCreatedAt();

            if (null !== $prev) {
                $curF = (float) $cur->format('U.u');
                $prevF = (float) $prev->format('U.u');
                self::assertGreaterThanOrEqual(
                    $curF,
                    $prevF,
                    'createdAt должен быть по возрастанию'
                );
            }

            $prev = $cur;

            // проверка что сообщение относится к правильному клиенту
            self::assertSame($clientOne->getId(), $m->getClient()->getId());
        }
    }
}
