<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移 / Run the migrations
     */
    public function up(): void
    {
        // C端普通用户表 / C-end users table
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('name')->comment('用户名/Display name');
            $table->string('email')->unique()->comment('登录邮箱/Login email');
            $table->string('password')->comment('密码bcrypt哈希/Password bcrypt hash');
            $table->string('salt', 6)->nullable()->comment('密码盐（6位字母数字，Hash(明文+盐)）');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用/Status: 1 active, 0 disabled');
            $table->rememberToken()->comment('记住令牌/Remember token');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            $table->comment('用户表 - C端普通用户/Users table');
        });

        // 密码重置令牌表 / Password reset tokens table（保留骨架原样）
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('邮箱/Email');
            $table->string('token')->comment('重置令牌/Reset token');
            $table->timestamp('created_at')->nullable()->comment('创建时间/Created at');
            $table->comment('密码重置令牌表/Password reset tokens table');
        });

        // 会话表 / Sessions table（保留骨架原样）
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('会话ID/Session ID');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('用户ID/User ID');
            $table->string('ip_address', 45)->nullable()->comment('客户端IP/Client IP');
            $table->text('user_agent')->nullable()->comment('浏览器UA/User agent');
            $table->longText('payload')->comment('会话载荷/Session payload');
            $table->integer('last_activity')->index()->comment('最后活动时间戳/Last activity timestamp');
            $table->comment('会话表/Sessions table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
