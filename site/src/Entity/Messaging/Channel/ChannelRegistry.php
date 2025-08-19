<?php

namespace App\Entity\Messaging\Channel;

final class ChannelRegistry
{
    /** @return Channel[] */
    public function all(): array
    {
        return Channel::cases();
    }

    public function exists(string $value): bool
    {
        return null !== Channel::tryFromCaseInsensitive($value);
    }

    public function normalize(string $value): Channel
    {
        $c = Channel::tryFromCaseInsensitive($value);
        if (!$c) {
            throw new \InvalidArgumentException("Unknown channel: {$value}");
        }

        return $c;
    }
}
