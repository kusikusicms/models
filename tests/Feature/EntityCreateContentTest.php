<?php

namespace KusikusiCMS\Models\Tests\Feature;

use Illuminate\Support\Facades\Config;
use KusikusiCMS\Models\Entity;
use KusikusiCMS\Models\Tests\TestCase;

class EntityCreateContentTest extends TestCase
{
    public function test_upserts_multiple_fields_and_returns_affected_rows(): void
    {
        $e = Entity::create(['id' => 'e1', 'model' => 'Entity']);
        $count = $e->createContents(['title' => 'Hello', 'body' => 'World'], 'en');
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertDatabaseHas('entities_contents', ['entity_id' => 'e1', 'lang' => 'en', 'field' => 'title', 'text' => 'Hello']);
        $this->assertDatabaseHas('entities_contents', ['entity_id' => 'e1', 'lang' => 'en', 'field' => 'body', 'text' => 'World']);

        // Upsert should update text
        $count2 = $e->createContents(['title' => 'Hello 2'], 'en');
        $this->assertGreaterThanOrEqual(1, $count2);
        $this->assertDatabaseHas('entities_contents', ['entity_id' => 'e1', 'lang' => 'en', 'field' => 'title', 'text' => 'Hello 2']);
    }

    public function test_defaults_to_configured_language_when_none_is_passed(): void
    {
        Config::set('kusikusicms.models.default_language', 'es');
        $e = Entity::create(['id' => 'e2', 'model' => 'Entity']);
        $e->createContents(['title' => 'Titulo']);
        $this->assertDatabaseHas('entities_contents', ['entity_id' => 'e2', 'lang' => 'es', 'field' => 'title', 'text' => 'Titulo']);
    }
}
