<?php

namespace App\Command;

use App\Entity\Messaging\Client;
use App\Entity\Messaging\TelegramBot;
use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\Messaging\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'telegram:poll-updates',
    description: 'Poll Telegram updates for active bots and push inbound messages through the unified ingress pipeline'
)]
final class TelegramPollUpdatesCommand extends Command
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly TelegramBotRepository $botRepo,
        private readonly MessageIngressService $ingress,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max updates per bot per call', 50)
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Long-polling timeout (seconds)', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $timeout = (int) $input->getOption('timeout');

        /** @var TelegramBot[] $bots */
        $bots = $this->botRepo->findActive(); // только активные и с непустым токеном
        if (!$bots) {
            $output->writeln('<info>No active Telegram bots.</info>');

            return Command::SUCCESS;
        }

        foreach ($bots as $bot) {
            try {
                $offset = $bot->getLastUpdateId() ? ($bot->getLastUpdateId() + 1) : null;
                $updates = $this->telegram->getUpdates($bot, [
                    'offset' => $offset,
                    'limit' => $limit,
                    'timeout' => $timeout,
                ]);

                if (!$updates) {
                    $output->writeln(sprintf('<comment>[%s] No updates.</comment>', (string) $bot->getId()));
                    continue;
                }

                $maxUpdateId = $bot->getLastUpdateId();

                foreach ($updates as $upd) {
                    $updateId = $upd['update_id'] ?? null;
                    $message = $upd['message'] ?? ($upd['edited_message'] ?? null);
                    if (!$updateId || !$message || !is_array($message)) {
                        $maxUpdateId = $this->maxId($maxUpdateId, $updateId);
                        continue;
                    }

                    $text = (string) ($message['text'] ?? '');
                    $chat = $message['chat'] ?? [];
                    $chatId = (string) ($chat['id'] ?? '');

                    // Обрабатываем только валидные текстовые входящие
                    if ('' === $text || '' === $chatId) {
                        $maxUpdateId = $this->maxId($maxUpdateId, $updateId);
                        continue;
                    }

                    // ВАЖНО: externalId — всегда chat.id (строкой).
                    // Это унифицирует с вебхуком и NormalizeMiddleware.
                    $meta = [
                        'username' => $chat['username'] ?? null,
                        'firstName' => $chat['first_name'] ?? null,
                        'lastName' => $chat['last_name'] ?? null,
                        'company' => $bot->getCompany(),  // контекст компании для LLM
                        'bot_id' => $bot->getId(),
                        'update_id' => $updateId,
                        'raw' => $upd,
                    ];

                    // ЕДИНЫЙ ВХОД → Pipeline (Normalize → Persist → AiEnrich)
                    $this->ingress->accept(new InboundMessage(
                        channel: Client::TELEGRAM,
                        externalId: $chatId,
                        text: $text,
                        clientId: null,
                        meta: $meta
                    ));

                    $maxUpdateId = $this->maxId($maxUpdateId, $updateId);
                }

                if (null !== $maxUpdateId && $maxUpdateId !== $bot->getLastUpdateId()) {
                    $bot->setLastUpdateId((int) $maxUpdateId);
                    $this->em->flush();
                }

                $output->writeln(sprintf('<info>[%s] done, lastUpdateId=%s</info>', (string) $bot->getId(), (string) $bot->getLastUpdateId()));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>[%s] poll error: %s</error>', (string) $bot->getId(), $e->getMessage()));
                continue;
            }
        }

        return Command::SUCCESS;
    }

    private function maxId(?int $a, ?int $b): ?int
    {
        if (null === $a) {
            return $b;
        }
        if (null === $b) {
            return $a;
        }

        return max($a, $b);
    }
}
