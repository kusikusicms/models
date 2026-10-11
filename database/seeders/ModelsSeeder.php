<?php

namespace KusikusiCMS\Models\Database\Seeders;

use Illuminate\Database\Seeder;
use KusikusiCMS\Models\Entity;

class ModelsSeeder extends Seeder
{
    /**
     * Seed the package models for demo/testing purposes.
     */
    public function run(): void
    {
        // Draft entity (unpublished)
        Entity::factory()
            ->draft()
            ->withContents(['title' => 'Draft entity', 'body' => 'Not yet live'])
            ->create();

        // Scheduled entity (publish in the future)
        Entity::factory()
            ->scheduled()
            ->withContents(['title' => 'Scheduled entity', 'body' => 'Will be live soon'])
            ->create();

        // Live entity (currently visible)
        Entity::factory()
            ->live()
            ->withContents(['title' => 'Live entity', 'body' => 'This is live'])
            ->create();

        // Expired entity (unpublished in the past)
        Entity::factory()
            ->expired()
            ->withContents(['title' => 'Expired entity', 'body' => 'This was live and is now expired'])
            ->create();
    }
}
