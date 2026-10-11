<?php

namespace KusikusiCMS\Models\Tests\Unit;

use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Support\IdGenerator;
use KusikusiCMS\Models\Tests\TestCase;

class UsesShortIdTest extends TestCase
{
    public function test_generates_short_id_of_configured_length_on_create(): void
    {
        Config::set('kusikusicms.models.short_id_length', 12);
        $e = Entity::factory()->create();
        $this->assertIsString($e->id);
        $this->assertSame(12, strlen($e->id));
    }

    public function test_trims_manually_provided_id_to_configured_length(): void
    {
        Config::set('kusikusicms.models.short_id_length', 8);
        $manual = str_repeat('x', 64);
        $e = Entity::create(['id' => $manual, 'model' => 'Entity']);
        $this->assertSame(8, strlen($e->id));
        $this->assertSame(substr($manual, 0, 8), $e->id);
    }

    public function test_retries_on_collision_then_succeeds_with_unique_id(): void
    {
        Config::set('kusikusicms.models.short_id_length', 6);
        Config::set('kusikusicms.models.short_id_max_attempts', 5);

        $first = 'repeat1';
        $first = substr($first, 0, 6);
        $second = 'repeat1';
        $second = substr($second, 0, 6);
        $unique = 'uniqueZ';
        $unique = substr($unique, 0, 6);

        // Pre-create a model with the colliding ID to force collision on exists()
        Entity::create(['id' => $first, 'model' => 'Entity']);

        // Fake generator: returns $first, then $second (same), then $unique
        $sequence = [$first, $second, $unique];
        $fake = new class($sequence) implements IdGenerator
        {
            private array $seq;

            public function __construct(array $seq)
            {
                $this->seq = $seq;
            }

            public function generate(int $length): string
            {
                return array_shift($this->seq) ?? str_repeat('a', $length);
            }
        };
        $this->app->instance(IdGenerator::class, $fake);

        $e = Entity::factory()->create();
        $this->assertSame($unique, $e->id);
    }

    public function test_throws_after_max_attempts_when_all_generated_ids_collide(): void
    {
        Config::set('kusikusicms.models.short_id_length', 5);
        Config::set('kusikusicms.models.short_id_max_attempts', 3);

        // Make sure any generated ID exists to force collision each time
        // We'll prepare three existing rows with the same generated ID to simulate repeated collisions
        $collisionId = 'xxxxx';
        Entity::create(['id' => $collisionId, 'model' => 'Entity']);

        // Fake generator always returns the same colliding id
        $fake = new class($collisionId) implements IdGenerator
        {
            public function __construct(private string $id) {}

            public function generate(int $length): string
            {
                return substr($this->id, 0, $length);
            }
        };
        $this->app->instance(IdGenerator::class, $fake);

        $this->expectException(\RuntimeException::class);
        Entity::factory()->create();
    }
}
