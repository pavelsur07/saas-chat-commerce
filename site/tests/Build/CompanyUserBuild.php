<?php

namespace App\Tests\Build;

use App\Entity\Company\User as CompanyUser;
use Ramsey\Uuid\Uuid;

final class CompanyUserBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?string $email = null;
    private ?string $password = null;
    private ?array $roles = null;
    private ?bool $verified = null;

    public static function make(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withPassword(?string $pwd): self
    {
        $this->password = $pwd;

        return $this;
    }

    public function withRoles(?array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function withVerified(?bool $v): self
    {
        $this->verified = $v;

        return $this;
    }

    /** Удобный генератор уникального e‑mail */
    public static function uniqueEmail(string $label = 'user'): string
    {
        return sprintf('%s+%s@example.test', $label, bin2hex(random_bytes(4)));
    }

    public function build(): CompanyUser
    {
        $u = $this->newEntity(CompanyUser::class);

        $this->setSafe($u, 'id', $this->id ?? Uuid::uuid4()->toString());
        $this->setSafe($u, 'email', $this->email ?? self::uniqueEmail());

        // следующие поля выставляем, только если такие свойства/сеттеры реально есть
        if (null !== $this->password) {
            $this->setSafe($u, 'password', $this->password);
        }
        if (null !== $this->roles) {
            $this->setSafe($u, 'roles', $this->roles);
        }
        if (null !== $this->verified) {
            $this->setSafe($u, 'isVerified', $this->verified);
        }

        return $u;
    }
}
