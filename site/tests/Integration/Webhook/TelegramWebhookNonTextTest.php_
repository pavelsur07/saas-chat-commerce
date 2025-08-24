<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\LlmClient;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Doubles\LlmClientSpy;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TelegramWebhookNonTextTest extends WebTestCase
{
    private function bootstrap(): array
    {
        $browser = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

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

        $bot = new TelegramBot(
            id: Uuid::uuid4()->toString(),
            company: $company,
        );

        $bot->setToken('TEST_TOKEN_'.Uuid::uuid4()->toString());
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        /** @var LlmClientSpy $spy */
        $spy = static::getContainer()->get(LlmClientSpy::class);
        static::getContainer()->set(LlmClient::class, $spy);

        return [$browser, $em, $company, $bot, $spy];
    }

    public function testTextMessageTriggersLlmAndSavesIngestType(): void
    {
        [$browser, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(100000, 999999),
            'message' => [
                'message_id' => 111,
                'date' => time(),
                'chat' => ['id' => 555],
                'from' => ['id' => 777, 'is_bot' => false, 'username' => 'alice'],
                'text' => 'Привет!',
            ],
        ];

        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        /** @var MessageRepository $messages */
        $messages = static::getContainer()->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertSame('Привет!', $m->getText());
        $meta = $m->getMeta() ?? [];
        self::assertSame('text', $meta['ingest']['type'] ?? null);

        // Spy должен быть вызван
        self::assertGreaterThanOrEqual(1, $spy->calls);
    }

    public function testStickerMessageDoesNotTriggerLlm(): void
    {
        [$browser, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(100000, 999999),
            'message' => [
                'message_id' => 222,
                'date' => time(),
                'chat' => ['id' => 556],
                'from' => ['id' => 778, 'is_bot' => false, 'username' => 'bob'],
                'sticker' => [
                    'file_id' => 'abc123',
                    'width' => 512,
                    'height' => 512,
                    'emoji' => '👍',
                ],
            ],
        ];

        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        /** @var MessageRepository $messages */
        $messages = static::getContainer()->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertNull($m->getText(), 'Для стикера text должен быть null');
        $meta = $m->getMeta() ?? [];
        self::assertSame('sticker', $meta['ingest']['type'] ?? null);

        // Spy НЕ должен быть вызван
        self::assertSame(0, $spy->calls);
    }

    public function testPhotoMessageDoesNotTriggerLlm(): void
    {
        [$browser, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(100000, 999999),
            'message' => [
                'message_id' => 333,
                'date' => time(),
                'chat' => ['id' => 557],
                'from' => ['id' => 779, 'is_bot' => false, 'username' => 'carol'],
                'photo' => [
                    ['file_id' => 'p1', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90],
                    ['file_id' => 'p2', 'file_unique_id' => 'u2', 'width' => 320, 'height' => 320],
                ],
            ],
        ];

        $browser->request(
            'POST',
            '/webhook/telegram/bot/'.$bot->getToken(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
        self::assertResponseIsSuccessful();

        /** @var MessageRepository $messages */
        $messages = static::getContainer()->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertNull($m->getText(), 'Для фото text должен быть null');
        $meta = $m->getMeta() ?? [];
        self::assertSame('photo', $meta['ingest']['type'] ?? null);

        // Spy НЕ должен быть вызван
        self::assertSame(0, $spy->calls);
    }
}
