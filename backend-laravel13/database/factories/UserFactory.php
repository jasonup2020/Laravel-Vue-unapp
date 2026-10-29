<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Support\SaltHasher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * C端普通用户工厂 / C-end User factory
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salt = SaltHasher::generate();
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // salt 必须先于 password：password 的 cast set 需读取本行 salt 才能加盐哈希
            'salt' => $salt,
            'password' => SaltHasher::make('password', $salt),
            'status' => 1,
            'remember_token' => Str::random(10),
        ];
    }
}
