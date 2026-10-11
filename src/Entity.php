<?php

namespace KusikusiCMS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use KusikusiCMS\Models\Events\EntityCreated;
use KusikusiCMS\Models\Events\EntityCreating;
use KusikusiCMS\Models\Events\EntityDeleted;
use KusikusiCMS\Models\Events\EntityDeleting;
use KusikusiCMS\Models\Events\EntityForceDeleted;
use KusikusiCMS\Models\Events\EntityForceDeleting;
use KusikusiCMS\Models\Events\EntityReplicating;
use KusikusiCMS\Models\Events\EntityRestored;
use KusikusiCMS\Models\Events\EntityRestoring;
use KusikusiCMS\Models\Events\EntityRetrieved;
use KusikusiCMS\Models\Events\EntitySaved;
use KusikusiCMS\Models\Events\EntitySaving;
use KusikusiCMS\Models\Events\EntityTrashed;
use KusikusiCMS\Models\Events\EntityUpdated;
use KusikusiCMS\Models\Events\EntityUpdating;
use KusikusiCMS\Models\Factories\EntityFactory;
use KusikusiCMS\Models\Support\ContentsClass;
use KusikusiCMS\Models\Support\EntityCollection;
use KusikusiCMS\Models\Traits\UsesShortId;

class Entity extends Model
{
    use HasFactory, SoftDeletes, UsesShortId;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities';

    /**
     * Eloquent expects string IDs and non-incrementing for short IDs
     */
    public $incrementing = false;

    public $keyType = 'string';

    /**
     * Create a new factory instance for the Entity model.
     */
    protected static function newFactory(): Factory
    {
        return EntityFactory::new();
    }

    /**
     * The attributes that are guarded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'props' => 'array',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'langs' => 'array',
            'published' => 'boolean',
        ];
    }

    /**
     * status attribute
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $now = Carbon::now();
                // Use model accessors to ensure casts/defaults are applied even if $attributes is partial
                $published = $this->getAttribute('published');
                $publishAt = $this->getAttribute('publish_at');
                $unpublishAt = $this->getAttribute('unpublish_at');

                if ($published === null) {
                    return 'unknown';
                }
                if (! $published || $publishAt === null) {
                    return 'draft';
                }
                if ($publishAt instanceof Carbon ? $publishAt->greaterThan($now) : $publishAt > $now) {
                    return 'scheduled';
                }
                if ($unpublishAt !== null && ($unpublishAt instanceof Carbon ? $unpublishAt->lessThan($now) : $unpublishAt < $now)) {
                    return 'expired';
                }

                return 'live';
            },
        );
    }

    /**
     * Scope a query to only include entities that are currently live.
     *
     * Live means:
     * - live flag is true
     * - publish_at is not null and <= now
     * - unpublish_at is null OR > now
     */
    public function scopeCurrentlyLive(Builder $query): Builder
    {
        $now = Carbon::now()->toDateTimeString();

        return $query
            ->where('published', true)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('unpublish_at')
                    ->orWhere('unpublish_at', '>', $now);
            });
    }

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
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, Entity>  $models
     * @return EntityCollection<int, Entity>
     */
    public function newCollection(array $models = []): EntityCollection
    {
        return new EntityCollection($models);
    }

    /**************************************************************************
     *
     * HIERARCHY
     * Scopes and helper methods
     *
     **************************************************************************/

    /**
     * Scope a query to only include entities of a given modelId.
     *
     * @param  string  $model  The id of the model
     */
    public function scopeOfModel(Builder $query, string $model): Builder
    {
        // TODO: Accept array of model ids
        return $query->where('model', $model);
    }

    /**
     * Scope a query to only include children of a given parent id.
     *
     * @param  string  $entity_id  The id of the parent entity
     * @param  string|null  $tag  Filter by one tag
     */
    public function scopeChildrenOf(Builder $query, string $entity_id, ?string $tag = null): Builder
    {
        return $query->join('entities_relations as child', function ($join) use ($entity_id, $tag) {
            $join->on('child.caller_entity_id', '=', 'entities.id')
                ->where('child.called_entity_id', '=', $entity_id)
                ->where('child.depth', '=', 1)
                ->where('child.kind', '=', EntityRelation::RELATION_ANCESTOR)
                ->when($tag, function ($q) use ($tag) {
                    return $q->whereJsonContains('child.tags', $tag);
                });
        })
            ->addSelect('id')
            ->addSelect('child.position as child.position')
            ->addSelect('child.tags as child.tags');
    }

    /**
     * Scope a query to only include the parent of the given id.
     *
     * @param  string  $entity_id  The id of the parent entity
     */
    public function scopeParentOf(Builder $query, string $entity_id): Builder
    {
        return $query->join('entities_relations as parent', function ($join) use ($entity_id) {
            $join->on('parent.called_entity_id', '=', 'entities.id')
                ->where('parent.caller_entity_id', '=', $entity_id)
                ->where('parent.depth', '=', 1)
                ->where('parent.kind', '=', EntityRelation::RELATION_ANCESTOR);
        })
            ->addSelect('id')
            ->addSelect('parent.relation_id as parent.relation_id')
            ->addSelect('parent.position as parent.position')
            ->addSelect('parent.depth as parent.depth')
            ->addSelect('parent.tags as parent.tags');
    }

    /**
     * Scope a query to only include the ancestors of a given entity.
     * Returns the anscestors in order
     *
     * @param  number  $entity_id  The id of the entity
     *
     * @throws \Exception
     */
    /**
     * Scope a query to only include the ancestors of a given entity.
     *
     * Options (array) — second parameter:
     * - includeSelf (bool): Whether to include the referenced entity itself (the child/source) (default false).
     * - includeRelationMeta (bool): Whether to include ancestor.* meta columns in the select (default true).
     * - order (string): Controls order by hierarchy depth. Accepts 'ascending' | 'descending' | 'asc' | 'desc'.
     *                    Ascending means closest ancestor first (depth 1, then 2, ...). When includeSelf=true,
     *                    self is treated as depth 0 and appears first on ascending and last on descending. If not provided,
     *                    no specific ordering is applied (preserves previous behavior).
     */
    public function scopeAncestorsOf(Builder $query, string $entity_id, array $options = []): Builder
    {
        $includeSelf = (bool) ($options['includeSelf'] ?? false);
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options)
            ? (bool) $options['includeRelationMeta']
            : true;
        $orderOpt = isset($options['order']) ? strtolower((string) $options['order']) : null;
        $direction = 'desc';
        if ($orderOpt === 'ascending' || $orderOpt === 'asc') {
            $direction = 'asc';
        }

        // Use LEFT JOIN so we can optionally include the self row (the referenced entity) without a matching relation
        $query->leftJoin('entities_relations as ancestor', function ($join) use ($entity_id) {
            $join->on('ancestor.called_entity_id', '=', 'entities.id')
                ->where('ancestor.caller_entity_id', '=', $entity_id)
                ->where('ancestor.kind', '=', EntityRelation::RELATION_ANCESTOR);
        });

        // When includeSelf=false, require the join to exist. When true, allow the self row even without join
        if ($includeSelf) {
            $query->where(function ($q) use ($entity_id) {
                $q->whereNotNull('ancestor.relation_id')
                    ->orWhere('entities.id', '=', $entity_id);
            });
        } else {
            $query->whereNotNull('ancestor.relation_id');
        }

        // Always select the entity id
        $query->addSelect('id');

        // Optionally include relation meta columns
        if ($includeRelationMeta) {
            $query->addSelect('ancestor.relation_id as ancestor.relation_id')
                ->addSelect('ancestor.position as ancestor.position')
                ->addSelect('ancestor.depth as ancestor.depth')
                ->addSelect('ancestor.tags as ancestor.tags');
        }

        $query->orderByRaw(
            "CASE WHEN entities.id = ? THEN 0 ELSE ancestor.depth END $direction",
            [$entity_id]
        );

        return $query;
    }

    /**
     * Scope a query to only include descendants of a given entity id.
     *
     * @param  Builder  $query
     * @param  number  $entity_id  The id of the  entity
     * @return Builder
     *
     * @throws \Exception
     */
    /**
     * Scope a query to only include descendants of a given entity id.
     *
     * Options (array) — third parameter:
     * - maxDepth (int): Maximum depth to include (default 99). Replaces old $depth parameter.
     * - includeSelf (bool): Whether to include the ancestor entity itself in the result set (default false).
     * - includeRelationMeta (bool): Whether to include descendant.* meta columns in the select (default true).
     *
     * For backward compatibility, if the third parameter is an integer, it will be treated as ['maxDepth' => $thirdParam].
     */
    public function scopeDescendantsOf($query, $entity_id, $options = [])
    {
        // Backward compatibility: allow passing a numeric depth as the 3rd argument
        if (! is_array($options)) {
            $options = ['maxDepth' => $options];
        }

        $maxDepth = (int) ($options['maxDepth'] ?? 99);
        $includeSelf = (bool) ($options['includeSelf'] ?? false);
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options)
            ? (bool) $options['includeRelationMeta']
            : true;

        // Use LEFT JOIN so we can optionally include the ancestor row (self) without a matching relation
        $query->leftJoin('entities_relations as descendant', function ($join) use ($entity_id, $maxDepth) {
            $join->on('descendant.caller_entity_id', '=', 'entities.id')
                ->where('descendant.called_entity_id', '=', $entity_id)
                ->where('descendant.kind', '=', EntityRelation::RELATION_ANCESTOR)
                ->where('descendant.depth', '<=', $maxDepth);
        });

        // When includeSelf=false, require the join to exist. When true, allow the self row even without join
        if ($includeSelf) {
            $query->where(function ($q) use ($entity_id) {
                $q->whereNotNull('descendant.relation_id')
                    ->orWhere('entities.id', '=', $entity_id);
            });
        } else {
            $query->whereNotNull('descendant.relation_id');
        }

        // Always select the entity id and parent entity id
        $query->addSelect('id', 'parent_entity_id');

        // Optionally include relation meta columns
        if ($includeRelationMeta) {
            $query->addSelect('descendant.relation_id as descendant.relation_id')
                ->addSelect('descendant.position as descendant.position')
                ->addSelect('descendant.depth as descendant.depth')
                ->addSelect('descendant.tags as descendant.tags');
        }

        return $query;
    }

    /**
     * Scope a query to only include siblings of a given entity id.
     *
     * Siblings are entities that share the same parent_entity_id.
     *
     * Options (array) — second parameter:
     * - includeSelf (bool): Whether to include the referenced entity itself (default false).
     * - includeRelationMeta (bool): Whether to include sibling.* meta columns in the select (default true).
     *
     * @param  string  $entity_id  The id of the entity
     * @param  array  $options  Options array
     */
    public function scopeSiblingsOf(Builder $query, string $entity_id, array $options = []): Builder
    {
        $includeSelf = (bool) ($options['includeSelf'] ?? false);
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options)
            ? (bool) $options['includeRelationMeta']
            : true;

        // Subquery to get the parent_entity_id of the given entity
        $parentSubquery = Entity::query()
            ->select('parent_entity_id')
            ->where('id', '=', $entity_id)
            ->limit(1);

        // Find all entities with the same parent_entity_id
        $query->whereIn('entities.parent_entity_id', $parentSubquery)
            ->whereNotNull('entities.parent_entity_id');

        // Exclude self by default
        if (! $includeSelf) {
            $query->where('entities.id', '!=', $entity_id);
        }

        // LEFT JOIN with entities_relations to get sibling metadata (from child relation)
        $query->leftJoin('entities_relations as sibling', function ($join) use ($entity_id) {
            $join->on('sibling.caller_entity_id', '=', 'entities.id')
                ->where('sibling.called_entity_id', '=',
                    Entity::query()->select('parent_entity_id')->where('id', '=', $entity_id)->limit(1))
                ->where('sibling.depth', '=', 1)
                ->where('sibling.kind', '=', EntityRelation::RELATION_ANCESTOR);
        });

        // Always select the entity id
        $query->addSelect('id');

        // Optionally include relation meta columns
        if ($includeRelationMeta) {
            $query->addSelect('sibling.relation_id as sibling.relation_id')
                ->addSelect('sibling.position as sibling.position')
                ->addSelect('sibling.depth as sibling.depth')
                ->addSelect('sibling.tags as sibling.tags');
        }

        return $query;
    }

    /**
     * Scope a query to include only the furthest ancestor (root) of a given entity.
     *
     * Example:
     * Entity::query()->rootOf($id)->first();
     *
     * If the entity has no ancestors (is a root itself), the scope returns an empty set.
     */
    public function scopeRootOf(Builder $query, string $entity_id): Builder
    {
        // Join ancestors and take the one with maximum depth
        $query->join('entities_relations as root', function ($join) use ($entity_id) {
            $join->on('root.called_entity_id', '=', 'entities.id')
                ->where('root.caller_entity_id', '=', $entity_id)
                ->where('root.kind', '=', EntityRelation::RELATION_ANCESTOR);
        })
            ->addSelect('id')
            ->addSelect('root.relation_id as root.relation_id')
            ->addSelect('root.position as root.position')
            ->addSelect('root.depth as root.depth')
            ->addSelect('root.tags as root.tags')
            ->orderByDesc('root.depth')
            ->limit(1);

        return $query;
    }

    /**
     * Scope a query to only get entities being called by (outgoing relations from a source entity).
     *
     * Options (array):
     * - kind (string|array|null): Filter by one kind (string) or several kinds (array). When omitted/null, all kinds are included.
     * - exceptKind (string|array|null): Exclude one kind (string) or several kinds (array) from the results.
     * - tag (string|null): Filter by presence of a JSON tag on the relation.
     * - includeRelationMeta (bool): Whether to include relation meta columns in the select (default true). Aliases: related_by.*
     * - orderBy (string|array|null): Order by relation meta. Accepts strings like 'depth asc', 'depth desc', 'position asc', 'position desc',
     *                                or compact forms 'depth_asc', 'position_desc', or array ['column' => 'depth|position', 'direction' => 'asc|desc'].
     *
     * @param  string  $entity_id  The id of the entity calling the relations
     */
    public function scopeRelatedBy(Builder $query, string $entity_id, array $options = []): Builder
    {
        $kindOpt = $options['kind'] ?? null;
        $exceptKindOpt = $options['exceptKind'] ?? null;
        $tag = $options['tag'] ?? null;
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options) ? (bool) $options['includeRelationMeta'] : true;
        $orderBy = $options['orderBy'] ?? null;

        // Normalize kinds to arrays as needed
        $kinds = is_array($kindOpt) ? array_values($kindOpt) : ($kindOpt !== null ? [(string) $kindOpt] : null);
        $exceptKinds = is_array($exceptKindOpt) ? array_values($exceptKindOpt) : ($exceptKindOpt !== null ? [(string) $exceptKindOpt] : []);

        $query->join('entities_relations as related_by', function ($join) use ($entity_id, $kinds, $exceptKinds, $tag) {
            $join->on('related_by.called_entity_id', '=', 'entities.id')
                ->where('related_by.caller_entity_id', '=', $entity_id)
                ->when($tag, function ($q) use ($tag) {
                    return $q->whereJsonContains('related_by.tags', $tag);
                });
            if ($kinds !== null && count($kinds) > 0) {
                $join->whereIn('related_by.kind', $kinds);
            }
            if (! empty($exceptKinds)) {
                $join->whereNotIn('related_by.kind', $exceptKinds);
            }
        });

        // Always select the entity id
        $query->addSelect('id');

        if ($includeRelationMeta) {
            $query->addSelect('related_by.relation_id as related_by.relation_id')
                ->addSelect('related_by.kind as related_by.kind')
                ->addSelect('related_by.position as related_by.position')
                ->addSelect('related_by.depth as related_by.depth')
                ->addSelect('related_by.tags as related_by.tags');
        }

        // Optional ordering by relation meta
        if (! empty($orderBy)) {
            $parsed = ['column' => null, 'direction' => 'asc'];
            if (is_string($orderBy)) {
                $s = strtolower(trim($orderBy));
                $s = str_replace('_', ' ', $s);
                if (in_array($s, ['depth asc', 'depth desc', 'position asc', 'position desc'], true)) {
                    [$col, $dir] = explode(' ', $s);
                    $parsed['column'] = $col;
                    $parsed['direction'] = $dir;
                }
            } elseif (is_array($orderBy)) {
                $col = strtolower((string) ($orderBy['column'] ?? ''));
                $dir = strtolower((string) ($orderBy['direction'] ?? 'asc'));
                if (in_array($col, ['depth', 'position'], true)) {
                    $parsed['column'] = $col;
                }
                if (in_array($dir, ['asc', 'desc'], true)) {
                    $parsed['direction'] = $dir;
                }
            }
            if ($parsed['column']) {
                $query->orderBy('related_by.'.$parsed['column'], $parsed['direction']);
            }
        }

        return $query;
    }

    /**
     * Scope a query to only get entities calling (incoming relations to a target entity).
     *
     * Options (array):
     * - kind (string|array|null): Filter by one kind (string) or several kinds (array). When omitted/null, all kinds are included (default).
     * - exceptKind (string|array|null): Exclude one kind (string) or several kinds (array) from the results.
     * - tag (string|null): Filter by presence of a JSON tag on the relation.
     * - includeRelationMeta (bool): Whether to include relation meta columns in the select (default true). Aliases: relating.*
     * - orderBy (string|array|null): Order by relation meta; accepts same forms as in scopeRelatedBy: 'depth asc|desc', 'position asc|desc',
     *                                'depth_asc'/'position_desc', or array ['column' => 'depth|position', 'direction' => 'asc|desc'].
     *
     * @param  string  $entity_id  The id of the entity receiving the relations (target)
     *
     * @throws \Exception
     */
    public function scopeRelating(Builder $query, string $entity_id, array $options = []): Builder
    {
        $kindOpt = $options['kind'] ?? null;
        $exceptKindOpt = $options['exceptKind'] ?? null;
        $tag = $options['tag'] ?? null;
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options) ? (bool) $options['includeRelationMeta'] : true;
        $orderBy = $options['orderBy'] ?? null;

        $kinds = is_array($kindOpt) ? array_values($kindOpt) : ($kindOpt !== null ? [(string) $kindOpt] : null);
        $exceptKinds = is_array($exceptKindOpt) ? array_values($exceptKindOpt) : ($exceptKindOpt !== null ? [(string) $exceptKindOpt] : []);

        $query->join('entities_relations as relating', function ($join) use ($entity_id, $kinds, $exceptKinds, $tag) {
            $join->on('relating.caller_entity_id', '=', 'entities.id')
                ->where('relating.called_entity_id', '=', $entity_id)
                ->when($tag, function ($q) use ($tag) {
                    return $q->whereJsonContains('relating.tags', $tag);
                });
            if ($kinds !== null && count($kinds) > 0) {
                $join->whereIn('relating.kind', $kinds);
            }
            if (! empty($exceptKinds)) {
                $join->whereNotIn('relating.kind', $exceptKinds);
            }
        });

        // Always select the entity id
        $query->addSelect('id');

        if ($includeRelationMeta) {
            $query->addSelect('relating.relation_id as relating.relation_id')
                ->addSelect('relating.kind as relating.kind')
                ->addSelect('relating.position as relating.position')
                ->addSelect('relating.depth as relating.depth')
                ->addSelect('relating.tags as relating.tags');
        }

        if (! empty($orderBy)) {
            $parsed = ['column' => null, 'direction' => 'asc'];
            if (is_string($orderBy)) {
                $s = strtolower(trim($orderBy));
                $s = str_replace('_', ' ', $s);
                if (in_array($s, ['depth asc', 'depth desc', 'position asc', 'position desc'], true)) {
                    [$col, $dir] = explode(' ', $s);
                    $parsed['column'] = $col;
                    $parsed['direction'] = $dir;
                }
            } elseif (is_array($orderBy)) {
                $col = strtolower((string) ($orderBy['column'] ?? ''));
                $dir = strtolower((string) ($orderBy['direction'] ?? 'asc'));
                if (in_array($col, ['depth', 'position'], true)) {
                    $parsed['column'] = $col;
                }
                if (in_array($dir, ['asc', 'desc'], true)) {
                    $parsed['direction'] = $dir;
                }
            }
            if ($parsed['column']) {
                $query->orderBy('relating.'.$parsed['column'], $parsed['direction']);
            }
        }

        return $query;
    }

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity ID.
     * It also recreates children ANCESTOR relations recursively.
     */
    public static function refreshAncestorsRelationsById(string $entity_id): void
    {
        $entity = Entity::findOrFail($entity_id);
        if ($entity) {
            self::refreshAncestorRelationsOfEntity($entity);
        }
    }

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity.
     * It also recreates children's ANCESTOR relations recursively.
     */
    public static function refreshAncestorRelationsOfEntity(Entity $entity): void
    {
        // First, clear all ancestor relations of the entity
        EntityRelation::query()
            ->where('caller_entity_id', $entity->id)
            ->where('kind', EntityRelation::RELATION_ANCESTOR)
            ->delete();
        // Now recreate all ancestor relations
        $currentAncestor = Entity::find($entity->parent_entity_id);
        $currentDepth = 1;
        while ($currentAncestor) {
            EntityRelation::create([
                'caller_entity_id' => $entity->id,
                'called_entity_id' => $currentAncestor->id,
                'kind' => EntityRelation::RELATION_ANCESTOR,
                'depth' => $currentDepth,
            ]);
            $currentAncestor = Entity::find($currentAncestor->parent_entity_id);
            $currentDepth++;
        }
        // Descendants should be updated as well
        $children = Entity::where('parent_entity_id', $entity->id)->get();
        foreach ($children as $child) {
            self::refreshAncestorRelationsOfEntity($child);
        }
    }

    /** Refresh the relations of ANCESTOR kind of the Entity
     */
    public function refreshAncestorsRelations(): void
    {
        self::refreshAncestorRelationsOfEntity($this);
    }

    /***************************************************************************
     *
     * CONTENTS
     * Scopes and attributes
     *
     * *************************************************************************
     */

    /**
     * The rawContents relationship, used for a standard way to access the contents of an entity.
     */
    public function rawContents(): HasMany
    {
        return $this->hasMany(EntityContent::class, 'entity_id', 'id');
    }

    /**
     * Other contents relationships for special ways to access the contents of an entity.
     */
    public function contentsSupport(): HasMany
    {
        return $this->hasMany(EntityContent::class, 'entity_id', 'id');
    }

    /**
     * Scope to include contents relation, filtered by options in a dev frienldy format.
     *
     * Options:
     * - 'lang'   => string|null Language code to filter by. When null, no lang filter is applied.
     * - 'fields' => string|array|null One field or list of fields to include.
     */
    public function scopeWithContents(Builder $query, ?array $options = null): Builder
    {
        $lang = $options['lang'] ?? Config::get('kusikusicms.models.default_language', 'en');
        $this->lastUsedLang = $lang;
        $fields = $options['fields'] ?? null;

        return $query->with(['contentsSupport' => function ($q) use ($lang, $fields) {
            $q->when($lang !== null, function ($q) use ($lang) {
                return $q->where('lang', $lang);
            });
            $q->when($fields !== null, function ($q) use ($fields) {
                if (is_array($fields)) {
                    return $q->whereIn('field', $fields);
                }
                if (is_string($fields)) {
                    return $q->where('field', $fields);
                }
            });
        }]);
    }

    private $lastUsedLang;

    /**
     * Attribute to return the contents of the entity in a dev-friendly format.
     */
    protected function contents(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (! $this->relationLoaded('contentsSupport')) {
                    return new ContentsClass;
                } else {
                    $this->makeHidden('contentsSupport');

                    return $this->contentsSupport->reduce(function ($carry, $item) {
                        $carry->{$item->field} = $item->text;

                        return $carry;
                    }, new ContentsClass);
                }
            }
        );
    }

    /**
     * Always include the contents accessor
     */
    protected $appends = ['contents'];

    /**
     * Attribute to return the contents of the entity grouped by field.
     */
    protected function contentsByField(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (! $this->relationLoaded('rawContents')) {
                    return [];
                } else {
                    return $this->rawContents->reduce(function ($carry, $item) {
                        if (! isset($carry[$item->field])) {
                            $carry[$item->field] = [];
                        }
                        $carry[$item->field][$item->lang] = $item->text;

                        return $carry;
                    }, []);
                }
            }
        );
    }

    /**
     * Attribute to return the contents of the entity grouped by lang.
     */
    protected function contentsByLang(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (! $this->relationLoaded('rawContents')) {
                    return [];
                } else {
                    return $this->rawContents->reduce(function ($carry, $item) {
                        if (! isset($carry[$item->lang])) {
                            $carry[$item->lang] = [];
                        }
                        $carry[$item->lang][$item->field] = $item->text;

                        return $carry;
                    }, []);
                }
            }
        );
    }

    /**
     * Scope to order the result by a content field.
     */
    public function scopeOrderByContent(Builder $query, string $field, string $order = 'asc', ?string $lang = null): Builder
    {
        if (! $lang) {
            $lang = $this->lastUsedLang ?? Config::get('kusikusicms.models.default_language', 'en');
        }

        return $query->leftJoin("entities_contents as content_order_{$lang}_{$field}", function ($join) use ($field, $lang) {
            $join->on("content_order_{$lang}_{$field}.entity_id", 'entities.id')
                ->where("content_order_{$lang}_{$field}.field", $field)
                ->where("content_order_{$lang}_{$field}.lang", $lang);
        })
            ->orderBy("content_order_{$lang}_{$field}.text", $order);
    }

    /**
     * Scope to filter by a content field.
     */
    public function scopeWhereContent(Builder $query, string $field, string $param2, ?string $param3 = null, ?string $param4 = null): Builder
    {
        // Determine overload: operator form or value form
        $opList = ['=', '!=', '<>', 'like', 'not like', 'ilike', 'rlike'];
        if (in_array(strtolower($param2), $opList, true)) {
            $operator = strtolower($param2);
            $value = (string) ($param3 ?? '');
            $lang = $param4;
        } else {
            $operator = '=';
            $value = (string) $param2;
            $lang = $param3;
        }

        if ($lang === null) {
            $lang = $this->lastUsedLang ?? Config::get('kusikusicms.models.default_language', 'en');
        }

        // Normalize LIKE wildcards if caller didn't pass any
        if (in_array($operator, ['like', 'not like', 'ilike'], true) && ! str_contains($value, '%')) {
            $value = "%{$value}%";
        }

        // Use whereHas on relation to avoid alias collisions and ensure field equality
        return $query->whereHas('contentsSupport', function ($q) use ($field, $operator, $value, $lang) {
            $q->where('field', '=', $field)
                ->when($lang !== '', fn ($q2) => $q2->where('lang', $lang))
                ->where('text', $operator, $value);
        });
    }

    /************************************************************************
     *
     * OTHER RELATIONS
     *
     ***********************************************************************/

    /**
     * The generic relations relationship
     */
    public function relations(): HasMany
    {
        return $this
            ->hasMany(EntityRelation::class, 'caller_entity_id', 'id');
    }

    /****************
     * Methods
     ***************/

    /**
     * Create rawContents for the current Entity.
     *
     * @param  array  $fieldsAndValues  An associative array of fields and their value
     * @param  string|null  $language  The id of the language of the rawContents
     *
     * @throws \Exception
     */
    public function createContents(array $fieldsAndValues, ?string $language = null): int
    {
        return EntityContent::upsert(Arr::map($fieldsAndValues, function (string $value, string $key) use ($language) {
            return [
                'entity_id' => $this->id,
                'field' => $key,
                'text' => $value,
                'lang' => $language ?? Config::get('kusikusicms.models.default_language', 'en'),
            ];
        }), uniqueBy: ['entity_id', 'field', 'lang'], update: ['text']);
    }

    /**
     * Get a specific key of the entity props field, using dot notation
     *
     * @param  string  $key  The key of the prop
     * @param  mixed  $default  The default value if the prop is not set
     */
    public function getProp(string $key, mixed $default = null): mixed
    {
        $key = Str::replace('->', '.', $key);

        return Arr::get($this->props, $key, $default);
    }

    /**
     * Set a specific key of the entity props field, using dot notation
     *
     * @param  string  $key  The key of the prop
     * @param  mixed  $value  The value of the prop
     */
    public function setProp(string $key, mixed $value): Entity
    {
        $key = Str::replace('->', '.', $key);
        $props = $this->props;
        Arr::set($props, $key, $value);
        $this->props = $props;

        return $this;
    }
}
