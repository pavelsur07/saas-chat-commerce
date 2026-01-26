<?php

namespace App\Entity\Company;

use App\Account\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user_companies`')]
#[ORM\UniqueConstraint(fields: ['user', 'company'])]
class UserCompany
{
    public const ROLE_OWNER = 'OWNER';
    public const ROLE_OPERATOR = 'OPERATOR';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INVITED = 'INVITED';
    public const STATUS_DISABLED = 'DISABLED';

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 16)]
    private string $role = self::ROLE_OPERATOR;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $invitedBy = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, User $user, Company $company)
    {
        Assert::uuid($id);
        $this->id = $id;
        $this->user = $user;
        $this->company = $company;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $role = strtoupper($role);
        Assert::oneOf($role, [self::ROLE_OWNER, self::ROLE_OPERATOR]);
        $this->role = $role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $status = strtoupper($status);
        Assert::oneOf($status, [self::STATUS_ACTIVE, self::STATUS_INVITED, self::STATUS_DISABLED]);
        $this->status = $status;
    }

    public function getInvitedBy(): ?string
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?string $invitedBy): void
    {
        if ($invitedBy !== null) {
            Assert::uuid($invitedBy);
        }

        $this->invitedBy = $invitedBy;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
