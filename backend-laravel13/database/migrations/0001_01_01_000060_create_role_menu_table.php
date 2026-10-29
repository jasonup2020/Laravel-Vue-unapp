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
        // 角色-菜单中间表 / Role-Menu pivot table
        Schema::create('role_menu', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->comment('角色ID/Role ID');
            $table->unsignedBigInteger('menu_id')->comment('菜单ID/Menu ID');
            $table->primary(['role_id', 'menu_id']);
            $table->comment('角色-菜单关联表/Role-Menu pivot table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('role_menu');
    }
};
