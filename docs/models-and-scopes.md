# Models and Scopes

This package includes the following primary models:
- `KusikusiCMS\Models\Entity` — main content node. Uses short string IDs and soft deletes.
- `KusikusiCMS\Models\EntityContent` — localized field/value rows for an entity.
- `KusikusiCMS\Models\EntityRelation` — graph relations between entities (ancestor/child, etc.).
- `KusikusiCMS\Models\EntityArchive` — stores versions/archives (reserved for future use).

## Entity status accessor
`$entity->status` returns one of: `unknown`, `draft`, `scheduled`, `expired`, `live` based on `published`, `publish_at`, `unpublish_at`.

Example:
```
$e = Entity::factory()->live()->create();
echo $e->status; // "live"
```

## Content helpers
### `Entity::createContents(array $fields, ?string $lang = null): int`
Upserts multiple fields for the entity in the given (or default) language.

```
$e->createContents(['title' => 'Hello', 'body' => 'World'], 'en');
```

## Scopes (Entity)

### `ofModel(string $model): Builder`
Filter by model ID string.
```
Entity::query()->ofModel('Article')->get();
```

### `withContents(?array $options = null): Builder`
Eager load the `contents` relation optionally filtered by language and field(s).
Options keys: `lang` (string|null) and `fields` (string|array|null).
```
Entity::query()->withContents(['lang' => 'es', 'fields' => ['title', 'summary']])->get();
```

### `orderByContent(string $field, string $order = 'asc', ?string $lang = null): Builder`
Join and order by the content `text` for a field. Uses `config('kusikusicms.models.default_language')` when `$lang` is omitted.
```
Entity::query()->orderByContent('title', 'desc')->get();
```

### `whereContent(string $field, string $param2, ?string $param3 = null, ?string $param4 = null): Builder`
Filter by content using field equality and an operator applied to the `text` column. Two overloads are supported:
- Overload A: `whereContent($field, $value, ?$lang = null)` → compares `field = :field` and `text = :value`.
- Overload B: `whereContent($field, $operator, $value, ?$lang = null)` → compares `field = :field` and `text $operator :value`.

Rules:
- Language resolution: `$lang === null` uses `config('kusikusicms.models.default_language')`; `$lang === ''` searches across any language.
- Operators: `=`, `!=`, `<>`, `like`, `not like`, `ilike`, `rlike` (driver support varies).
- LIKE convenience: if you use `like`/`not like` without `%`, the value is wrapped as `%value%` automatically.

Examples:
```
// Equals (default operator)
Entity::query()->whereContent('title', 'Hello')->get();

// LIKE (starts with)
Entity::query()->whereContent('title', 'like', 'Hello%')->get();

// Any language
Entity::query()->whereContent('title', 'like', 'Hola%', '')->get();
```

## Relation graph scopes

### `childrenOf(string $entityId, ?string $tag = null): Builder`
Direct children (depth=1) of a given parent. Current code exposes dot-style SQL aliases in attributes (e.g., `child.position`).
```
Entity::query()->childrenOf($parentId)->get();
```

### `parentOf(string $entityId): Builder`
Direct parent of a given entity. Exposes `parent.*` dot-style aliases.
```
Entity::query()->parentOf($childId)->first();
```

### `ancestorsOf(string $entityId, array $options = []): Builder`
All ancestors (any depth). Exposes `ancestor.*` dot-style aliases.

Options:
- `includeSelf` (bool, default false): Include the entity itself in results
- `includeRelationMeta` (bool, default true): Include `ancestor.*` metadata columns
- `order` (string): Order by hierarchy depth ('ascending' | 'descending' | 'asc' | 'desc')

```
Entity::query()->ancestorsOf($id)->get();
Entity::query()->ancestorsOf($id, ['includeSelf' => true, 'order' => 'asc'])->get();
```

### `descendantsOf(string $entityId, array $options = []): Builder`
All descendants of a given entity. Exposes `descendant.*` dot-style aliases.

Options:
- `maxDepth` (int, default 99): Maximum depth to include
- `includeSelf` (bool, default false): Include the ancestor entity itself in results
- `includeRelationMeta` (bool, default true): Include `descendant.*` metadata columns

```
Entity::query()->descendantsOf($id)->get();
Entity::query()->descendantsOf($id, ['maxDepth' => 2, 'includeSelf' => true])->get();
```

### `siblingsOf(string $entityId, array $options = []): Builder`
All siblings (entities sharing the same parent) of a given entity. Exposes `sibling.*` dot-style aliases.

Siblings are entities that share the same `parent_entity_id`. Returns an empty collection if the entity has no parent or no siblings.

Options:
- `includeSelf` (bool, default false): Include the entity itself in results
- `includeRelationMeta` (bool, default true): Include `sibling.*` metadata columns

```
Entity::query()->siblingsOf($id)->get();
Entity::query()->siblingsOf($id, ['includeSelf' => true])->get();
Entity::query()->siblingsOf($id, ['includeRelationMeta' => false])->get();
```

### `relatedBy(string $entityId, array $options = []): Builder`
Entities that the given entity calls (outgoing relations). Exposes `related_by.*` dot-style aliases.

Options:
- `kind` (string|array|null): Filter by one kind (string) or several kinds (array). When omitted/null, all kinds are included.
- `exceptKind` (string|array|null): Exclude one kind (string) or several kinds (array) from the results.
- `tag` (string|null): Filter by a JSON tag present in the relation `tags` column.
- `includeRelationMeta` (bool, default true): Include `related_by.*` metadata columns in the select.
- `orderBy` (string|array|null): Order the results by relation metadata. Accepts:
  - Strings: `'depth asc'`, `'depth desc'`, `'position asc'`, `'position desc'` (also `'depth_asc'` style).
  - Array: `['column' => 'depth|position', 'direction' => 'asc|desc']`.

Examples:
```
// All outgoing relations
Entity::query()->relatedBy($id)->get();

// Only kinds link or reference
Entity::query()->relatedBy($id, ['kind' => ['link', 'reference']])->get();

// Exclude ancestor and mention kinds
Entity::query()->relatedBy($id, ['exceptKind' => ['ancestor', 'mention']])->get();

// Only links tagged as "featured", ordered by position desc
Entity::query()->relatedBy($id, [
  'kind' => 'link',
  'tag' => 'featured',
  'orderBy' => 'position desc',
])->get();

// Without relation meta columns
Entity::query()->relatedBy($id, ['includeRelationMeta' => false])->get();
```

### `relating(string $entityId, array $options = []): Builder`
Entities that call the given entity (incoming relations). Exposes `relating.*` dot-style aliases.

Options:
- `kind` (string|array|null): Filter by one kind (string) or several kinds (array). When omitted/null, all kinds are included.
- `exceptKind` (string|array|null): Exclude one kind (string) or several kinds (array) from the results.
- `tag` (string|null): Filter by a JSON tag present in the relation `tags` column.
- `includeRelationMeta` (bool, default true): Include `relating.*` metadata columns in the select.
- `orderBy` (string|array|null): Same forms as in `relatedBy`.

Examples:
```
// All incoming relations
Entity::query()->relating($id)->get();

// Only incoming of kinds link or reference
Entity::query()->relating($id, ['kind' => ['link', 'reference']])->get();

// Exclude ancestor and mention kinds
Entity::query()->relating($id, ['exceptKind' => ['ancestor', 'mention']])->get();

// Only incoming of kind "reference", ordered by depth asc
Entity::query()->relating($id, ['kind' => 'reference', 'orderBy' => 'depth asc'])->get();
```

### `rootOf(string $entityId): Builder`
Furthest ancestor (root) of a given entity. Exposes `root.*` dot-style aliases.

Rules:
- Returns a single row (the topmost ancestor) or an empty set if the entity has no ancestors.
- Selected meta columns: `root.relation_id`, `root.position`, `root.depth`, `root.tags`.

```
// Get the root entity
$root = Entity::query()->rootOf($id)->first();

// When there is no ancestor, returns null with ->first() or empty with ->get()
```

> Upcoming change: We plan to switch to snake_case aliases (e.g., `parent_position`) and include `entities.*` in selects for consistent hydration. That change will be documented as a minor breaking change with migration guidance.

## Collections helpers
- `EntityCollection::flattenContentsByField()` → transforms the `contents` relation of each entity into `['field' => 'text']`.
- `EntityCollection::groupContentsByField()` → `['field' => ['lang' => 'text']]`.
- `EntityCollection::groupContentsByLang()` → `['lang' => ['field' => 'text']]`.

See also: `EntityContentsCollection` specialized reducers.
