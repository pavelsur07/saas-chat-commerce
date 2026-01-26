<?php

namespace App\Tests\Build;

use App\Entity\Company\Company;
use App\Account\Entity\User as CompanyUser;
use App\Tests\Builders\Company\CompanyUserBuilder;
use Ramsey\Uuid\Uuid;

final class CompanyBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?string $name = 'Test Co';
    private ?string $slug = 'test-co';
    private ?CompanyUser $owner = null;
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

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function withOwner(CompanyUser $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $dt): self
    {
        $this->createdAt = $dt;

        return $this;
    }

    public function build(): Company
    {
        $c = $this->newEntity(Company::class);
        $this->setSafe($c, 'id', $this->id ?? Uuid::uuid4()->toString());
        $this->setSafe($c, 'name', $this->name ?? 'Test Co');
        $this->setSafe($c, 'slug', $this->slug ?? 'test-co');
        $this->setSafe($c, 'owner', $this->owner ?? CompanyUserBuilder::aCompanyUser()->build());
        $this->setSafe($c, 'createdAt', $this->createdAt ?? new \DateTimeImmutable('now'));

        return $c;
    }
}
