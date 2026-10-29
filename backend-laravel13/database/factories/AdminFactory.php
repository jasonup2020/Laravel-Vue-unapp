<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Support\SaltHasher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 后台管理员工厂 / Backend Admin factory
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * 定义默认属性 / Define model default state
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salt = SaltHasher::generate();
        return [
            'username' => fake()->unique()->userName(),
            'nickname' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // salt 必须先于 password：password 的 cast set 需读取本行 salt 才能加盐哈希
            'salt' => $salt,
            'password' => SaltHasher::make('admin123', $salt),
            'avatar' => null,
            'status' => 1,
            'remember_token' => Str::random(10),
        ];
    }
}
