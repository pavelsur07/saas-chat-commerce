<?php

namespace App\Tests\Tools;

trait EntityFactoryTrait
{
    /**
     * Создаёт экземпляр сущности БЕЗ вызова конструктора.
     * Удобно для тестов, когда конструктор жёстко типизирован (User/Owner и пр.).
     *
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \ReflectionException
     */
    protected function newEntity(string $class)
    {
        $ref = new \ReflectionClass($class);
        /** @var T $obj */
        $obj = $ref->newInstanceWithoutConstructor();

        return $obj;
    }

    /**
     * Насильно проставляет приватное/защищённое свойство сущности.
     */
    protected function setPriv(object $entity, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($entity);
        while (!$ref->hasProperty($prop) && $ref = $ref->getParentClass()) {
        }
        $p = new \ReflectionProperty($entity, $prop);
        $p->setAccessible(true);
        $p->setValue($entity, $value);
    }
}
