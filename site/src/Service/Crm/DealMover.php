<?php

namespace App\Service\Crm;

use App\Account\Entity\User;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmStage;
use App\Entity\Crm\CrmStageHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DealMover
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'monolog.logger.crm')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function move(CrmDeal $deal, CrmStage $to, User $by, ?string $comment): void
    {
        if ($deal->getPipeline()->getId() !== $to->getPipeline()->getId()) {
            throw new \InvalidArgumentException('Stage pipeline does not match deal pipeline.');
        }

        $from = $deal->getStage();
        $now = new \DateTimeImmutable();

        $lastHistory = $this->em->getRepository(CrmStageHistory::class)->findOneBy(
            ['deal' => $deal],
            ['changedAt' => 'DESC', 'id' => 'DESC']
        );
        $lastChangeAt = $lastHistory?->getChangedAt() ?? $deal->getOpenedAt();
        $spentSeconds = max(0, $now->getTimestamp() - $lastChangeAt->getTimestamp());

        $history = new CrmStageHistory(Uuid::uuid4()->toString(), $deal, $to, $by, $now, $from);
        $history->setComment($comment);
        $history->setSpentHours((int) floor($spentSeconds / 3600));

        $deal->setStage($to);
        $deal->setStageEnteredAt($now);

        if ($to->isWon() || $to->isLost()) {
            $deal->setIsClosed(true);
            $deal->setClosedAt($now);
        } else {
            $deal->setIsClosed(false);
            $deal->setClosedAt(null);
        }

        $deal->setUpdatedAt($now);

        $this->em->persist($history);
        $this->em->flush();

        $this->logger->info('crm.deal_stage_moved', [
            'dealId' => $deal->getId(),
            'fromStageId' => $from?->getId(),
            'toStageId' => $to->getId(),
        ]);
    }
}
