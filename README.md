# KusikusiCMS Models (Laravel 13)

> **Read-only split.** This repository is automatically split from [`packages/models`](https://github.com/kusikusicms/monorepo/tree/main/packages/models) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/models`.

This package provides the core Eloquent models and helpers for KusikusiCMS on Laravel, including:
- `Entity` with short string IDs, lifecycle events, scopes, and content helpers
- `EntityContent`, `EntityRelation`, and `EntityArchive`
- Collections helpers for transforming related contents
- Factory states for realistic testing/seed data

Version: 13.x

## Installation

1) Require the package (when published):

```
composer require kusikusicms/models
```

2) Configuration is auto-merged. To publish the config file:

```
php artisan vendor:publish --tag=kusikusicms-config
```

This will create `config/kusikusicms/models.php`.

## Configuration

All keys live under the `kusikusicms.models.*` namespace:
- `short_id_length` (int, default 10) — length of generated IDs
- `short_id_max_attempts` (int, default 5) — retries before throwing on collision
- `default_language` (string, default `en`) — used by content helpers/scopes
- `store_versions` (bool, default true) — used by archives (reserved for future use)

See docs/configuration.md for details.

## Migrations

The service provider loads the package migrations automatically. They use timezone-aware timestamps (`timestampsTz`, `softDeletesTz`) and FK helpers.

Run migrations as usual:
```
php artisan migrate
```

## Quick start

### Create an entity
```PHP
use KusikusiCMS\Models\Entity;

$entity = Entity::create(['model' => 'Article']); // short ID assigned automatically
$entity = Entity::create(['id' => 'home', 'model' => 'Home']); // short ID given
$entity = Entity::create(['model' => 'Box', 'props' => ['width' => 23]]); // with props
```

### Attach contents records
```PHP
$entity->createContents([
  'title' => 'Hello',
  'body'  => 'World',
]); // defaults to config('kusikusicms.models.default_language')
```

### Query with content-aware scopes
- Load related contents with optional filters:
```PHP
Entity::query()->withContents(['lang' => 'es', 'fields' => ['title']])->get();
```
- Order by a content field (uses configured default language if omitted):
```PHP
Entity::query()->orderByContent('title', 'desc')->get();
```
- Filter by a content field:
```PHP
// Current behavior is characterized in tests; future version will compare `field =` and apply operator to `text` only.
Entity::query()->whereContent('title', 'like', 'Hello%')->get();
```

### Relations graph scopes
```PHP
Entity::query()->childrenOf($parentId)->get();
Entity::query()->parentOf($childId)->first();
Entity::query()->ancestorsOf($entityId)->get();
Entity::query()->descendantsOf($entityId)->get();
Entity::query()->siblingsOf($entityId)->get();
```

### Query builder

All the filters above are real, typed methods of `KusikusiCMS\Models\Builders\EntityBuilder`, which `Entity` registers with `#[UseEloquentBuilder]`. `Entity::query()` and static calls such as `Entity::childrenOf($id)` return it.

Methods: `currentlyLive`, `ofModel`, `childrenOf`, `parentOf`, `ancestorsOf`, `descendantsOf`, `siblingsOf`, `rootOf`, `relatedBy`, `relating`, `withContents`, `orderByContent`, `whereContent`.

The language used by `withContents()` is kept on the query, so `orderByContent()` and `whereContent()` reuse it for that query only.

#### Add a scope
A subclass can add its own scopes as usual; they chain after the builder methods and no custom builder is needed:
```PHP
class Page extends Entity
{
    public function scopePublishedBy(Builder $query, string $author): Builder
    {
        return $query->where('props->author', $author);
    }
}

Page::query()->childrenOf($id)->publishedBy('ana')->get();
```

#### Override a built-in filter
Extend `EntityBuilder` and register it on your subclass:
```PHP
class PageBuilder extends EntityBuilder
{
    public function childrenOf(string $entityId, ?string $tag = null): static
    {
        return parent::childrenOf($entityId, $tag)->where('model', 'page');
    }
}

#[UseEloquentBuilder(PageBuilder::class)]
class Page extends Entity {}

Page::childrenOf($id)->get(); // uses PageBuilder::childrenOf
```
You can also return it from `newEloquentBuilder($query)` instead of using the attribute.

Rules:
- A model-level `scopeChildrenOf()` on a subclass is ignored, because the builder method always wins.
- A subclass with its own builder must extend `EntityBuilder`.

#### Migration notes
The filters used to be `scopeXxx()` methods on `Entity`. Fluent calls are unchanged, but:
- Calling `scopeChildrenOf()` and the like directly no longer works; call the builder method instead.
- `hasNamedScope('childrenOf')` returns `false` and `->scopes(['childrenOf' => [...]])` is not supported; call the method directly.
- Custom builders returned by Entity subclasses must extend `EntityBuilder`.
- `descendantsOf()` is now typed (`array|int` as the second argument).
- If you use `barryvdh/laravel-ide-helper`, regenerate the helper file.

### Factory states
```PHP
Entity::factory()->draft()->create();
Entity::factory()->scheduled()->create();
Entity::factory()->live()->create();
Entity::factory()->expired()->create();

// With contents helper
Entity::factory()->live()->withContents(['title' => 'Hello'])->create();
```

### Events

`Entity` dispatches custom events via `$dispatchesEvents` so host apps can listen to them. Example:
```PHP
use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Events\EntityCreated;

Event::listen(EntityCreated::class, fn ($event) => logger('Entity created', ['id' => $event->entity->id]));
```

See docs/events.md for the full list.

### Collections helpers

When loading `contents`, use collection helpers to transform related rows into convenient arrays:
```PHP
$entities = Entity::withContents(['lang' => 'en'])->get();
$entities->flattenContentsByField();      // ['title' => 'Hello', 'body' => 'World']
$entities->groupContentsByField();        // ['title' => ['en' => 'Hello'], ...]
$entities->groupContentsByLang();         // ['en' => ['title' => 'Hello'], ...]
```

## Testing

- Package tests (Orchestra Testbench):
```shell
cd packages/models
../../vendor/bin/phpunit
```

## Extensibility

### Replace the ID generator
Bind your own generator to `KusikusiCMS\Models\Support\IdGenerator` in the host app container to control short ID generation.

See docs/id-generator.md for an example.

## Documentation
- docs/installation.md
- docs/configuration.md
- docs/models-and-scopes.md
- docs/events.md
- docs/factories-and-seeding.md
- docs/testing.md
- docs/id-generator.md
- docs/design-decisions.md

## License
MIT
