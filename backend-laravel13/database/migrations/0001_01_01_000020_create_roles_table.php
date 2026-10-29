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
        // 角色表 / Roles table（RBAC 角色）
        Schema::create('roles', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('name')->comment('角色名称/Role name');
            $table->string('code')->unique()->comment('角色唯一标识/Role unique code');
            $table->text('remark')->nullable()->comment('备注说明/Remark');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            $table->comment('角色表 - 系统角色定义/Roles table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
