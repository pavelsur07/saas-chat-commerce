<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Messaging\TelegramBotRepository;
use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\MessageIngressService;
use App\Service\Messaging\TelegramInboundMessageFactory;
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
        private readonly TelegramBotRepository $bots,
        private readonly MessageIngressService $ingress,
        private readonly TelegramInboundMessageFactory $messageFactory,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max updates per bot per iteration', 50)
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Long-polling timeout (seconds)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $timeout = (int) $input->getOption('timeout');

        $bots = $this->bots->findBy(['isActive' => true]);
        if (0 === count($bots)) {
            $output->writeln('<info>No active Telegram bots.</info>');

            return Command::SUCCESS;
        }

        foreach ($bots as $bot) {
            try {
                $offset = $bot->getLastUpdateId();
                $updates = $this->telegram->getUpdates($bot, [
                    'offset' => $offset ? $offset + 1 : null,
                    'limit' => $limit,
                    'timeout' => $timeout,
                ]);

                if (!is_array($updates) || [] === $updates) {
                    $output->writeln(sprintf('<comment>[%s] No updates.</comment>', (string) $bot->getId()));
                    continue;
                }

                $maxUpdateId = $bot->getLastUpdateId();
                $accepted = 0;

                foreach ($updates as $upd) {
                    $updateId = $upd['update_id'] ?? null;

                    if (null === $updateId) {
                        continue;
                    }

                    $inbound = $this->messageFactory->createFromUpdate($bot, $upd);

                    if ($inbound instanceof InboundMessage) {
                        $this->ingress->accept($inbound);
                        ++$accepted;
                    }

                    $maxUpdateId = $this->maxUpdateId($maxUpdateId, $updateId);
                }

                if (null !== $maxUpdateId && $maxUpdateId !== $bot->getLastUpdateId()) {
                    $bot->setLastUpdateId($maxUpdateId);
                    $this->em->flush();
                }

                $output->writeln(sprintf(
                    '<info>[bot:%s] accepted=%d lastUpdateId=%s</info>',
                    (string) $bot->getId(),
                    $accepted,
                    (string) $bot->getLastUpdateId()
                ));
            } catch (\Throwable $e) {
                $output->writeln(sprintf(
                    '<error>[bot:%s] poll error: %s</error>',
                    (string) $bot->getId(),
                    $e->getMessage()
                ));
            }
        }

        return Command::SUCCESS;
    }

    private function maxUpdateId(?int $current, int|string|null $new): ?int
    {
        if (null === $new) {
            return $current;
        }

        $value = (int) $new;

        if (null === $current || $value > $current) {
            return $value;
        }

        return $current;
    }
}
