<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Message;
use App\Entity\TelegramBot;
use App\Repository\ClientRepository;
use App\Repository\MessageRepository;
use App\Repository\TelegramBotRepository;
use Doctrine\ORM\EntityManagerInterface;
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

        $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$token}/getUpdates", [
            'query' => [
                'offset' => $offset,
                'timeout' => 0,
            ],
        ]);

        $data = $response->toArray();

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
            $client = (new Client())
                ->setTelegramId($telegramId)
                ->setFirstName($from['first_name'] ?? null)
                ->setUsername($from['username'] ?? null)
                ->setTelegramBot($bot)
                ->setCompany($bot->getCompany());

            $this->em->persist($client);
        }

        $message = (new Message())
            ->setClient($client)
            ->setTelegramBot($bot)
            ->setType('incoming')
            ->setContent($msg['text'] ?? '')
            ->setSentAt((new \DateTime())->setTimestamp($msg['date'] ?? time()));

        $this->em->persist($message);
    }
}
