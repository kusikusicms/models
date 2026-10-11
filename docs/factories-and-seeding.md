# Factories & Seeding

The package includes a class-based factory for `Entity` with expressive states, as well as a demo seeder.

## Factory: `KusikusiCMS\Models\Factories\EntityFactory`

### States
- `draft()` — `published=false`, clears `publish_at`/`unpublish_at`.
- `scheduled(?Carbon $when = null)` — `published=true`, `publish_at` in the future.
- `live(?Carbon $since = null, ?Carbon $until = null)` — currently visible (within window).
- `expired(?Carbon $publishedSince = null, ?Carbon $unpublishedAt = null)` — `unpublish_at` in the past.

### Helpers
- `withContents(array $fields, ?string $lang = null)` — attaches contents after creation.

#### Examples
```
use KusikusiCMS\Models\Entity;

Entity::factory()->draft()->create();
Entity::factory()->scheduled()->create();
Entity::factory()->live()->create();
Entity::factory()->expired()->create();

Entity::factory()->live()->withContents(['title' => 'Hello'])->create();
```

## Seeder: `KusikusiCMS\Models\Database\Seeders\ModelsSeeder`

Seeds four entities across the main states with sample contents.

### Running the seeder (in a host app)
Register the seeder in your app's `DatabaseSeeder`:
```
$this->call(\KusikusiCMS\Models\Database\Seeders\ModelsSeeder::class);
```

Or run directly:
```
php artisan db:seed --class="KusikusiCMS\\Models\\Database\\Seeders\\ModelsSeeder"
```
