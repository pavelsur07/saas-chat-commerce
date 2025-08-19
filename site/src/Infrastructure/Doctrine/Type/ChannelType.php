<?php

namespace App\Infrastructure\Doctrine\Type;

use App\Entity\Messaging\Channel\Channel;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ChannelType extends Type
{
    public const NAME = 'channel_enum';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getVarcharTypeDeclarationSQL(['length' => 32]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Channel
    {
        if (null === $value) {
            return null;
        }

        return Channel::tryFromCaseInsensitive((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof Channel) {
            return $value->value;
        }

        $c = Channel::tryFromCaseInsensitive((string) $value);
        if (!$c) {
            throw new \InvalidArgumentException("Unknown channel: {$value}");
        }

        return $c->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
