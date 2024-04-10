<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => fake()->numberBetween(1, 5),
            'name' => fake()->name(),
            'price' => fake()->randomFloat(2,1, 100),
            'image' => fake()->imageUrl(640, 480),
            'stock' => fake()->numberBetween(0, 100),
            'description' => fake()->text(),
            'status' => fake()->numberBetween(0, 1),
            'is_favorite' => fake()->numberBetween(0, 1),
        ];
    }
}
