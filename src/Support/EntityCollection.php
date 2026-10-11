<?php

namespace KusikusiCMS\Models\Support;

use Illuminate\Database\Eloquent\Collection;
use KusikusiCMS\Models\Entity;

/**
 * @extends Collection<int, Entity>
 */
class EntityCollection extends Collection
{
    /**
     * Reorganize the collection into a tree using each entity's `id` and `parent_entity_id`.
     * Children are placed in a `children` attribute on their parent.
     * Entities whose parent is not present in the collection (or null) are returned as roots.
     *
     * Returns an EntityCollection so you can chain collection methods like `toJson()`.
     * The `children` attribute on each entity is also an EntityCollection.
     */
    public function toTree(): EntityCollection
    {
        // Index entities by id and make sure every entity has an empty children collection
        $indexed = [];
        foreach ($this->items as $entity) {
            // Initialize children as an EntityCollection for full chainability
            $entity->setAttribute('children', new static);
            $indexed[$entity->id] = $entity;
        }

        /** @var EntityCollection $roots */
        $roots = new static;

        // Assign children to their parents when present in the collection; otherwise mark as root
        foreach ($this->items as $entity) {
            $parentId = $entity->parent_entity_id ?? null;
            if ($parentId !== null && isset($indexed[$parentId])) {
                /** @var EntityCollection $children */
                $children = $indexed[$parentId]->getAttribute('children');
                $children->push($entity);
            } else {
                $roots->push($entity);
            }
        }

        return $roots;
    }
}
