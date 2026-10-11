# Testing

This package supports two complementary test setups:

1) Package-level tests using Orchestra Testbench (isolated from any host app)
2) Host app integration tests under `apps/sandbox` (real Laravel application)

## 1) Package tests (Testbench)

From the repository root:
```
cd packages/models
../../vendor/bin/phpunit
```

What this does:
- Boots a minimal Laravel kernel via Orchestra Testbench
- Registers the package service providers
- Loads package migrations from `database/migrations`
- Uses SQLite in-memory by default (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)

## 2) Host app integration tests (apps/sandbox)

From the repository root:
```
cd apps/sandbox
php artisan test
```

What this does:
- Boots a full Laravel 13 application
- Registers package providers (directly or via discovery)
- Verifies end-to-end behaviors: config merge, migrations, factories, events, and scopes

## Event testing example
```
use Illuminate\Support\Facades\Event;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Events\EntityCreated;

Event::fake([EntityCreated::class]);

$entity = Entity::factory()->create();

Event::assertDispatched(EntityCreated::class);
```

## Notes
- Both suites use SQLite in-memory by default for speed and isolation.
- Keep both suites green before submitting changes (see the root CONTRIBUTING.md).
- Characterization tests document current behavior for some scopes (e.g., `whereContent`); future changes will flip those specs to active tests.