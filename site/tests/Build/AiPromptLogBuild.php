<?php

namespace App\Tests\Build;

use App\Entity\AI\AiPromptLog;
use Ramsey\Uuid\Uuid;

final class AiPromptLogBuild extends TestEntityBuilder
{
    private ?int $id = null;
    private ?string $companyId = null;
    private ?string $clientId = null;
    private string $feature = 'suggest';
    private ?string $model = 'gpt-4o-mini';
    private ?string $prompt = null;
    private ?string $response = null;
    private ?array $meta = null;        // если у вас поле meta
    private ?array $metadata = null;    // если у вас поле metadata (JSON) — как мы добавили
    private ?\DateTimeImmutable $createdAt = null;

    public static function make(): self
    {
        return new self();
    }

    public function withCompanyId(string $uuid): self
    {
        $this->companyId = $uuid;

        return $this;
    }

    public function withClientId(string $uuid): self
    {
        $this->clientId = $uuid;

        return $this;
    }

    public function withFeature(string $f): self
    {
        $this->feature = $f;

        return $this;
    }

    public function withModel(?string $m): self
    {
        $this->model = $m;

        return $this;
    }

    public function withPrompt(?string $p): self
    {
        $this->prompt = $p;

        return $this;
    }

    public function withResponse(?string $r): self
    {
        $this->response = $r;

        return $this;
    }

    public function withMeta(?array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function withMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $dt): self
    {
        $this->createdAt = $dt;

        return $this;
    }

    public function build(): AiPromptLog
    {
        $log = $this->newEntity(AiPromptLog::class);

        $this->set($log, 'id', $this->id ?? Uuid::uuid4()->toString());
        $this->set($log, 'companyId', $this->companyId ?? Uuid::uuid4()->toString());
        $this->set($log, 'clientId', $this->clientId ?? Uuid::uuid4()->toString());
        $this->set($log, 'feature', $this->feature);
        $this->set($log, 'model', $this->model);
        $this->set($log, 'prompt', $this->prompt);
        $this->set($log, 'response', $this->response);
        // подхватываем оба варианта поля, чтобы не ломать ваш текущий маппинг:
        if (method_exists($log, 'setMeta')) {
            $log->setMeta($this->meta);
        } else {
            $this->set($log, 'meta', $this->meta);
        }
        if (method_exists($log, 'setMetadata')) {
            $log->setMetadata($this->metadata);
        } else {
            $this->set($log, 'metadata', $this->metadata);
        }
        $this->set($log, 'createdAt', $this->createdAt ?? new \DateTimeImmutable('now'));

        return $log;
    }
}
