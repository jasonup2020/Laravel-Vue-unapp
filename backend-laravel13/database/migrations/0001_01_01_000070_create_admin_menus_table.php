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
        // 后台动态菜单表 / Backend dynamic admin menus table
        Schema::create('admin_menus', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父菜单ID，0为根节点/Parent menu ID, 0=root');
            $table->string('title')->comment('菜单显示标题/Menu display title');
            $table->string('title_en')->nullable()->comment('英文菜单标题/English menu title');
            $table->string('path')->nullable()->comment('前端Vue路由路径/Vue route path');
            $table->string('component')->nullable()->comment('前端组件路径/Frontend component path');
            $table->string('icon')->nullable()->comment('菜单图标/Menu icon');
            $table->string('permission_code')->nullable()->comment('绑定权限码/Bound permission code');
            $table->integer('sort')->default(0)->comment('排序，数字越小越靠前/Sort order, lower=first');
            $table->tinyInteger('is_show')->default(1)->comment('是否侧边栏显示/Visible in sidebar');
            $table->tinyInteger('is_cache')->default(1)->comment('是否keep-alive缓存/Keep-alive cache');
            $table->tinyInteger('is_external')->default(1)->comment('是否外部链接/External link flag');
            $table->string('external_url')->nullable()->comment('外部链接地址/External URL');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            // 单列索引：children 递归查询子菜单 / parent_id for recursive children lookup
            $table->index('parent_id', 'admin_menus_parent_id_index');
            // 表注释 / Table comment
            $table->comment('后台动态菜单表/Admin menus table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_menus');
    }
};
