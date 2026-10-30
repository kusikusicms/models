<?php

namespace KusikusiCMS\Models\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use KusikusiCMS\Models\Entity;

/**
 * Query builder for the Entity model. Holds all the query filters (formerly model scopes).
 *
 * To override a filter, extend this class and return it from the `newEloquentBuilder()` method
 * of your Entity subclass (or register it with the `#[UseEloquentBuilder]` attribute).
 *
 * @extends Builder<Entity>
 */
class EntityBuilder extends Builder
{
    use Concerns\FiltersContents;
    use Concerns\FiltersHierarchy;
    use Concerns\FiltersRelations;

    /**
     * Only include entities that are currently live.
     *
     * Live means:
     * - published flag is true
     * - publish_at is not null and <= now
     * - unpublish_at is null OR > now
     */
    public function currentlyLive(): static
    {
        $now = Carbon::now()->toDateTimeString();

        return $this
            ->where('published', true)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('unpublish_at')
                    ->orWhere('unpublish_at', '>', $now);
            });
    }
}
