<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use KusikusiCMS\Models\Entity;

final class EntityTest extends TestCase
{
    /**
     * An Entity can be constructed.
     */
    public function testAnEntityCanBeConstructed(): void
    {
        $entity = new Entity();
        $this->assertInstanceOf(Entity::class, $entity);
    }
}
