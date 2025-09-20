<?php

namespace App\Service\Crm;

use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PipelineManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'monolog.logger.crm')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createStage(
        CrmPipeline $pipeline,
        string $name,
        int $position,
        string $color = '#CBD5E1',
        int $probability = 0,
        bool $isStart = false,
        bool $isWon = false,
        bool $isLost = false,
        ?int $slaHours = null,
    ): CrmStage {
        $this->assertWonLostFlags($isWon, $isLost);

        $stage = new CrmStage(Uuid::uuid4()->toString(), $pipeline);
        $stage->setName($name);
        $stage->setPosition($position);
        $stage->setColor($color);
        $stage->setProbability($probability);
        $stage->setIsWon($isWon);
        $stage->setIsLost($isLost);
        $stage->setSlaHours($slaHours);

        if ($isStart) {
            $this->resetStartStages($pipeline, $stage);
        }
        $stage->setIsStart($isStart);

        $now = new DateTimeImmutable();
        $stage->setUpdatedAt($now);
        $pipeline->setUpdatedAt($now);

        $this->em->persist($stage);
        $this->em->flush();

        $this->logger->info('crm.pipeline_stage_created', [
            'pipelineId' => $pipeline->getId(),
            'stageId' => $stage->getId(),
            'name' => $name,
            'position' => $position,
            'isStart' => $isStart,
            'isWon' => $isWon,
            'isLost' => $isLost,
        ]);

        return $stage;
    }

    public function updateStage(
        CrmStage $stage,
        string $name,
        string $color,
        int $probability,
        bool $isStart,
        bool $isWon,
        bool $isLost,
        ?int $slaHours = null,
    ): void {
        $this->assertWonLostFlags($isWon, $isLost);

        $stage->setName($name);
        $stage->setColor($color);
        $stage->setProbability($probability);
        $stage->setSlaHours($slaHours);
        $stage->setIsWon($isWon);
        $stage->setIsLost($isLost);

        if ($isStart) {
            $this->resetStartStages($stage->getPipeline(), $stage);
        }
        $stage->setIsStart($isStart);

        $now = new DateTimeImmutable();
        $stage->setUpdatedAt($now);
        $stage->getPipeline()->setUpdatedAt($now);

        $this->em->flush();

        $this->logger->info('crm.pipeline_stage_updated', [
            'pipelineId' => $stage->getPipeline()->getId(),
            'stageId' => $stage->getId(),
            'isStart' => $isStart,
            'isWon' => $isWon,
            'isLost' => $isLost,
        ]);
    }

    /**
     * @param array<int, array{stageId: string, position: int}> $orderPairs
     */
    public function reorderStages(CrmPipeline $pipeline, array $orderPairs): void
    {
        if ($orderPairs === []) {
            return;
        }

        $normalizedPairs = [];
        foreach ($orderPairs as $pair) {
            $stageId = $pair['stageId'] ?? null;
            $position = $pair['position'] ?? null;

            if (!is_string($stageId) || !is_int($position)) {
                throw new InvalidArgumentException('Each order pair must contain stageId and position.');
            }

            $normalizedPairs[] = [
                'stageId' => $stageId,
                'position' => $position,
            ];
        }

        $stageIds = array_column($normalizedPairs, 'stageId');
        $stages = $this->em->getRepository(CrmStage::class)->findBy([
            'id' => $stageIds,
        ]);

        $stagesById = [];
        foreach ($stages as $stage) {
            $stagesById[$stage->getId()] = $stage;
        }

        $now = new DateTimeImmutable();
        foreach ($normalizedPairs as $pair) {
            $stageId = $pair['stageId'];
            $position = $pair['position'];

            if (!isset($stagesById[$stageId])) {
                throw new InvalidArgumentException(sprintf('Stage %s not found for the given pipeline.', $stageId));
            }

            $stage = $stagesById[$stageId];
            if ($stage->getPipeline()->getId() !== $pipeline->getId()) {
                throw new InvalidArgumentException(sprintf('Stage %s does not belong to the provided pipeline.', $stageId));
            }

            $stage->setPosition($position);
            $stage->setUpdatedAt($now);
        }

        $pipeline->setUpdatedAt($now);
        $this->em->flush();

        $this->logger->info('crm.pipeline_stages_reordered', [
            'pipelineId' => $pipeline->getId(),
            'order' => $normalizedPairs,
        ]);
    }

    private function assertWonLostFlags(bool $isWon, bool $isLost): void
    {
        if ($isWon && $isLost) {
            throw new InvalidArgumentException('A stage cannot be both won and lost.');
        }
    }

    private function resetStartStages(CrmPipeline $pipeline, ?CrmStage $exclude = null): void
    {
        $stages = $this->em->getRepository(CrmStage::class)->findBy([
            'pipeline' => $pipeline,
            'isStart' => true,
        ]);

        if ($stages === []) {
            return;
        }

        $now = new DateTimeImmutable();
        foreach ($stages as $existingStage) {
            if ($exclude !== null && $existingStage->getId() === $exclude->getId()) {
                continue;
            }

            $existingStage->setIsStart(false);
            $existingStage->setUpdatedAt($now);
        }
    }
}
