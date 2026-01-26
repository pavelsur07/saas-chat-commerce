<?php

namespace App\Entity\AI;

use App\Entity\AI\Enum\PromptStatus;
use App\Entity\AI\Traits\Timestampable;
use App\Entity\Company\Company;
use App\Account\Entity\User;
use App\Repository\AI\AiPromptLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiPromptLogRepository::class)]
#[ORM\Table(name: 'ai_prompt_log')]
#[ORM\Index(name: 'idx_ai_prompt_log_company_created_at', columns: ['company_id', 'created_at'])]
#[ORM\Index(name: 'idx_ai_prompt_log_company_model_created', columns: ['company_id', 'model', 'created_at'])]
#[ORM\Index(name: 'idx_ai_prompt_log_company_status_created', columns: ['company_id', 'status', 'created_at'])]
// Новая: быстрая фильтрация по feature
#[ORM\Index(name: 'idx_ai_prompt_log_company_feature_created', columns: ['company_id', 'feature', 'created_at'])]
class AiPromptLog
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    // Новая колонка: тип операции (intent_classify, auto_reply и т.п.)
    #[ORM\Column(type: 'string', length: 64)]
    private string $feature;

    #[ORM\Column(type: 'string', length: 32)]
    private string $channel; // 'telegram','web','api','system'...

    #[ORM\Column(type: 'string', length: 64)]
    private string $model;   // 'gpt-4o-mini', 'gpt-5', ...

    #[ORM\Column(type: 'text')]
    private string $prompt;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $promptTokens = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $response = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $completionTokens = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalTokens = 0;

    #[ORM\Column(type: 'integer', options: ['comment' => 'латентность, мс', 'default' => 0])]
    private int $latencyMs = 0;

    #[ORM\Column(enumType: PromptStatus::class)]
    private PromptStatus $status = PromptStatus::OK;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 5, nullable: true)]
    private ?string $costUsd = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function __construct(
        string $id,
        Company $company,
        string $feature,
        string $channel,
        string $model,
        string $prompt,
    ) {
        $this->id = $id;
        $this->company = $company;
        $this->feature = $feature;
        $this->channel = $channel;
        $this->model = $model;
        $this->prompt = $prompt;
        $this->touchCreated();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): void
    {
        $this->feature = $feature;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function setPromptTokens(int $promptTokens): void
    {
        $this->promptTokens = $promptTokens;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function setCompletionTokens(int $completionTokens): void
    {
        $this->completionTokens = $completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function setTotalTokens(int $totalTokens): void
    {
        $this->totalTokens = $totalTokens;
    }

    public function getLatencyMs(): int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(int $latencyMs): void
    {
        $this->latencyMs = $latencyMs;
    }

    public function getStatus(): PromptStatus
    {
        return $this->status;
    }

    public function setStatus(PromptStatus $status): void
    {
        $this->status = $status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getCostUsd(): ?string
    {
        return $this->costUsd;
    }

    public function setCostUsd(?string $costUsd): void
    {
        $this->costUsd = $costUsd;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
