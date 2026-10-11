<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Carbon\Carbon;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Tests\TestCase;

class EntityFactoryStatesTest extends TestCase
{
    public function test_draft_state(): void
    {
        $e = Entity::factory()->draft()->create();
        $this->assertFalse($e->published);
        $this->assertSame('draft', $e->status);
    }

    public function test_scheduled_state(): void
    {
        $future = Carbon::now()->addDays(2);
        $e = Entity::factory()->scheduled($future)->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->publish_at);
        $this->assertTrue($e->publish_at->greaterThan(Carbon::now()));
        $this->assertNull($e->unpublish_at);
        $this->assertSame('scheduled', $e->status);
    }

    public function test_live_state(): void
    {
        $since = Carbon::now()->subDay();
        $e = Entity::factory()->live($since)->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->publish_at);
        $this->assertTrue($e->publish_at->lessThanOrEqualTo(Carbon::now()));
        $this->assertSame('live', $e->status);
    }

    public function test_expired_state(): void
    {
        $e = Entity::factory()->expired()->create();
        $this->assertTrue($e->published);
        $this->assertNotNull($e->unpublish_at);
        $this->assertTrue($e->unpublish_at->lessThan(Carbon::now()));
        $this->assertSame('expired', $e->status);
    }

    public function test_scope_is_live_returns_only_live_entities(): void
    {
        // Create a mix of entities in different states
        $draft = Entity::factory()->draft()->create();
        $scheduled = Entity::factory()->scheduled(Carbon::now()->addDay())->create();
        $expired = Entity::factory()->expired()->create();
        $live1 = Entity::factory()->live(Carbon::now()->subDay())->create();
        $live2 = Entity::factory()->live(Carbon::now()->subMinutes(5))->create();

        $live = Entity::query()->currentlyLive()->pluck('id')->all();

        $this->assertContains($live1->id, $live);
        $this->assertContains($live2->id, $live);
        $this->assertNotContains($draft->id, $live);
        $this->assertNotContains($scheduled->id, $live);
        $this->assertNotContains($expired->id, $live);
        $this->assertCount(2, $live);
    }

    public function test_with_contents_helper(): void
    {
        $e = Entity::factory()->draft()->withContents(['title' => 'Hello'])->create();
        $this->assertDatabaseHas('entities_contents', [
            'entity_id' => $e->id,
            'field' => 'title',
            'text' => 'Hello',
        ]);
    }
}
