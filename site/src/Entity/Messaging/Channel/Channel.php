<?php

namespace App\Entity\Messaging\Channel;

enum Channel: string
{
    case TELEGRAM = 'telegram';
    case WHATSAPP = 'whatsapp';
    case INSTAGRAM = 'instagram';
    case AVITO = 'avito';
    case WEB = 'web';
    case SYSTEM = 'system';

    public static function tryFromCaseInsensitive(?string $value): ?self
    {
        if (null === $value) {
            return null;
        }
        $v = \strtolower(\trim($value));
        foreach (self::cases() as $case) {
            if ($case->value === $v) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::TELEGRAM => 'Telegram',
            self::WHATSAPP => 'WhatsApp',
            self::INSTAGRAM => 'Instagram',
            self::AVITO => 'Avito',
            self::WEB => 'Web-чат',
            self::SYSTEM => 'Система',
        };
    }
}
