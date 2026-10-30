<?php

namespace KusikusiCMS\Models\Builders\Concerns;

use KusikusiCMS\Models\EntityRelation;

trait FiltersHierarchy
{
    /**
     * Only include entities of a given model id, or of any of the given model ids.
     *
     * @param  string|array<int, string>  $model  The id of the model, or a list of model ids
     */
    public function ofModel(string|array $model): static
    {
        if (is_array($model)) {
            return $this->whereIn('model', $model);
        }

        return $this->where('model', $model);
    }

    /**
     * Only include children of a given parent id.
     *
     * @param  string  $entityId  The id of the parent entity
     * @param  string|null  $tag  Filter by one tag
     */
    public function childrenOf(string $entityId, ?string $tag = null): static
    {
        return $this->join('entities_relations as child', function ($join) use ($entityId, $tag) {
            $join->on('child.caller_entity_id', '=', 'entities.id')
                ->where('child.called_entity_id', '=', $entityId)
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
     * Only include the parent of the given id.
     *
     * @param  string  $entityId  The id of the child entity
     */
    public function parentOf(string $entityId): static
    {
        return $this->join('entities_relations as parent', function ($join) use ($entityId) {
            $join->on('parent.called_entity_id', '=', 'entities.id')
                ->where('parent.caller_entity_id', '=', $entityId)
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
     * Only include the ancestors of a given entity.
     *
     * Options (array):
     * - includeSelf (bool): Whether to include the referenced entity itself (default false).
     * - includeRelationMeta (bool): Whether to include ancestor.* meta columns in the select (default true).
     * - order (string): 'ascending' | 'descending' | 'asc' | 'desc'. Ascending means closest ancestor first.
     *                   When includeSelf=true, self is treated as depth 0. Any other value orders descending.
     *
     * @param  string  $entityId  The id of the entity
     * @param  array{includeSelf?: bool, includeRelationMeta?: bool, order?: string}  $options
     */
    public function ancestorsOf(string $entityId, array $options = []): static
    {
        [$includeSelf, $includeRelationMeta] = $this->relationMetaOptions($options);
        $orderOpt = isset($options['order']) ? strtolower((string) $options['order']) : null;
        $direction = ($orderOpt === 'ascending' || $orderOpt === 'asc') ? 'asc' : 'desc';

        // LEFT JOIN so the self row can be included without a matching relation
        $this->leftJoin('entities_relations as ancestor', function ($join) use ($entityId) {
            $join->on('ancestor.called_entity_id', '=', 'entities.id')
                ->where('ancestor.caller_entity_id', '=', $entityId)
                ->where('ancestor.kind', '=', EntityRelation::RELATION_ANCESTOR);
        });

        $this->requireRelationOrSelf('ancestor', $entityId, $includeSelf);

        $this->addSelect('id');

        if ($includeRelationMeta) {
            $this->addSelect('ancestor.relation_id as ancestor.relation_id')
                ->addSelect('ancestor.position as ancestor.position')
                ->addSelect('ancestor.depth as ancestor.depth')
                ->addSelect('ancestor.tags as ancestor.tags');
        }

        return $this->orderByRaw(
            "CASE WHEN entities.id = ? THEN 0 ELSE ancestor.depth END $direction",
            [$entityId]
        );
    }

    /**
     * Only include descendants of a given entity id.
     *
     * Options (array) — second parameter:
     * - maxDepth (int): Maximum depth to include (default 99).
     * - includeSelf (bool): Whether to include the entity itself in the result set (default false).
     * - includeRelationMeta (bool): Whether to include descendant.* meta columns in the select (default true).
     *
     * If the second parameter is an integer, it is treated as ['maxDepth' => $options].
     *
     * @param  string  $entityId  The id of the entity
     * @param  array{maxDepth?: int, includeSelf?: bool, includeRelationMeta?: bool}|int  $options
     */
    public function descendantsOf(string $entityId, array|int $options = []): static
    {
        if (! is_array($options)) {
            $options = ['maxDepth' => $options];
        }

        $maxDepth = (int) ($options['maxDepth'] ?? 99);
        [$includeSelf, $includeRelationMeta] = $this->relationMetaOptions($options);

        // LEFT JOIN so the self row can be included without a matching relation
        $this->leftJoin('entities_relations as descendant', function ($join) use ($entityId, $maxDepth) {
            $join->on('descendant.caller_entity_id', '=', 'entities.id')
                ->where('descendant.called_entity_id', '=', $entityId)
                ->where('descendant.kind', '=', EntityRelation::RELATION_ANCESTOR)
                ->where('descendant.depth', '<=', $maxDepth);
        });

        $this->requireRelationOrSelf('descendant', $entityId, $includeSelf);

        $this->addSelect('id', 'parent_entity_id');

        if ($includeRelationMeta) {
            $this->addSelect('descendant.relation_id as descendant.relation_id')
                ->addSelect('descendant.position as descendant.position')
                ->addSelect('descendant.depth as descendant.depth')
                ->addSelect('descendant.tags as descendant.tags');
        }

        return $this;
    }

    /**
     * Only include siblings (entities sharing the same parent_entity_id) of a given entity id.
     *
     * Options (array):
     * - includeSelf (bool): Whether to include the referenced entity itself (default false).
     * - includeRelationMeta (bool): Whether to include sibling.* meta columns in the select (default true).
     *
     * @param  string  $entityId  The id of the entity
     * @param  array{includeSelf?: bool, includeRelationMeta?: bool}  $options
     */
    public function siblingsOf(string $entityId, array $options = []): static
    {
        [$includeSelf, $includeRelationMeta] = $this->relationMetaOptions($options);

        $parentSubquery = fn () => $this->getModel()->newQuery()
            ->select('parent_entity_id')
            ->where('id', '=', $entityId)
            ->limit(1);

        $this->whereIn('entities.parent_entity_id', $parentSubquery())
            ->whereNotNull('entities.parent_entity_id');

        if (! $includeSelf) {
            $this->where('entities.id', '!=', $entityId);
        }

        // LEFT JOIN to get the sibling metadata (from the ancestor relation to the shared parent)
        $this->leftJoin('entities_relations as sibling', function ($join) use ($parentSubquery) {
            $join->on('sibling.caller_entity_id', '=', 'entities.id')
                ->where('sibling.called_entity_id', '=', $parentSubquery())
                ->where('sibling.depth', '=', 1)
                ->where('sibling.kind', '=', EntityRelation::RELATION_ANCESTOR);
        });

        $this->addSelect('id');

        if ($includeRelationMeta) {
            $this->addSelect('sibling.relation_id as sibling.relation_id')
                ->addSelect('sibling.position as sibling.position')
                ->addSelect('sibling.depth as sibling.depth')
                ->addSelect('sibling.tags as sibling.tags');
        }

        return $this;
    }

    /**
     * Only include the furthest ancestor (root) of a given entity.
     * If the entity has no ancestors (is a root itself), the result is empty.
     *
     * @param  string  $entityId  The id of the entity
     */
    public function rootOf(string $entityId): static
    {
        return $this->join('entities_relations as root', function ($join) use ($entityId) {
            $join->on('root.called_entity_id', '=', 'entities.id')
                ->where('root.caller_entity_id', '=', $entityId)
                ->where('root.kind', '=', EntityRelation::RELATION_ANCESTOR);
        })
            ->addSelect('id')
            ->addSelect('root.relation_id as root.relation_id')
            ->addSelect('root.position as root.position')
            ->addSelect('root.depth as root.depth')
            ->addSelect('root.tags as root.tags')
            ->orderByDesc('root.depth')
            ->limit(1);
    }

    /**
     * Read the shared `includeSelf` and `includeRelationMeta` options.
     *
     * @param  array<string, mixed>  $options
     * @return array{0: bool, 1: bool} [includeSelf (default false), includeRelationMeta (default true)]
     */
    protected function relationMetaOptions(array $options): array
    {
        return [
            (bool) ($options['includeSelf'] ?? false),
            array_key_exists('includeRelationMeta', $options) ? (bool) $options['includeRelationMeta'] : true,
        ];
    }

    /**
     * Require the left-joined relation to exist, optionally allowing the entity itself without it.
     */
    protected function requireRelationOrSelf(string $alias, string $entityId, bool $includeSelf): void
    {
        if ($includeSelf) {
            $this->where(function ($q) use ($alias, $entityId) {
                $q->whereNotNull("$alias.relation_id")
                    ->orWhere('entities.id', '=', $entityId);
            });
        } else {
            $this->whereNotNull("$alias.relation_id");
        }
    }
}
