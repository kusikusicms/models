<?php

namespace KusikusiCMS\Models\Tests\Unit;

use Carbon\Carbon;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Tests\TestCase;

class EntityStatusAccessorTest extends TestCase
{
    public function test_returns_unknown_when_published_not_set(): void
    {
        $e = new Entity;
        $this->assertSame('unknown', $e->status);
    }

    public function test_returns_draft_when_published_false(): void
    {
        $e = Entity::factory()->draft()->create();
        $this->assertSame('draft', $e->status);
    }

    public function test_returns_scheduled_when_publish_at_in_future(): void
    {
        // future
        $e1 = Entity::factory()->scheduled(Carbon::now()->addDay())->create();
        $this->assertSame('scheduled', $e1->status);
    }

    public function test_returns_expired_when_unpublish_at_in_past(): void
    {
        $e = Entity::factory()->expired()->create();
        $this->assertSame('expired', $e->status);
    }

    public function test_returns_live_when_now_within_window(): void
    {
        $e = Entity::factory()->live()->create();
        $this->assertSame('live', $e->status);
    }
}
