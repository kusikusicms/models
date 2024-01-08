<?php

namespace KusikusiCMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use KusikusiCMS\Models\Traits\UsesShortId;
use Illuminate\Database\Eloquent\Factories\Factory;
use KusikusiCMS\Models\Factories\EntityFactory;
use KusikusiCMS\Models\Events\{
    EntityCreating,
    EntityCreated,
    EntityRetrieved,
    EntityUpdating,
    EntityUpdated,
    EntitySaving,
    EntitySaved,
    EntityDeleting,
    EntityDeleted,
    EntityTrashed,
    EntityForceDeleting,
    EntityForceDeleted,
    EntityRestoring,
    EntityRestored,
    EntityReplicating
};


class Entity extends Model
{
    use UsesShortId, SoftDeletes, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities';

    /**
     * Create a new factory instance for the Entity model.
     */
    protected static function newFactory(): Factory
    {
        return EntityFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable
        = [
            'id',
            'model',
            'view',
            'langs',
            'properties',
            'status',
            'parent_entity_id',
            'created_at',
            'published_at',
            'unpublished_at'
        ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts
        = [
            'properties' => 'array',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'langs' => 'array'
        ];

    /**
     * The event map for the model. Some events are not used here, but they are defined so other packages can use them
     *
     * @var array
     */
    protected $dispatchesEvents
        = [
            'retrieved' => EntityRetrieved::class,
            'creating' => EntityCreating::class,
            'created' => EntityCreated::class,
            'updating' => EntityUpdating::class,
            'updated' => EntityUpdated::class,
            'saving' => EntitySaving::class,
            'saved' => EntitySaved::class,
            'deleting' => EntityDeleting::class,
            'deleted' => EntityDeleted::class,
            'trashed' => EntityTrashed::class,
            'forceDeleting' => EntityForceDeleting::class,
            'forceDeleted' => EntityForceDeleted::class,
            'restoring' => EntityRestoring::class,
            'restored' => EntityRestored::class,
            'replicating' => EntityReplicating::class,
        ];

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity ID.
     * It also recreates children ANCESTOR relations recursively.
     */
    public static function refreshAncestorsRelationsById(string $entity_id)
    {
        $entity = Entity::find($entity_id);
        if ($entity) {
            self::refreshAncestorsRelationsOfEntity($entity);
        }
    }

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity.
     * It also recreates children ANCESTOR relations recursively.
     */
    public static function refreshAncestorsRelationsOfEntity(Entity $entity)
    {
        // First clear all ancestors relations of the entity
        EntityRelation::query()
            ->where('caller_entity_id', $entity->id)
            ->where('kind', EntityRelation::RELATION_ANCESTOR)
            ->delete();
        // Now recreate all ancestors relations
        $currentAncestor = Entity::find($entity->parent_entity_id);
        $currentDepth = 1;
        while ($currentAncestor) {
            EntityRelation::create([
                "caller_entity_id" => $entity->id,
                "called_entity_id" => $currentAncestor->id,
                "kind" => EntityRelation::RELATION_ANCESTOR,
                "depth" => $currentDepth
            ]);
            $currentAncestor = Entity::find($currentAncestor->parent_entity_id);
            $currentDepth++;
        }
        // Descendants should be updated as well
        $children = Entity::where("parent_entity_id", $entity->id)->get();
        foreach ($children as $child) {
            self::refreshAncestorsRelationsOfEntity($child);
        }
    }

    /** Refresh the relations of ANCESTOR kind of the Entity
     */
    public function refreshAncestorsRelations(): void
    {
        self::refreshAncestorsRelationsOfEntity($this);
    }


    /**********
     * SCOPES *
     **********/

    /**
     * Scope a query to only include entities of a given modelId.
     *
     * @param  Builder $query
     * @param  string $model
     * @return Builder
     */
    public function scopeOfModel(Builder $query, string $model)
    {
        // TODO: Accept array of model ids
        return $query->where('model', $model);
    }

    /**
     * Scope a query to only include children of a given parent id.
     *
     * @param  Builder  $query
     * @param  string  $entity_id  The id of the parent entity
     * @param  string|null  $tag  Filter by one tag
     *
     * @return Builder
     * @throws Exception
     */
    public function scopeChildrenOf(
        Builder $query,
        string $entity_id,
        string|null $tag = null
    ): Builder {
        return $query->join('entities_relations as child',
            function ($join) use ($entity_id, $tag) {
                $join->on('child.caller_entity_id', '=', 'entities.id')
                    ->where('child.called_entity_id', '=', $entity_id)
                    ->where('child.depth', '=', 1)
                    ->where('child.kind', '=',
                        EntityRelation::RELATION_ANCESTOR)
                    ->when($tag, function ($q) use ($tag) {
                        return $q->whereJsonContains('child.tags', $tag);
                    });;
            })
            ->addSelect('id')
            ->addSelect('child.position as child.position')
            ->addSelect('child.tags as child.tags');
    }
}
