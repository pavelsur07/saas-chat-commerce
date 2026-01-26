<?php

namespace App\Tests\Build;

use App\Account\Entity\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use Ramsey\Uuid\Uuid;

final class MessageBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?Company $company = null;
    private ?Client $client = null;
    private string $direction = Message::IN; // 'in'|'out'
    private ?string $text = 'hello';
    private ?\DateTimeImmutable $createdAt = null;

    public static function make(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withCompany(Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function withClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function withDirection(string $dir): self
    {
        $this->direction = $dir;

        return $this;
    }

    public function withText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $dt): self
    {
        $this->createdAt = $dt;

        return $this;
    }

    public function build(): Message
    {
        $msg = $this->newEntity(Message::class);

        $client = $this->client ?? ClientBuild::make()->build();
        $company = $this->company ?? $client->getCompany();

        // ID — можно через safe
        $this->setSafe($msg, 'id', $this->id ?? Uuid::uuid4()->toString());

        // КЛЮЧЕВОЕ: у Message приватный setCompany(), поэтому — ТОЛЬКО forcePriv
        $this->setForcePriv($msg, 'company', $company);

        // client тоже ставим напрямую, чтобы не задеть инварианты приватными сеттерами
        $this->setForcePriv($msg, 'client', $client);

        // Канал сообщения = канал клиента (enum)
        $channel = method_exists($client, 'getChannel') ? $client->getChannel() : Channel::SYSTEM;
        $this->setForcePriv($msg, 'channel', $channel);

        // Остальные поля можно через safe (если есть public-сеттеры, они вызовутся)
        $this->setSafe($msg, 'direction', $this->direction);
        $this->setSafe($msg, 'text', $this->text);
        $this->setSafe($msg, 'createdAt', $this->createdAt ?? new \DateTimeImmutable('now'));

        return $msg;
    }
}
