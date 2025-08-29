<?php

declare(strict_types=1);

namespace App\EventSubscriber\Knowledge;

use App\Entity\AI\CompanyKnowledge;
use App\Service\AI\KnowledgeSearchService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(Events::postPersist)]
#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::postRemove)]
final class CompanyKnowledgeChangedSubscriber
{
    public function __construct(private KnowledgeSearchService $service)
    {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidate($args);
    }

    private function invalidate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof CompanyKnowledge) {
            return; // не наша сущность — выходим
        }
        $company = $entity->getCompany();
        if ($company) {
            $this->service->invalidateCompanyCache($company);
        } else {
            // на всякий случай (хотя связи быть должна)
            $this->service->invalidateCompanyCache(
                // пусто — внутри clear()
            );
        }
    }
}
