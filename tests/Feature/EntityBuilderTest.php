<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use KusikusiCMS\Models\Builders\EntityBuilder;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Tests\TestCase;

class PageBuilder extends EntityBuilder
{
    public function childrenOf(string $entityId, ?string $tag = null): static
    {
        return parent::childrenOf($entityId, $tag)->where('model', 'page');
    }
}

#[UseEloquentBuilder(PageBuilder::class)]
class Page extends Entity
{
    protected $table = 'entities';
}

class AuthoredEntity extends Entity
{
    protected $table = 'entities';

    public function scopePublishedBy(Builder $query, string $author): Builder
    {
        return $query->where('props->author', $author);
    }

    public function scopeChildrenOf(Builder $query, string $id, ?string $tag = null): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}

class EntityBuilderTest extends TestCase
{
    protected function makeTree(): void
    {
        Entity::create(['id' => 'root', 'model' => 'home', 'props' => ['author' => 'ana']]);
        Entity::create(['id' => 'a', 'model' => 'page', 'parent_entity_id' => 'root', 'props' => ['author' => 'ana']]);
        Entity::create(['id' => 'b', 'model' => 'other', 'parent_entity_id' => 'root', 'props' => ['author' => 'bob']]);
    }

    public function test_query_returns_entity_builder_and_static_call_works(): void
    {
        $this->makeTree();

        $this->assertInstanceOf(EntityBuilder::class, Entity::query());
        $this->assertCount(2, Entity::childrenOf('root')->get());
    }

    public function test_last_used_lang_is_scoped_to_each_query(): void
    {
        $q1 = Entity::query()->withContents(['lang' => 'es']);
        $q2 = Entity::query()->withContents(['lang' => 'fr']);

        $q1->orderByContent('title');
        $q2->orderByContent('title');

        $this->assertStringContainsString('content_order_es_title', $q1->toSql());
        $this->assertStringNotContainsString('content_order_fr_title', $q1->toSql());
        $this->assertStringContainsString('content_order_fr_title', $q2->toSql());
    }

    public function test_subclass_can_add_scopes_that_chain_after_builder_methods(): void
    {
        $this->makeTree();

        $rows = AuthoredEntity::query()->descendantsOf('root')->publishedBy('ana')->get();

        $this->assertSame(['a'], $rows->pluck('id')->all());
    }

    public function test_subclass_can_override_a_filter_with_its_own_builder(): void
    {
        $this->makeTree();

        $this->assertInstanceOf(PageBuilder::class, Page::query());
        $this->assertSame(['a'], Page::childrenOf('root')->get()->pluck('id')->all());
    }

    public function test_model_level_scope_with_a_builder_method_name_is_ignored(): void
    {
        $this->makeTree();

        $this->assertCount(2, AuthoredEntity::childrenOf('root')->get());
    }

    public function test_refresh_ancestors_by_id_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Entity::refreshAncestorsRelationsById('missing');
    }
}
