<?php

namespace App\Entity\Messaging;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Repository\Messaging\ClientReadStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity(repositoryClass: ClientReadStateRepository::class)]
#[ORM\Table(name: '`client_read_states`')]
#[ORM\UniqueConstraint(name: 'uniq_client_read_state_company_client_user', columns: ['company_id', 'client_id', 'user_id'])]
#[ORM\Index(columns: ['company_id', 'user_id'], name: 'idx_client_read_state_company_user')]
#[ORM\Index(columns: ['company_id', 'client_id'], name: 'idx_client_read_state_company_client')]
class ClientReadState
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastReadAt = null;

    public function __construct(string $id, Company $company, Client $client, User $user)
    {
        Assert::uuid($id);

        $this->id = $id;
        $this->company = $company;
        $this->client = $client;
        $this->user = $user;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLastReadAt(): ?DateTimeImmutable
    {
        return $this->lastReadAt;
    }

    public function setLastReadAt(?DateTimeImmutable $lastReadAt): void
    {
        $this->lastReadAt = $lastReadAt;
    }
}
