<?php

namespace App\Tests\Build;

use App\Entity\Company\User as CompanyUser;
use Ramsey\Uuid\Uuid;

final class CompanyUserBuild extends TestEntityBuilder
{
    public const USER_EMAIL = 'email@email.io';
    public const USER_PASSWORD = 'password';
    private ?string $id = null;
    private ?string $email = null;
    private ?string $password = null;

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
        $this->setSafe($u, 'id', $this->id ?? Uuid::uuid4()->toString());

        $this->setSafe($u, 'email', $this->email ?? self::USER_EMAIL);
        $this->setSafe($u, 'password', $this->password ?? self::USER_PASSWORD);

        // Добавьте здесь иные обязательные поля, если есть (пароль/роли и т.п.)
        return $u;
    }
}
