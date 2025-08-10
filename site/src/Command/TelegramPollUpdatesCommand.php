<?php

namespace App\Command;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\MessageRepository;
use App\Repository\Messaging\TelegramBotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'telegram:poll-updates',
    description: 'Polls Telegram updates for all active bots.',
)]
class TelegramPollUpdatesCommand extends Command
{
    public function __construct(
        private TelegramBotRepository $botRepo,
        private ClientRepository $clientRepo,
        private MessageRepository $messageRepo,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bots = $this->botRepo->findBy(['isActive' => true]);

        foreach ($bots as $bot) {
            $this->processBot($bot, $output);
        }

        return Command::SUCCESS;
    }

    private function processBot(TelegramBot $bot, OutputInterface $output): void
    {
        $token = $bot->getToken();
        $offset = $bot->getLastUpdateId() + 1;

        try {
            $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$token}/getUpdates", [
                'query' => [
                    'offset' => $offset,
                    'timeout' => 0,
                ],
            ]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $data = [];
        }

        /* $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$token}/getUpdates", [
             'query' => [
                 'offset' => $offset,
                 'timeout' => 0,
             ],
         ]);*/

        /* $data = $response->toArray(); */

        foreach ($data['result'] ?? [] as $update) {
            $this->handleUpdate($update, $bot);
            $bot->setLastUpdateId($update['update_id']);
        }

        $this->em->flush();
    }

    private function handleUpdate(array $update, TelegramBot $bot): void
    {
        if (!isset($update['message'])) {
            return;
        }

        $msg = $update['message'];
        $from = $msg['from'] ?? [];

        $telegramId = $from['id'];
        $client = $this->clientRepo->findOneByTelegramIdAndBot($telegramId, $bot);

        if (!$client) {
            $client = new Client(Uuid::uuid4()->toString(), Client::TELEGRAM, $telegramId, $bot->getCompany());
            $client->setTelegramId(telegramId: $telegramId);
            $client->setFirstName($from['first_name'] ?? null);
            $client->setUsername($from['username'] ?? null);
            $client->setTelegramBot($bot);
            $client->setCompany($bot->getCompany());

            $this->em->persist($client);
        }

        $text = $msg['text'] ?? '';
        $message = new Message(Uuid::uuid4()->toString(), $client, Message::IN, $text, null, $bot);

        $this->em->persist($message);

        $redis = new \Predis\Client([
            'scheme' => 'tcp',
            'host' => 'redis-realtime',
            'port' => 6379,
        ]);

        $redis->publish("chat.client.{$client->getId()}", json_encode([
            'id' => $message->getId(),
            'clientId' => $client->getId(),
            'text' => $message->getText(),
            'direction' => 'in',
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]));
    }
}
