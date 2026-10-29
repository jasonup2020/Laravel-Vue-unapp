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
        // 角色-权限中间表 / Role-Permission pivot table
        Schema::create('role_permission', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->comment('角色ID/Role ID');
            $table->unsignedBigInteger('permission_id')->comment('权限ID/Permission ID');
            $table->primary(['role_id', 'permission_id']);
            $table->comment('角色-权限关联表/Role-Permission pivot table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
