<?php

namespace App\Tests\Unit\Messaging;

use App\Entity\Messaging\Channel\Channel;
use App\Entity\Messaging\Client;
use PHPUnit\Framework\TestCase;

final class ClientChannelTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    private function newClient(): Client
    {
        // если у Client сложный конструктор — создаём без конструктора:
        $ref = new \ReflectionClass(Client::class);
        /** @var Client $c */
        $c = $ref->newInstanceWithoutConstructor();

        return $c;
    }

    /**
     * @throws \ReflectionException
     */
    public function testSetChannelEnumAndString(): void
    {
        $c = $this->newClient();
        $c->setChannel(Channel::INSTAGRAM);
        $this->assertSame(Channel::INSTAGRAM, $c->getChannel());

        $c->setChannel('telegram');
        $this->assertSame(Channel::TELEGRAM, $c->getChannel());
    }

    /**
     * @throws \ReflectionException
     */
    public function testRejectsUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $c = $this->newClient();
        $c->setChannel('telegrm');
    }
}
