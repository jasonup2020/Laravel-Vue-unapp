<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 商品工厂 / Product factory
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'desc' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 1, 9999),
            'status' => 1,
        ];
    }
}
