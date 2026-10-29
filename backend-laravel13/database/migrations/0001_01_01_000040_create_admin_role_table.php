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
        // 管理员-角色中间表 / Admin-Role pivot table
        Schema::create('admin_role', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_id')->comment('管理员ID/Admin ID');
            $table->unsignedBigInteger('role_id')->comment('角色ID/Role ID');
            $table->primary(['admin_id', 'role_id']);
            $table->comment('管理员-角色关联表/Admin-Role pivot table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_role');
    }
};
