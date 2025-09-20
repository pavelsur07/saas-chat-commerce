<?php

namespace App\Service\Crm;

use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PipelineSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PipelineManager $pipelineManager,
        #[Autowire(service: 'monolog.logger.crm')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function seedDefaults(CrmPipeline $pipeline): void
    {
        $this->em->transactional(function (EntityManagerInterface $em) use ($pipeline): void {
            $stageCount = $em->getRepository(CrmStage::class)->count([
                'pipeline' => $pipeline,
            ]);

            if ($stageCount > 0) {
                return;
            }

            $definitions = [
                [
                    'name' => 'Новый',
                    'position' => 1,
                    'color' => '#3b82f6',
                    'probability' => 5,
                    'isStart' => true,
                    'slaHours' => 24,
                ],
                [
                    'name' => 'В работе',
                    'position' => 2,
                    'color' => '#6366f1',
                    'probability' => 25,
                    'slaHours' => 72,
                ],
                [
                    'name' => 'Договор',
                    'position' => 3,
                    'color' => '#0ea5e9',
                    'probability' => 55,
                    'slaHours' => 72,
                ],
                [
                    'name' => 'Оплачено',
                    'position' => 4,
                    'color' => '#10b981',
                    'probability' => 100,
                    'isWon' => true,
                    'slaHours' => 0,
                ],
                [
                    'name' => 'Отказ',
                    'position' => 5,
                    'color' => '#ef4444',
                    'probability' => 0,
                    'isLost' => true,
                    'slaHours' => 0,
                ],
            ];

            foreach ($definitions as $definition) {
                $this->pipelineManager->createStage(
                    $pipeline,
                    $definition['name'],
                    $definition['position'],
                    $definition['color'],
                    $definition['probability'],
                    $definition['isStart'] ?? false,
                    $definition['isWon'] ?? false,
                    $definition['isLost'] ?? false,
                    $definition['slaHours'] ?? null,
                );
            }

            $this->logger->info('crm.pipeline_seeded_defaults', [
                'pipelineId' => $pipeline->getId(),
                'count' => count($definitions),
            ]);
        });
    }
}
