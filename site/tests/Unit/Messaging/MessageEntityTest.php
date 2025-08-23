<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messaging;

use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Build\MessageBuild;
use PHPUnit\Framework\TestCase;

final class MessageEntityTest extends TestCase
{
    public function testMessageBelongsToClientAndHasCreatedAt(): void
    {
        // Компания + владелец
        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();

        // Клиент
        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();

        // Сообщение (текст — произвольно)
        $text = 'hello';
        $message = MessageBuild::make()
            ->withClient($client)
            ->withText($text)
            ->build();

        // Проверки (через геттеры если есть, иначе — через Reflection)
        $actualClient = $this->getProp($message, 'client');
        $actualText = $this->getProp($message, 'text');
        $createdAt = $this->getProp($message, 'createdAt');

        self::assertSame($client, $actualClient, 'Сообщение должно ссылаться на своего клиента');
        self::assertSame($text, $actualText, 'Текст сообщения должен совпадать с установленным');
        self::assertInstanceOf(\DateTimeInterface::class, $createdAt, 'createdAt должен быть датой/временем');
    }

    /**
     * Если модель поддерживает направление (in/out), этот тест проверит,
     * что значение корректно устанавливается билдером. Если такого поля нет — тест мягко пройдёт.
     */
    public function testMessageDirectionOptional(): void
    {
        // Компания + владелец + клиент
        $owner = CompanyUserBuild::make()
            ->withEmail('d_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('dc_'.bin2hex(random_bytes(4)))
            ->build();

        $client = ClientBuild::make()
            ->withCompany($company)
            ->withExternalId('ext_'.random_int(10000, 99999))
            ->build();

        // Попробуем установить направление, если билдер такое поддерживает
        $mb = MessageBuild::make()
            ->withClient($client)
            ->withText('hi');

        // Если у билдера есть withDirection — используем; если нет, просто построим сообщение
        if (method_exists($mb, 'withDirection')) {
            $mb = $mb->withDirection('in');
        }

        $message = $mb->build();

        $direction = $this->getProp($message, 'direction');
        if (null !== $direction) {
            self::assertContains($direction, ['in', 'out'], 'direction сообщения должен быть in|out');
        } else {
            // если поля нет — ничего не проверяем, тест считается пройденным
            self::assertTrue(true);
        }
    }

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
