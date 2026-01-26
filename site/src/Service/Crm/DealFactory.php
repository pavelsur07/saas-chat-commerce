<?php

namespace App\Service\Crm;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Entity\Crm\CrmDeal;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Messaging\Client;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DealFactory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'monolog.logger.crm')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<array-key, mixed> $meta
     */
    public function create(
        Company $company,
        CrmPipeline $pipeline,
        CrmStage $stage,
        User $createdBy,
        string $title,
        ?string $amount = null,
        ?Client $client = null,
        ?User $owner = null,
        ?string $source = null,
        array $meta = [],
        ?DateTimeImmutable $openedAt = null,
    ): CrmDeal {
        $now = new DateTimeImmutable();
        $openedAt ??= $now;

        $deal = new CrmDeal(
            Uuid::uuid4()->toString(),
            $company,
            $pipeline,
            $stage,
            $createdBy,
            $title,
            $openedAt,
        );

        $deal->setCreatedAt($now);
        $deal->setUpdatedAt($now);
        $deal->setAmount($amount);
        $deal->setClient($client);
        $deal->setOwner($owner);
        $deal->setSource($source);
        $deal->setMeta($meta);
        $deal->setStageEnteredAt($openedAt);

        $this->em->persist($deal);
        $this->em->flush();

        $this->logger->info('crm.deal_created', [
            'dealId' => $deal->getId(),
            'pipelineId' => $pipeline->getId(),
            'stageId' => $stage->getId(),
            'createdBy' => $createdBy->getId(),
            'ownerId' => $owner?->getId(),
            'clientId' => $client?->getId(),
            'source' => $source,
            'amount' => $amount,
        ]);

        return $deal;
    }
}
