# Events

`KusikusiCMS\Models\Entity` dispatches custom events via Eloquent's `$dispatchesEvents` map so host applications can listen to lifecycle changes using Laravel's event system.

## Dispatched events

The following events are mapped to model lifecycle hooks:
- `retrieved` → `KusikusiCMS\Models\Events\EntityRetrieved`
- `creating` → `KusikusiCMS\Models\Events\EntityCreating`
- `created` → `KusikusiCMS\Models\Events\EntityCreated`
- `updating` → `KusikusiCMS\Models\Events\EntityUpdating`
- `updated` → `KusikusiCMS\Models\Events\EntityUpdated`
- `saving` → `KusikusiCMS\Models\Events\EntitySaving`
- `saved` → `KusikusiCMS\Models\Events\EntitySaved`
- `deleting` → `KusikusiCMS\Models\Events\EntityDeleting`
- `deleted` → `KusikusiCMS\Models\Events\EntityDeleted`
- `trashed` → `KusikusiCMS\Models\Events\EntityTrashed`
- `forceDeleting` → `KusikusiCMS\Models\Events\EntityForceDeleting`
- `forceDeleted` → `KusikusiCMS\Models\Events\EntityForceDeleted`
- `restoring` → `KusikusiCMS\Models\Events\EntityRestoring`
- `restored` → `KusikusiCMS\Models\Events\EntityRestored`
- `replicating` → `KusikusiCMS\Models\Events\EntityReplicating`

## Listening in a host application

### Inline listener
```
use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Events\EntityCreated;

Event::listen(EntityCreated::class, function (EntityCreated $event) {
    // Your logic here
});
```

### EventServiceProvider
```
// app/Providers/EventServiceProvider.php
protected $listen = [
    \KusikusiCMS\Models\Events\EntityUpdated::class => [
        \App\Listeners\LogEntityUpdate::class,
    ],
];
```

### Testing event dispatch
```
use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityCreated;

Event::fake([EntityCreated::class]);

$entity = Entity::factory()->create();

Event::assertDispatched(EntityCreated::class);
```

> Note: Internal lifecycle logic (like maintaining ancestor relations) can be handled via observers. In this version, we keep observers optional; events are emitted for host apps regardless.