<?php

namespace App\Tests\Build;

use App\Entity\Company\Company;
use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use Ramsey\Uuid\Uuid;

final class ClientBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?Company $company = null;
    private Channel $channel = Channel::TELEGRAM;
    private ?string $externalId = null; // если у вас есть такое поле

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

    public function withChannel(Channel $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function withExternalId(?string $ext): self
    {
        $this->externalId = $ext;

        return $this;
    }

    public function build(): Client
    {
        $cl = $this->newEntity(Client::class);
        $this->set($cl, 'id', $this->id ?? Uuid::uuid4()->toString());
        $this->set($cl, 'company', $this->company ?? CompanyBuild::make()->build());
        // использовать сеттер, если он есть, иначе приватное поле:
        if (method_exists($cl, 'setChannel')) {
            $cl->setChannel($this->channel);
        } else {
            $this->set($cl, 'channel', $this->channel);
        }
        if (null !== $this->externalId) {
            $this->set($cl, 'externalId', $this->externalId);
        }

        return $cl;
    }
}
