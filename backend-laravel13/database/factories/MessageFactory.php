<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 站内消息工厂 / Message factory
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receiver_type' => 'user',
            'receiver_id' => fake()->numberBetween(1, 100),
            'msg_type' => 'biz',
            'title' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'extra' => null,
            'is_read' => 0,
            'is_pop' => 0,
        ];
    }
}
