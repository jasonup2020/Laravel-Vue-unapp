<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserFavorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 用户收藏工厂 / User favorite factory
 * @extends Factory<UserFavorite>
 */
class UserFavoriteFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 100),
            'product_id' => fake()->numberBetween(1, 100),
        ];
    }
}
