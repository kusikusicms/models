<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityCreated;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntityDeleted;
use KusikusiCMS\Models\Events\EntityDeleting;
use KusikusiCMS\Models\Events\EntityForceDeleted;
use KusikusiCMS\Models\Events\EntityForceDeleting;
use KusikusiCMS\Models\Events\EntityReplicating;
use KusikusiCMS\Models\Events\EntityRestored;
use KusikusiCMS\Models\Events\EntityRestoring;
use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Models\Events\EntityTrashed;
use KusikusiCMS\Models\Events\EntityUpdated;
use KusikusiCMS\Models\Events\EntityUpdating;
use KusikusiCMS\Models\Tests\TestCase;

class EntityEventsDispatchTest extends TestCase
{
    public function test_create_dispatches_creating_saving_created_saved(): void
    {
        Event::fake([
            EntityCreating::class,
            EntitySaving::class,
            EntityCreated::class,
            EntitySaved::class,
        ]);

        $e = Entity::factory()->create();

        Event::assertDispatched(EntityCreating::class);
        Event::assertDispatched(EntitySaving::class);
        Event::assertDispatched(EntityCreated::class);
        Event::assertDispatched(EntitySaved::class);
    }

    public function test_update_dispatches_updating_saving_updated_saved(): void
    {
        $e = Entity::factory()->create();
        Event::fake([
            EntityUpdating::class,
            EntitySaving::class,
            EntityUpdated::class,
            EntitySaved::class,
        ]);

        $e->update(['view' => 'custom']);

        Event::assertDispatched(EntityUpdating::class);
        Event::assertDispatched(EntitySaving::class);
        Event::assertDispatched(EntityUpdated::class);
        Event::assertDispatched(EntitySaved::class);
    }

    public function test_soft_delete_dispatches_deleting_deleted_trashed(): void
    {
        $e = Entity::factory()->create();
        Event::fake([
            EntityDeleting::class,
            EntityDeleted::class,
            EntityTrashed::class,
        ]);

        $e->delete();

        Event::assertDispatched(EntityDeleting::class);
        Event::assertDispatched(EntityDeleted::class);
        Event::assertDispatched(EntityTrashed::class);
    }

    public function test_restore_dispatches_restoring_restored(): void
    {
        $e = Entity::factory()->create();
        $e->delete();
        Event::fake([
            EntityRestoring::class,
            EntityRestored::class,
        ]);

        $e->restore();

        Event::assertDispatched(EntityRestoring::class);
        Event::assertDispatched(EntityRestored::class);
    }

    public function test_force_delete_dispatches_force_deleting_force_deleted(): void
    {
        $e = Entity::factory()->create();
        // need a trashed model to call forceDelete typically
        $e->delete();

        Event::fake([
            EntityForceDeleting::class,
            EntityForceDeleted::class,
        ]);

        $e->forceDelete();

        Event::assertDispatched(EntityForceDeleting::class);
        Event::assertDispatched(EntityForceDeleted::class);
    }

    public function test_replicate_dispatches_replicating(): void
    {
        $e = Entity::factory()->create();

        Event::fake([EntityReplicating::class]);

        $e->replicate();

        Event::assertDispatched(EntityReplicating::class);
    }
}
