<?php

namespace App\Tests\Build;

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

    /** Сеттер: если есть публичный сеттер — используем его, иначе проставляем приватно (если свойство существует). */
    protected function set(object $entity, string $prop, mixed $value): void
    {
        $setter = 'set'.ucfirst($prop);
        if (method_exists($entity, $setter)) {
            $entity->{$setter}($value);

            return;
        }

        $ref = new \ReflectionClass($entity);
        while ($ref && !$ref->hasProperty($prop)) {
            $ref = $ref->getParentClass();
        }
        if (!$ref) {
            return; // защищаемся от отсутствующих полей
        }
        $rp = new \ReflectionProperty($ref->getName(), $prop);
        $rp->setAccessible(true);
        $rp->setValue($entity, $value);
    }
}
