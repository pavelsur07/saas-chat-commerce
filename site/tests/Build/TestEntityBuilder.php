<?php

namespace App\Tests\Build;

use ReflectionProperty;

abstract class TestEntityBuilder
{
    /** @template T @param class-string<T> $class @return T */
    protected function newEntity(string $class)
    {
        $ref = new \ReflectionClass($class);
        /** @var T $obj */
        $obj = $ref->newInstanceWithoutConstructor();

        return $obj;
    }

    /**
     * СТАВИТ ЗНАЧЕНИЕ «БЕЗОПАСНО»:
     * 1) Если есть ПУБЛИЧНЫЙ сеттер — вызовем его.
     * 2) Иначе — проставим поле напрямую через ReflectionProperty.
     * НИКОГДА не вызываем private/protected методы.
     */
    protected function setSafe(object $entity, string $prop, mixed $value): void
    {
        $setter = 'set'.ucfirst($prop);

        if (method_exists($entity, $setter)) {
            $rm = new \ReflectionMethod($entity, $setter);
            if ($rm->isPublic()) {
                $rm->invoke($entity, $value);

                return;
            }
            // если сеттер есть, но не public — не трогаем его
        }

        $this->setForcePriv($entity, $prop, $value);
    }

    /**
     * СТАВИТ ЗНАЧЕНИЕ «ЖЁСТКО» напрямую в приватное/защищённое поле,
     * если оно существует в иерархии класса.
     */
    protected function setForcePriv(object $entity, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($entity);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass();
        }
        if (!$ref) {
            return; // свойства нет — мягко выходим
        }
        $rp = new \ReflectionProperty($ref->getName(), $prop);
        $rp->setAccessible(true);
        $rp->setValue($entity, $value);
    }
}
