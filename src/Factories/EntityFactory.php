<?php
namespace KusikusiCMS\Models\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use KusikusiCMS\Models\Entity;

class EntityFactory extends Factory
{

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Entity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model' => 'Entity',
            'view' => 'entity'
        ];
    }
}