<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api\WebChat;

use App\Controller\Api\WebChat\WebChatCorsTrait;
use PHPUnit\Framework\TestCase;

final class WebChatCorsTraitTest extends TestCase
{
    private function createTraitInstance(): object
    {
        return new class {
            use WebChatCorsTrait {
                isHostAllowed as public exposedIsHostAllowed;
            }
        };
    }

    public function testWildcardAllowsAnyHost(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('example.com', ['*']));
        self::assertTrue($trait->exposedIsHostAllowed('chat.2bstock.ru', ['*']));
    }

    public function testSubdomainsAllowedByWildcardRule(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('chat.2bstock.ru', ['*.2bstock.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('2bstock.ru', ['*.2bstock.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('deep.chat.2bstock.ru', ['*.2bstock.ru']));
    }

    public function testExactMatchesStillWork(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('chat.2bstock.ru', ['chat.2bstock.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('chat.2bstock.ru', ['https://chat.2bstock.ru']));
        self::assertFalse($trait->exposedIsHostAllowed('shop.2bstock.ru', ['chat.2bstock.ru']));
    }

    public function testWildcardDoesNotAllowUnrelatedHosts(): void
    {
        $trait = $this->createTraitInstance();

        self::assertFalse($trait->exposedIsHostAllowed('malicious.example', ['*.2bstock.ru']));
    }
}

