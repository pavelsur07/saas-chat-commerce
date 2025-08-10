<?php

namespace App\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class JsonbType extends Type
{
    public const NAME = 'jsonb';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // жёстко указываем JSONB для PostgreSQL
        return 'JSONB';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (\is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        // чтобы Doctrine не спутал наш тип
        return true;
    }
}
