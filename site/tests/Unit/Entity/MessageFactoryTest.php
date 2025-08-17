<?php

// tests/Unit/Entity/MessageFactoryTest.php

namespace App\Tests\Unit\Entity;

use App\Entity\Company\Company;
use App\Entity\Messaging\Client;
use App\Entity\Messaging\Message;
use App\Entity\Messaging\TelegramBot;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MessageFactoryTest extends TestCase
{
    public function testMessageInSetsDirectionCompanyBotAndText(): void
    {
        $company = $this->createMock(Company::class);
        $client = $this->createMock(Client::class);
        $client->method('getCompany')->willReturn($company);

        $bot = $this->createMock(TelegramBot::class);

        $id = Uuid::uuid4()->toString();
        $text = 'hello from tg';

        $m = Message::messageIn(
            id: $id, client: $client,
            telegramBot: $bot,
            text: $text
        );

        self::assertSame('in', $m->getDirection());
        self::assertSame($client, $m->getClient());
        self::assertSame($company, $m->getCompany());
        self::assertSame($bot, $m->getBot());
        self::assertSame($text, $m->getText());
        self::assertInstanceOf(\DateTimeInterface::class, $m->getCreatedAt());
    }
}
