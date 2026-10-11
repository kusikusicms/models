<?php

namespace KusikusiCMS\Models\Tests\Unit;

use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Models\Support\EntityContentsCollection;
use KusikusiCMS\Models\Tests\TestCase;

class EntityContentsCollectionTest extends TestCase
{
    public function test_flatten_by_field(): void
    {
        $c1 = new EntityContent(['field' => 'title', 'lang' => 'en', 'text' => 'Hello']);
        $c2 = new EntityContent(['field' => 'body', 'lang' => 'en', 'text' => 'World']);
        $collection = new EntityContentsCollection([$c1, $c2]);
        $result = $collection->flattenByField();
        $this->assertSame(['title' => 'Hello', 'body' => 'World'], $result);
    }

    public function test_group_by_field(): void
    {
        $c1 = new EntityContent(['field' => 'title', 'lang' => 'en', 'text' => 'Hello']);
        $c2 = new EntityContent(['field' => 'title', 'lang' => 'es', 'text' => 'Hola']);
        $c3 = new EntityContent(['field' => 'body', 'lang' => 'en', 'text' => 'World']);
        $collection = new EntityContentsCollection([$c1, $c2, $c3]);
        $result = $collection->groupByField();
        $this->assertSame([
            'title' => ['en' => 'Hello', 'es' => 'Hola'],
            'body' => ['en' => 'World'],
        ], $result);
    }

    public function test_group_by_lang(): void
    {
        $c1 = new EntityContent(['field' => 'title', 'lang' => 'en', 'text' => 'Hello']);
        $c2 = new EntityContent(['field' => 'title', 'lang' => 'es', 'text' => 'Hola']);
        $c3 = new EntityContent(['field' => 'body', 'lang' => 'en', 'text' => 'World']);
        $collection = new EntityContentsCollection([$c1, $c2, $c3]);
        $result = $collection->groupByLang();
        $this->assertSame([
            'en' => ['title' => 'Hello', 'body' => 'World'],
            'es' => ['title' => 'Hola'],
        ], $result);
    }
}
