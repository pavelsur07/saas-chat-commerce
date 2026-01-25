<?php

declare(strict_types=1);

namespace App\Tests\Builders\Company;

use App\Entity\Company\User;
use InvalidArgumentException;

final class CompanyUserBuilder
{
    public const DEFAULT_ID = '00000000-0000-0000-0000-000000000001';
    public const DEFAULT_EMAIL = 'company.user@example.test';
    public const DEFAULT_PASSWORD = 'password';
    /** @var list<string> */
    public const DEFAULT_ROLES = ['ROLE_USER'];

    private const ALLOWED_ROLES = [
        'ROLE_USER',
        'ROLE_ADMIN',
        'ROLE_OPERATOR',
        'ROLE_OWNER',
    ];

    private string $id;
    private string $email;
    private string $password;
    /** @var list<string> */
    private array $roles;

    private function __construct()
    {
        $this->id = self::DEFAULT_ID;
        $this->email = self::DEFAULT_EMAIL;
        $this->password = self::DEFAULT_PASSWORD;
        $this->roles = self::DEFAULT_ROLES;
    }

    public static function aCompanyUser(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        if ($id === '') {
            throw new InvalidArgumentException('Id must not be empty.');
        }

        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function withEmail(string $email): self
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email must be valid.');
        }

        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function withPassword(string $password): self
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password must not be empty.');
        }

        $clone = clone $this;
        $clone->password = $password;

        return $clone;
    }

    /**
     * @param list<string> $roles
     */
    public function withRoles(array $roles): self
    {
        if (!array_is_list($roles)) {
            throw new InvalidArgumentException('Roles must be a list.');
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new InvalidArgumentException('Each role must be a string.');
            }
        }

        $invalidRoles = array_diff($roles, self::ALLOWED_ROLES);
        if ($invalidRoles !== []) {
            throw new InvalidArgumentException(sprintf('Roles must be one of: %s.', implode(', ', self::ALLOWED_ROLES)));
        }

        $clone = clone $this;
        $clone->roles = $roles;

        return $clone;
    }

    public function withIndex(int $index): self
    {
        if ($index < 1) {
            throw new InvalidArgumentException('Index must be greater than zero.');
        }

        $clone = clone $this;
        $clone->id = self::idFromIndex($index);
        $clone->email = sprintf('company.user+%d@example.test', $index);

        return $clone;
    }

    public function asAdmin(): self
    {
        return $this->withRoles(['ROLE_ADMIN']);
    }

    public function build(): User
    {
        $user = new User($this->id);
        $user->setEmail($this->email);
        $user->setPassword($this->password);
        $user->setRoles($this->roles);

        return $user;
    }

    private static function idFromIndex(int $index): string
    {
        $hex = sprintf('%012x', $index);

        return sprintf('00000000-0000-0000-0000-%012s', $hex);
    }
}
