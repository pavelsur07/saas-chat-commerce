<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messaging;

use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use PHPUnit\Framework\TestCase;

final class ClientEntityTest extends TestCase
{
    public function testClientHasCompanyAndExternalId(): void
    {
        // Компания с владельцем (владелец обязателен по доменной логике)
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();

        // Клиент
        $externalId = 'ext_'.random_int(10000, 99999);
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId($externalId)
            ->build();

        // Проверяем через геттеры если есть, иначе — через Reflection (без изменения кода сущностей)
        $actualCompany = $this->getProp($client, 'company');
        $actualExternalId = $this->getProp($client, 'externalId');

        self::assertNotNull($actualCompany, 'У клиента должна быть компания');
        self::assertSame($company, $actualCompany, 'Клиент должен ссылаться на ту же компанию');
        self::assertSame($externalId, $actualExternalId, 'externalId клиента должен совпадать с установленным');
    }

    /**
     * Универсальное чтение свойства: сперва пытаемся вызвать getXxx(),
     * иначе читаем приватное поле через Reflection.
     */
    private function getProp(object $entity, string $prop): mixed
    {
        $getter = 'get'.ucfirst($prop);
        if (method_exists($entity, $getter)) {
            return $entity->{$getter}();
        }

        $ref = new \ReflectionClass($entity);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass();
        }
        if (!$ref) {
            return null;
        }
        $rp = new \ReflectionProperty($ref->getName(), $prop);
        $rp->setAccessible(true);

        return $rp->getValue($entity);
    }
}
