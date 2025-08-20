<?php

namespace Predis;

class Client
{
    public function __construct(...$args)
    {
    }

    public function publish($channel, $message): void
    {
    }
}

namespace App\Tests\Integration\Api;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\MessageEgressService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class MessageControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $http;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->http = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncateTables([
            'messages',
            'clients',
            'telegram_bots',
            'companies',
            'user',
            'user_companies',
        ]);
    }

    public function testSendRequiresActiveCompany(): void
    {
        [$user] = $this->createUserAndCompany();
        $this->http->loginUser($user);

        $this->http->request('POST', '/api/messages/'.Uuid::uuid4()->toString(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hi'])
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $this->http->getResponse()->getStatusCode());
    }

    public function testSendClientNotFound(): void
    {
        [$user, $company] = $this->createUserAndCompany();
        $this->loginWithCompany($user, $company);

        $this->http->request('POST', '/api/messages/'.Uuid::uuid4()->toString(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hi'])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $this->http->getResponse()->getStatusCode());
    }

    public function testSendAccessDeniedForForeignClient(): void
    {
        [$user, $companyA] = $this->createUserAndCompany();
        $companyB = new Company(Uuid::uuid4()->toString(), $user);
        $companyB->setName('B');
        $companyB->setSlug('b');
        $this->em->persist($companyB);

        $client = new Client(Uuid::uuid4()->toString(), Channel::TELEGRAM, 'ext', $companyB);
        $this->em->persist($client);
        $this->em->flush();

        $this->loginWithCompany($user, $companyA);

        $this->http->request('POST', '/api/messages/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hi'])
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $this->http->getResponse()->getStatusCode());
    }

    public function testSendClientNotFromTelegram(): void
    {
        [$user, $company] = $this->createUserAndCompany();
        $client = new Client(Uuid::uuid4()->toString(), Channel::WHATSAPP, 'ext', $company);
        $this->em->persist($client);
        $this->em->flush();

        $this->loginWithCompany($user, $company);

        $this->http->request('POST', '/api/messages/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hi'])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->http->getResponse()->getStatusCode());
    }

    public function testSendInvalidText(): void
    {
        [$user, $company, $client] = $this->createClientWithBot();
        $this->loginWithCompany($user, $company);

        $this->http->request('POST', '/api/messages/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => ''])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->http->getResponse()->getStatusCode());
    }

    public function testSendCannotDetermineBot(): void
    {
        [$user, $company] = $this->createUserAndCompany();
        $client = new Client(Uuid::uuid4()->toString(), Channel::TELEGRAM, 'ext', $company);
        $this->em->persist($client);
        $this->em->flush();

        $this->loginWithCompany($user, $company);

        $this->http->request('POST', '/api/messages/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hi'])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->http->getResponse()->getStatusCode());
    }

    public function testSendSuccess(): void
    {
        [$user, $company, $client, $bot] = $this->createClientWithBot(true);

        $dummy = new class([]) extends MessageEgressService {
            public array $sent = [];

            public function __construct()
            {
                parent::__construct([]);
            }

            public function send(OutboundMessage $m): void
            {
                $this->sent[] = $m;
            }
        };
        self::getContainer()->set(MessageEgressService::class, $dummy);

        $this->loginWithCompany($user, $company);

        $this->http->request('POST', '/api/messages/'.$client->getId(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['text' => 'hello'])
        );

        self::assertResponseIsSuccessful();
        $data = json_decode($this->http->getResponse()->getContent(), true);
        self::assertSame('success', $data['status'] ?? null);
        $saved = $this->em->getRepository(Message::class)->find($data['message_id']);
        self::assertNotNull($saved);
        self::assertSame('out', $saved->getDirection());
        self::assertSame('hello', $saved->getText());
        self::assertCount(1, $dummy->sent);
    }

    private function loginWithCompany(User $user, Company $company): void
    {
        $this->http->loginUser($user);
        $session = self::getContainer()->get('session');
        $session->set('active_company_id', $company->getId());
        $session->save();
        $cookie = new Cookie($session->getName(), $session->getId());
        $this->http->getCookieJar()->set($cookie);
    }

    /**
     * @return array{User,Company}
     */
    private function createUserAndCompany(): array
    {
        $user = new User(Uuid::uuid4()->toString());
        $user->setEmail('int@test.ia');
        $user->setPassword('secret');
        $this->em->persist($user);

        $company = new Company(Uuid::uuid4()->toString(), $user);
        $company->setName('Test LLC');
        $company->setSlug('test-llc');
        $this->em->persist($company);
        $this->em->flush();

        return [$user, $company];
    }

    /**
     * @return array{User,Company,Client,TelegramBot}
     */
    private function createClientWithBot(bool $withMessage = false): array
    {
        [$user, $company] = $this->createUserAndCompany();
        $client = new Client(Uuid::uuid4()->toString(), Channel::TELEGRAM, 'ext', $company);
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);
        $bot->setToken('123:token');
        $this->em->persist($client);
        $this->em->persist($bot);

        if ($withMessage) {
            $in = Message::messageIn(Uuid::uuid4()->toString(), $client, $bot, 'ping');
            $this->em->persist($in);
        }

        $this->em->flush();

        return [$user, $company, $client, $bot];
    }

    private function truncateTables(array $tables): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('BEGIN');
        $conn->executeStatement('SET CONSTRAINTS ALL DEFERRED');
        foreach ($tables as $t) {
            $conn->executeStatement('TRUNCATE TABLE "'.$t.'" RESTART IDENTITY CASCADE');
        }
        $conn->executeStatement('COMMIT');
    }
}
