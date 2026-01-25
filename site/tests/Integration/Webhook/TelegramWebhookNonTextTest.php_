<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\MessageRepository;
use App\Service\AI\LlmClient;
use App\Tests\Build\CompanyBuild;
use App\Tests\Builders\Company\CompanyUserBuilder;
use App\Tests\Doubles\LlmClientSpy;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TelegramWebhookNonTextTest extends WebTestCase
{
    private function bootstrap(): array
    {
        $browser = static::createClient();
        $c = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $c->get(EntityManagerInterface::class);

        // Владелец
        $owner = CompanyUserBuilder::aCompanyUser()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        // Компания (обязательно с владельцем)
        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);

        // TelegramBot: конструктор (id, company), токен/активность — сеттерами
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);
        $bot->setToken('tkn_'.bin2hex(random_bytes(4)));
        $bot->setIsActive(true);
        $em->persist($bot);
        $em->flush();

        /** @var LlmClientSpy $spy */
        $spy = $c->get(LlmClientSpy::class);
        // Подменяем LlmClient на Spy
        $c->set(LlmClient::class, $spy);

        // очистка счётчика перед каждым тестом
        $spy->calls = 0;
        $spy->capturedPayloads = [];

        return [$browser, $c, $em, $company, $bot, $spy];
    }

    public function testTextMessageTriggersLlmAndSavesIngestType(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 111,
                'date' => time(),
                'chat' => ['id' => 555, 'type' => 'private'],
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertSame('Привет!', $m->getText());
        $meta = $m->getMeta() ?? [];
        self::assertSame('text', $meta['ingest']['type'] ?? null);

        // LLM должен быть вызван
        self::assertGreaterThanOrEqual(1, $spy->calls);
    }

    public function testStickerMessageDoesNotTriggerLlmAndSavesIngestType(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 666,
                'date' => time(),
                'chat' => ['id' => 560, 'type' => 'private'],
                'from' => ['id' => 782, 'is_bot' => false, 'username' => 'kate'],
                'sticker' => [
                    'file_id' => 'stkr1',
                    'file_unique_id' => 'us1',
                    'width' => 512,
                    'height' => 512,
                    'is_animated' => false,
                    'emoji' => '😎',
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertNull($m->getText(), 'Для стикера text должен быть null');
        $meta = $m->getMeta() ?? [];
        self::assertSame('sticker', $meta['ingest']['type'] ?? null);

        // На стикере LLM не должен вызываться
        self::assertSame(0, $spy->calls);
    }

    public function testPhotoMessageDoesNotTriggerLlm(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 333,
                'date' => time(),
                'chat' => ['id' => 557, 'type' => 'private'],
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertNull($m->getText(), 'Для фото без caption text должен быть null');
        $meta = $m->getMeta() ?? [];
        self::assertSame('photo', $meta['ingest']['type'] ?? null);

        // На фото без текста LLM не вызывается
        self::assertSame(0, $spy->calls);
    }

    public function testVideoMessageDoesNotTriggerLlm(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 334,
                'date' => time(),
                'chat' => ['id' => 557, 'type' => 'private'],
                'from' => ['id' => 780, 'is_bot' => false, 'username' => 'dan'],
                'video' => [
                    'file_id' => 'v1',
                    'file_unique_id' => 'uv1',
                    'duration' => 3,
                    'width' => 320,
                    'height' => 240,
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertNull($m->getText(), 'Для видео без caption text должен быть null');
        $meta = $m->getMeta() ?? [];
        self::assertSame('video', $meta['ingest']['type'] ?? null);

        // На видео без текста LLM не вызывается
        self::assertSame(0, $spy->calls);
    }

    public function testPhotoWithCaptionTriggersLlmAndSavesText(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 444,
                'date' => time(),
                'chat' => ['id' => 558, 'type' => 'private'],
                'from' => ['id' => 780, 'is_bot' => false, 'username' => 'dave'],
                'photo' => [
                    ['file_id' => 'p1', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90],
                ],
                'caption' => 'Вот фото товара 🔥', // текст должен попасть в Message.text и в LLM
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertSame('Вот фото товара 🔥', $m->getText());
        $meta = $m->getMeta() ?? [];
        self::assertSame('photo', $meta['ingest']['type'] ?? null);

        // LLM должен быть вызван (есть caption)
        self::assertGreaterThanOrEqual(1, $spy->calls);
    }

    public function testVideoWithCaptionTriggersLlmAndSavesText(): void
    {
        [$browser, $c, $em, $company, $bot, $spy] = $this->bootstrap();

        $payload = [
            'update_id' => random_int(1_000_000, 9_999_999),
            'message' => [
                'message_id' => 555,
                'date' => time(),
                'chat' => ['id' => 559, 'type' => 'private'],
                'from' => ['id' => 781, 'is_bot' => false, 'username' => 'erin'],
                'video' => [
                    'file_id' => 'v1',
                    'file_unique_id' => 'uv1',
                    'duration' => 5,
                    'width' => 640,
                    'height' => 360,
                ],
                'caption' => 'Короткое видео обзор ✅', // текст должен попасть в Message.text и в LLM
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
        $messages = $c->get(MessageRepository::class);
        $list = $messages->findBy(['company' => $company]);
        self::assertCount(1, $list);
        $m = $list[0];

        self::assertSame('Короткое видео обзор ✅', $m->getText());
        $meta = $m->getMeta() ?? [];
        self::assertSame('video', $meta['ingest']['type'] ?? null);

        // LLM должен быть вызван (есть caption)
        self::assertGreaterThanOrEqual(1, $spy->calls);
    }
}
