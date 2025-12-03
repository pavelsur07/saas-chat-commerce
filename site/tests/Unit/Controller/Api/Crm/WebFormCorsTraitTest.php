<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api\Crm;

use App\Controller\Api\Crm\WebFormCorsTrait;
use PHPUnit\Framework\TestCase;

final class WebFormCorsTraitTest extends TestCase
{
    private function createTraitInstance(): object
    {
        return new class {
            use WebFormCorsTrait {
                isHostAllowed as public exposedIsHostAllowed;
            }
        };
    }

    public function testWildcardAllowsAnyHost(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('example.com', ['*']));
        self::assertTrue($trait->exposedIsHostAllowed('app.conwix.ru', ['*']));
    }

    public function testSubdomainsAllowedByWildcardRule(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('app.conwix.ru', ['*.conwix.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('conwix.ru', ['*.conwix.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('deep.app.conwix.ru', ['*.conwix.ru']));
    }

    public function testExactMatchesStillWork(): void
    {
        $trait = $this->createTraitInstance();

        self::assertTrue($trait->exposedIsHostAllowed('app.conwix.ru', ['app.conwix.ru']));
        self::assertTrue($trait->exposedIsHostAllowed('app.conwix.ru', ['https://app.conwix.ru']));
        self::assertFalse($trait->exposedIsHostAllowed('shop.conwix.ru', ['app.conwix.ru']));
    }

    public function testWildcardDoesNotAllowUnrelatedHosts(): void
    {
        $trait = $this->createTraitInstance();

        self::assertFalse($trait->exposedIsHostAllowed('malicious.example', ['*.conwix.ru']));
    }
}

