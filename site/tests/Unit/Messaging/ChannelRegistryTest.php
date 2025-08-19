<?php

namespace App\Tests\Unit\Messaging;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Channel\ChannelRegistry;
use PHPUnit\Framework\TestCase;

final class ChannelRegistryTest extends TestCase
{
    public function testNormalizeExists(): void
    {
        $r = new ChannelRegistry();

        $this->assertTrue($r->exists('telegram'));
        $this->assertTrue($r->exists('TeLeGrAm'));
        $this->assertFalse($r->exists('telegrm'));

        $this->assertSame(Channel::TELEGRAM, $r->normalize('TELEGRAM'));
        $this->assertSame('telegram', $r->normalize('telegram')->value);
    }

    public function testTryFromCaseInsensitive(): void
    {
        $this->assertSame(Channel::WHATSAPP, Channel::tryFromCaseInsensitive(' WhatsApp '));
        $this->assertNull(Channel::tryFromCaseInsensitive('unknown'));
    }
}
