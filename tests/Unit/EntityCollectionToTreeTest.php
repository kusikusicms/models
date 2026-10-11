<?php

namespace KusikusiCMS\Models\Tests\Unit;

use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Support\EntityCollection;
use KusikusiCMS\Models\Tests\TestCase;

class EntityCollectionToTreeTest extends TestCase
{
    public function test_to_tree_returns_entity_collection_and_children_collections(): void
    {
        $root = new Entity(['id' => 'root']);
        $child1 = new Entity(['id' => 'c1', 'parent_entity_id' => 'root']);
        $child2 = new Entity(['id' => 'c2', 'parent_entity_id' => 'root']);
        $grandchild = new Entity(['id' => 'gc', 'parent_entity_id' => 'c1']);

        $collection = new EntityCollection([$child1, $root, $grandchild, $child2]);

        $tree = $collection->toTree();

        // Root is an EntityCollection
        $this->assertInstanceOf(EntityCollection::class, $tree);
        $this->assertCount(1, $tree);
        $this->assertSame('root', $tree->first()->id);

        // Children collections are EntityCollection too
        $rootChildren = $tree->first()->getAttribute('children');
        $this->assertInstanceOf(EntityCollection::class, $rootChildren);
        $this->assertCount(2, $rootChildren);

        // c1 should have one grandchild
        $c1 = $rootChildren->firstWhere('id', 'c1');
        $this->assertNotNull($c1);
        $this->assertInstanceOf(EntityCollection::class, $c1->getAttribute('children'));
        $this->assertCount(1, $c1->getAttribute('children'));
        $this->assertSame('gc', $c1->getAttribute('children')->first()->id);
    }

    public function test_to_tree_multiple_roots_and_orphan_as_root(): void
    {
        $a = new Entity(['id' => 'a']);
        $b = new Entity(['id' => 'b']);
        $a1 = new Entity(['id' => 'a1', 'parent_entity_id' => 'a']);
        $orphan = new Entity(['id' => 'x', 'parent_entity_id' => 'missing']); // parent not present

        $collection = new EntityCollection([$a1, $b, $orphan, $a]);
        $tree = $collection->toTree();

        // Expect 3 roots: a, b, and x (orphan)
        $this->assertCount(3, $tree);
        $rootIds = $tree->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['a', 'b', 'x'], $rootIds);

        // a has one child a1
        $aNode = $tree->firstWhere('id', 'a');
        $this->assertNotNull($aNode);
        $this->assertCount(1, $aNode->getAttribute('children'));
        $this->assertSame('a1', $aNode->getAttribute('children')->first()->id);

        // orphan has no children and is a root
        $xNode = $tree->firstWhere('id', 'x');
        $this->assertNotNull($xNode);
        $this->assertInstanceOf(EntityCollection::class, $xNode->getAttribute('children'));
        $this->assertCount(0, $xNode->getAttribute('children'));
    }

    public function test_children_order_is_preserved(): void
    {
        $parent = new Entity(['id' => 'p']);
        $c1 = new Entity(['id' => 'c1', 'parent_entity_id' => 'p']);
        $c2 = new Entity(['id' => 'c2', 'parent_entity_id' => 'p']);
        $c3 = new Entity(['id' => 'c3', 'parent_entity_id' => 'p']);

        // Intentionally add in specific order to verify insertion order is kept in children
        $collection = new EntityCollection([$parent, $c2, $c1, $c3]);
        $tree = $collection->toTree();

        $pNode = $tree->firstWhere('id', 'p');
        $this->assertNotNull($pNode);
        $this->assertSame(['c2', 'c1', 'c3'], $pNode->getAttribute('children')->pluck('id')->all());
    }

    public function test_to_tree_result_is_json_serializable_and_chainable(): void
    {
        $root = new Entity(['id' => 'r']);
        $child = new Entity(['id' => 'c', 'parent_entity_id' => 'r']);

        $tree = (new EntityCollection([$child, $root]))->toTree();

        $json = $tree->toJson();
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('r', $decoded[0]['id']);
        $this->assertArrayHasKey('children', $decoded[0]);
        $this->assertSame('c', $decoded[0]['children'][0]['id']);
    }
}
