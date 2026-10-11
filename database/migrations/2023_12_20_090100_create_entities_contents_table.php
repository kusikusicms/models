<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entities_contents', function (Blueprint $table) {
            $table->id('content_id');
            $table->string('entity_id', 26)->index();
            $table->char('lang', 5)->index();
            $table->string('field', 32)->index();
            $table->text('text')->nullable();
            $table->timestampsTz();
            $table->unique(['entity_id', 'lang', 'field']);
            // Composite index to speed up whereContent(field, operator, value, lang) lookups
            $table->index(['field', 'lang'], 'entities_contents_field_lang_index');
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
        switch (env('DB_CONNECTION')) {
            case 'mysql':
                DB::statement('CREATE FULLTEXT INDEX entities_contents_value_fulltext ON entities_contents(text);');
                break;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities_contents');
    }
};
