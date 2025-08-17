<?php

namespace App\Service\AI;

use App\Entity\AI\AiPromptLog;
use App\Entity\AI\Enum\PromptStatus;
use App\Entity\Company\Company;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final class AiPromptLogService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * $meta можно передать любые доп. данные (например, сырой ответ провайдера).
     */
    public function log(
        Company $company,
        string $feature,
        string $channel,
        string $model,
        string $prompt,
        PromptStatus $status,
        int $latencyMs,
        ?string $response = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        ?string $errorMessage = null,
        ?string $costUsd = null,
        ?array $meta = null,
    ): AiPromptLog {
        $log = new AiPromptLog(
            id: Uuid::uuid4()->toString(),
            company: $company,
            feature: $feature,
            channel: $channel,
            model: $model,
            prompt: $prompt
        );

        $log->setStatus($status);
        $log->setLatencyMs($latencyMs);
        $log->setResponse($response);
        $log->setPromptTokens($promptTokens);
        $log->setCompletionTokens($completionTokens);
        $log->setTotalTokens($promptTokens + $completionTokens);
        $log->setErrorMessage($errorMessage);
        $log->setCostUsd($costUsd);
        $log->setMetadata($meta);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }
}
