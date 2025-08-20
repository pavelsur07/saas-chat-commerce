<?php

namespace App\Tests\Build;

use App\Entity\Company\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use Ramsey\Uuid\Uuid;

final class MessageBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?Company $company = null;
    private ?Client $client = null;
    private string $direction = 'in'; // 'in' | 'out'
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

        $this->set($msg, 'id', $this->id ?? Uuid::uuid4()->toString());
        $this->set($msg, 'company', $company);
        $this->set($msg, 'client', $client);

        // канал должен совпадать с клиентским:
        $channel = method_exists($client, 'getChannel') ? $client->getChannel() : Channel::SYSTEM;
        $this->set($msg, 'channel', $channel);

        // direction, text, createdAt
        if (method_exists($msg, 'setDirection')) {
            $msg->setDirection($this->direction);
        } else {
            $this->set($msg, 'direction', $this->direction);
        }

        if (method_exists($msg, 'setText')) {
            $msg->setText($this->text);
        } else {
            $this->set($msg, 'text', $this->text);
        }

        $this->set($msg, 'createdAt', $this->createdAt ?? new \DateTimeImmutable('now'));

        return $msg;
    }
}
