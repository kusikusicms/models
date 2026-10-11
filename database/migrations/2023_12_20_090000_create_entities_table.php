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
        Schema::create('entities', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('model', 32)->index()->default('Entity');
            $table->json('props')->nullable();
            $table->string('view', 32)->nullable();
            $table->json('langs')->nullable();
            $table->string('parent_entity_id', 26)->index('parent')->nullable();
            $table->boolean('published')->index()->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestampTz('publish_at')->nullable();
            $table->timestampTz('unpublish_at')->nullable();
            $table->integer('version')->unsigned()->default(1);
            $table->integer('version_tree')->unsigned()->default(1);
            $table->integer('version_relations')->unsigned()->default(1);
            $table->integer('version_full')->unsigned()->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        Schema::table('entities', function (Blueprint $table) {
            $table->foreign('parent_entity_id')
                ->references('id')
                ->on('entities')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
