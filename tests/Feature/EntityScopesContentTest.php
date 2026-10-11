<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\EntityContent;
use KusikusiCMS\Models\Tests\TestCase;

class EntityScopesContentTest extends TestCase
{
    protected function makeEntityWithContents(string $id, array $contents, string $lang = 'en'): Entity
    {
        $e = Entity::create(['id' => $id, 'model' => 'Entity']);
        foreach ($contents as $field => $text) {
            EntityContent::create([
                'entity_id' => $e->id,
                'lang' => $lang,
                'field' => $field,
                'text' => $text,
            ]);
        }

        return $e;
    }

    public function test_with_contents_filters_by_lang_and_fields(): void
    {
        $e = $this->makeEntityWithContents('e1', ['title' => 'Hello', 'body' => 'World'], 'en');
        $this->makeEntityWithContents('e2', ['title' => 'Hola', 'body' => 'Mundo'], 'es');

        $e2 = Entity::query()->withContents(['lang' => 'es', 'fields' => ['title', 'body']])->where('id', 'e2')->first();
        $this->assertNotNull($e2);
        $this->assertSame('Hola', $e2->contents->title);
        $this->assertSame('Mundo', $e2->contents->body);
    }

    public function test_order_by_content_uses_configured_default_language_and_respects_order(): void
    {
        Config::set('kusikusicms.models.default_language', 'en');
        $this->makeEntityWithContents('a', ['title' => 'Bravo'], 'en');
        $this->makeEntityWithContents('b', ['title' => 'Alpha'], 'en');

        $asc = Entity::query()->orderByContent('title')->pluck('id')->all();
        $this->assertSame(['b', 'a'], $asc, 'Asc should order by Alpha, Bravo');

        $desc = Entity::query()->orderByContent('title', 'desc')->pluck('id')->all();
        $this->assertSame(['a', 'b'], $desc, 'Desc should order by Bravo, Alpha');
    }

    public function test_where_content_characterization_current_behavior(): void
    {
        // Current implementation compares "field" using the operator, which is likely unintended.
        $this->makeEntityWithContents('x', ['title' => 'Laravel'], 'en');
        $this->makeEntityWithContents('y', ['title' => 'Symfony'], 'en');

        $rows = Entity::query()->whereContent('title', 'like', 'Lara%')->pluck('id')->all();
        $this->assertContains('x', $rows);
        $this->assertNotContains('y', $rows);
    }

    public function test_where_content_intended_behavior_now_passes(): void
    {
        $this->makeEntityWithContents('x', ['title' => 'Laravel'], 'en');
        $this->makeEntityWithContents('y', ['title' => 'Symfony'], 'en');

        // Intended API: field equality and operator on text
        $rows = Entity::query()->whereContent('title', 'like', 'Lara%')->pluck('id')->all();
        $this->assertSame(['x'], $rows);
    }
}
