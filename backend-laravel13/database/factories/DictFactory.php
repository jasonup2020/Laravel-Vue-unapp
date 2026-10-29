<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 数据字典工厂 / Dictionary factory
 * @extends Factory<Dict>
 */
class DictFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'demo_type',
            'label' => fake()->word(),
            'value' => (string) fake()->numberBetween(0, 100),
            'sort' => fake()->numberBetween(0, 100),
            'status' => 1,
        ];
    }
}
