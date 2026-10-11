<?php

namespace KusikusiCMS\Models\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use KusikusiCMS\Models\Entity;

class EntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Entity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model' => 'Entity',
            'view' => 'entity',
            // Let DB defaults handle `published` unless a state overrides it
            // 'published' => true,
        ];
    }

    /**
     * Draft: not published. no publish window.
     */
    public function draft(): self
    {
        return $this->state(function () {
            return [
                'published' => false,
                'publish_at' => null,
                'unpublish_at' => null,
            ];
        });
    }

    /**
     * Scheduled: publish date in the future.
     */
    public function scheduled(?Carbon $when = null): self
    {
        return $this->state(function () use ($when) {
            $publishAt = ($when ?? Carbon::now()->addDay())->toDateTimeString();

            return [
                'published' => true,
                'publish_at' => $publishAt,
                'unpublish_at' => null,
            ];
        });
    }

    /**
     * Live: currently within publish window.
     */
    public function live(?Carbon $since = null, ?Carbon $until = null): self
    {
        return $this->state(function () use ($since, $until) {
            $now = Carbon::now();
            $publishAt = ($since ?? $now->copy()->subDay())->toDateTimeString();
            $unpublishAt = $until?->toDateTimeString();

            return [
                'published' => true,
                'publish_at' => $publishAt,
                'unpublish_at' => $unpublishAt,
            ];
        });
    }

    /**
     * Expired: already unpublished (unpublish_at in the past).
     */
    public function expired(?Carbon $publishedSince = null, ?Carbon $unpublishedAt = null): self
    {
        return $this->state(function () use ($publishedSince, $unpublishedAt) {
            $now = Carbon::now();
            $publishAt = ($publishedSince ?? $now->copy()->subDays(10))->toDateTimeString();
            $unpublishAt = ($unpublishedAt ?? $now->copy()->subDay())->toDateTimeString();

            return [
                'published' => true,
                'publish_at' => $publishAt,
                'unpublish_at' => $unpublishAt,
            ];
        });
    }

    /**
     * Set the parent entity.
     */
    public function childOf(string $entity_id): self
    {
        return $this->state(function () use ($entity_id) {
            return [
                'parent_entity_id' => $entity_id,
            ];
        });
    }

    /**
     * Set the parent entity.
     */
    public function id(string $entity_id): self
    {
        return $this->state(function () use ($entity_id) {
            return [
                'id' => $entity_id,
            ];
        });
    }

    /**
     * Attach rawContents after creating using provided fields (uses default language from config).
     *
     * Example: Entity::factory()->withContents(['title' => 'Hello'])->create();
     */
    public function withContents(array $fields, ?string $lang = null): self
    {
        return $this->afterCreating(function (Entity $entity) use ($fields, $lang) {
            $entity->createContents($fields, $lang);
        });
    }
}
