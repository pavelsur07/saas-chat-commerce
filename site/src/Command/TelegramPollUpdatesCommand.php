<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\ClientRepository;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use App\Service\Messaging\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'telegram:poll-updates',
    description: 'Polls Telegram updates for active bots, stores inbound messages, and logs AI.'
)]
final class TelegramPollUpdatesCommand extends Command
{
    public function __construct(
        private readonly TelegramBotRepository $botRepo,
        private readonly ClientRepository $clients,
        private readonly EntityManagerInterface $em,
        private readonly TelegramService $telegram,
        private readonly LlmClient $llm, // декоратор добавит лог в ai_prompt_log
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* $bots = method_exists($this->botRepo, 'findActive')
             ? $this->botRepo->findActive()
             : $this->botRepo->findBy(['isActive' => true]);*/
        $bots = $this->botRepo->findBy(['isActive' => true]);

        if (!$bots) {
            $output->writeln('<info>No active Telegram bots.</info>');

            return Command::SUCCESS;
        }

        foreach ($bots as $bot) {
            try {
                $this->processBot($bot, $output);
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>[bot:%s] poll error: %s</error>', (string) $bot->getId(), $e->getMessage()));
            }
        }

        return Command::SUCCESS;
    }

    private function processBot(TelegramBot $bot, OutputInterface $output): void
    {
        $offset = $bot->getLastUpdateId();
        $updates = $this->telegram->getUpdates($bot, [
            'offset' => $offset ? $offset + 1 : null,
            'limit' => 50,
            'timeout' => 20,
        ]);

        if (!$updates) {
            $output->writeln(sprintf('<comment>[%s] No updates.</comment>', (string) $bot->getId()));

            return;
        }

        $maxUpdateId = (int) ($offset ?? 0);
        $accepted = 0;
        $skipped = 0;

        foreach ($updates as $upd) {
            $updateId = $upd['update_id'] ?? null;
            $message = $upd['message'] ?? ($upd['edited_message'] ?? null);

            if (!$updateId) {
                continue;
            }

            if (!$message || !is_array($message)) {
                ++$skipped;
                $maxUpdateId = max($maxUpdateId, (int) $updateId);
                continue;
            }

            $text = (string) ($message['text'] ?? '');
            $chat = $message['chat'] ?? [];
            $chatId = isset($chat['id']) ? (string) $chat['id'] : '';

            if ('' === $text || '' === $chatId) {
                ++$skipped;
                $maxUpdateId = max($maxUpdateId, (int) $updateId);
                continue;
            }

            // ЕДИНЫЙ КЛЮЧ поиска клиента: channel + externalId (строкой!)
            $client = $this->clients->findOneByChannelAndExternalId(Client::TELEGRAM, $chatId);

            if (!$client) {
                $client = new Client(
                    id: Uuid::uuid4()->toString(),
                    channel: Client::TELEGRAM,
                    externalId: $chatId,
                    company: $bot->getCompany()
                );
                $this->em->persist($client);
            }

            // Дополняем телеграм-полями (не используем их для поиска)
            if (ctype_digit($chatId)) {
                $client->setTelegramId((int) $chatId);
            }
            if (!$client->getTelegramBot()) {
                $client->setTelegramBot($bot);
            }
            if (($chat['first_name'] ?? null) && !$client->getFirstName()) {
                $client->setFirstName($chat['first_name']);
            }
            if (($chat['username'] ?? null) && !$client->getUsername()) {
                $client->setUsername($chat['username']);
            }

            // Сохраняем входящее сообщение
            $msg = new Message(
                Uuid::uuid4()->toString(),
                $client,
                Message::IN,
                $text,
                $upd,   // сырой апдейт, если meta храните как json
                $bot
            );
            $this->em->persist($msg);

            // AI: классифицируем интент (декоратор запишет ai_prompt_log)
            try {
                $intentRes = $this->llm->chat([
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $text]],
                    'feature' => AiFeature::INTENT_CLASSIFY->value ?? 'intent_classify',
                    'channel' => 'telegram',
                ]);

                $intent = trim((string) ($intentRes['content'] ?? ''));
                $meta = $msg->getMeta();
                if (!is_array($meta)) {
                    $meta = [];
                }
                $meta['ai'] = array_merge($meta['ai'] ?? [], ['intent' => $intent]);
                $msg->setMeta($meta);
            } catch (\Throwable $e) {
                // не падаем; запись в ai_prompt_log будет со status=error
            }

            $this->em->flush();
            ++$accepted;
            $maxUpdateId = max($maxUpdateId, (int) $updateId);
        }

        if ($maxUpdateId !== (int) ($bot->getLastUpdateId() ?? 0)) {
            $bot->setLastUpdateId($maxUpdateId);
            $this->em->flush();
        }

        $output->writeln(sprintf('<info>[bot:%s] accepted=%d skipped=%d lastUpdateId=%s</info>',
            (string) $bot->getId(), $accepted, $skipped, (string) $bot->getLastUpdateId()
        ));
    }
}
