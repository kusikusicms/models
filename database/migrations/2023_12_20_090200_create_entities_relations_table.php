<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entities_relations', function (Blueprint $table) {
            $table->id('relation_id');
            $table->string('caller_entity_id', 26)->index('caller');
            $table->string('called_entity_id', 26)->index('called');
            $table->string('kind', 24)->default('relation')->index('kind');
            $table->integer('position')->default(0);
            $table->integer('depth')->unsigned()->default(0)->index('depth');
            $table->json('tags')->nullable();
            $table->timestampsTz();
            $table->foreign('caller_entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('called_entity_id')
                ->references('id')
                ->on('entities')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unique(['caller_entity_id', 'called_entity_id', 'kind'], 'relation_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities_relations');
    }
};
