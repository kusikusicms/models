<?php

namespace KusikusiCMS\Models\Builders\Concerns;

trait FiltersRelations
{
    /**
     * Only get entities being called by (outgoing relations from) a source entity.
     *
     * Options (array):
     * - kind (string|array|null): Filter by one kind or several kinds. When omitted, all kinds are included.
     * - exceptKind (string|array|null): Exclude one kind or several kinds.
     * - tag (string|null): Filter by presence of a JSON tag on the relation.
     * - includeRelationMeta (bool): Include relation meta columns in the select (default true). Aliases: related_by.*
     * - orderBy (string|array|null): Order by relation meta. Accepts 'depth asc', 'position desc', compact forms
     *                                'depth_asc', 'position_desc', or ['column' => 'depth|position', 'direction' => 'asc|desc'].
     *
     * @param  string  $entityId  The id of the entity calling the relations
     * @param  array<string, mixed>  $options
     */
    public function relatedBy(string $entityId, array $options = []): static
    {
        return $this->applyRelationJoin('related_by', 'called_entity_id', 'caller_entity_id', $entityId, $options);
    }

    /**
     * Only get entities calling (incoming relations to) a target entity.
     *
     * Accepts the same options as relatedBy(). Aliases: relating.*
     *
     * @param  string  $entityId  The id of the entity receiving the relations (target)
     * @param  array<string, mixed>  $options
     */
    public function relating(string $entityId, array $options = []): static
    {
        return $this->applyRelationJoin('relating', 'caller_entity_id', 'called_entity_id', $entityId, $options);
    }

    /**
     * Join the relations table under an alias, applying kind/tag filters, meta columns and ordering.
     *
     * @param  string  $alias  Alias of the joined relations table
     * @param  string  $selfColumn  Relation column that points to the entities returned by the query
     * @param  string  $otherColumn  Relation column that points to the given entity
     * @param  array<string, mixed>  $options
     */
    protected function applyRelationJoin(string $alias, string $selfColumn, string $otherColumn, string $entityId, array $options): static
    {
        $kindOpt = $options['kind'] ?? null;
        $exceptKindOpt = $options['exceptKind'] ?? null;
        $tag = $options['tag'] ?? null;
        $includeRelationMeta = array_key_exists('includeRelationMeta', $options) ? (bool) $options['includeRelationMeta'] : true;

        $kinds = is_array($kindOpt) ? array_values($kindOpt) : ($kindOpt !== null ? [(string) $kindOpt] : null);
        $exceptKinds = is_array($exceptKindOpt) ? array_values($exceptKindOpt) : ($exceptKindOpt !== null ? [(string) $exceptKindOpt] : []);

        $this->join("entities_relations as $alias", function ($join) use ($alias, $selfColumn, $otherColumn, $entityId, $kinds, $exceptKinds, $tag) {
            $join->on("$alias.$selfColumn", '=', 'entities.id')
                ->where("$alias.$otherColumn", '=', $entityId)
                ->when($tag, function ($q) use ($alias, $tag) {
                    return $q->whereJsonContains("$alias.tags", $tag);
                });
            if ($kinds !== null && count($kinds) > 0) {
                $join->whereIn("$alias.kind", $kinds);
            }
            if (! empty($exceptKinds)) {
                $join->whereNotIn("$alias.kind", $exceptKinds);
            }
        });

        $this->addSelect('id');

        if ($includeRelationMeta) {
            foreach (['relation_id', 'kind', 'position', 'depth', 'tags'] as $column) {
                $this->addSelect("$alias.$column as $alias.$column");
            }
        }

        $order = $this->parseRelationOrder($options['orderBy'] ?? null);
        if ($order !== null) {
            $this->orderBy("$alias.{$order['column']}", $order['direction']);
        }

        return $this;
    }

    /**
     * Normalize the orderBy option of the relation filters.
     *
     * @return array{column: string, direction: string}|null
     */
    protected function parseRelationOrder(mixed $orderBy): ?array
    {
        if (empty($orderBy)) {
            return null;
        }

        $column = null;
        $direction = 'asc';
        if (is_string($orderBy)) {
            $s = str_replace('_', ' ', strtolower(trim($orderBy)));
            if (in_array($s, ['depth asc', 'depth desc', 'position asc', 'position desc'], true)) {
                [$column, $direction] = explode(' ', $s);
            }
        } elseif (is_array($orderBy)) {
            $col = strtolower((string) ($orderBy['column'] ?? ''));
            $dir = strtolower((string) ($orderBy['direction'] ?? 'asc'));
            if (in_array($col, ['depth', 'position'], true)) {
                $column = $col;
            }
            if (in_array($dir, ['asc', 'desc'], true)) {
                $direction = $dir;
            }
        }

        return $column ? ['column' => $column, 'direction' => $direction] : null;
    }
}
