<?php

namespace App\Tests\Build;

use App\Entity\Company\User as CompanyUser;
use Ramsey\Uuid\Uuid;

final class CompanyUserBuild extends TestEntityBuilder
{
    private ?string $id = null;
    private ?string $email = null;

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

    public function build(): CompanyUser
    {
        $u = $this->newEntity(CompanyUser::class);
        $this->set($u, 'id', $this->id ?? Uuid::uuid4()->toString());
        if ($this->email) {
            $this->set($u, 'email', $this->email);
        }

        // Добавьте здесь иные обязательные поля, если есть (пароль/роли и т.п.)
        return $u;
    }
}
