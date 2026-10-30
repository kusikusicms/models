<?php

namespace KusikusiCMS\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use KusikusiCMS\Models\Builders\EntityBuilder;
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
use KusikusiCMS\Models\Observers\EntityObserver;
use KusikusiCMS\Models\Support\ContentsClass;
use KusikusiCMS\Models\Support\EntityCollection;
use KusikusiCMS\Models\Traits\UsesShortId;

/**
 * @property string $id
 * @property string|null $parent_entity_id
 * @property string|null $model
 * @property array<string, mixed>|\ArrayObject|null $props
 * @property-read Collection<int, EntityContent> $rawContents
 * @property-read Collection<int, EntityContent> $contentsSupport
 * @property bool|null $published
 * @property Carbon|null $publish_at
 * @property Carbon|null $unpublish_at
 * @property array<int, string>|null $langs
 * @property-read string $status
 *
 * @method static EntityBuilder query()
 * @method static EntityBuilder childrenOf(string $entityId, ?string $tag = null)
 * @method static EntityBuilder parentOf(string $entityId)
 * @method static EntityBuilder ancestorsOf(string $entityId, array $options = [])
 * @method static EntityBuilder descendantsOf(string $entityId, array|int $options = [])
 * @method static EntityBuilder siblingsOf(string $entityId, array $options = [])
 * @method static EntityBuilder rootOf(string $entityId)
 * @method static EntityBuilder relatedBy(string $entityId, array $options = [])
 * @method static EntityBuilder relating(string $entityId, array $options = [])
 * @method static EntityBuilder ofModel(string|array $model)
 * @method static EntityBuilder currentlyLive()
 * @method static EntityBuilder withContents(?array $options = null)
 * @method static EntityBuilder orderByContent(string $field, string $order = 'asc', ?string $lang = null)
 * @method static EntityBuilder whereContent(string $field, string $param2, ?string $param3 = null, ?string $param4 = null)
 * @method EntityBuilder newQuery()
 */
#[UseEloquentBuilder(EntityBuilder::class)]
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
     * Ensure all sub-classes inherit the observer
     */
    protected static function booted(): void
    {
        self::observe(EntityObserver::class);
    }

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

    /**
     * Static function to refresh the relations of ANCESTOR kind for the given Entity ID.
     * It also recreates children ANCESTOR relations recursively.
     */
    public static function refreshAncestorsRelationsById(string $entity_id): void
    {
        self::refreshAncestorRelationsOfEntity(Entity::findOrFail($entity_id));
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

                    return $this->contentsSupport->reduce(function ($carry, EntityContent $item) {
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
                    return $this->rawContents->reduce(function ($carry, EntityContent $item) {
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
                    return $this->rawContents->reduce(function ($carry, EntityContent $item) {
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
