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
        // 后台管理员表 / Backend admins table
        Schema::create('admins', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('username')->unique()->comment('登录账号/Login username');
            $table->string('nickname')->nullable()->comment('昵称/Nickname');
            $table->string('email')->nullable()->comment('邮箱/Email');
            $table->string('password')->comment('密码哈希/Password hash');
            $table->string('salt', 6)->nullable()->comment('密码盐（6位字母数字，Hash(明文+盐)）');
            $table->string('avatar')->nullable()->comment('头像地址/Avatar URL');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用/Status: 1 active, 0 disabled');
            $table->rememberToken()->comment('记住令牌/Remember token');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            $table->comment('管理员表 - 后台管理员/Admins table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
