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
        // C端用户菜单表 / C-end user menu table
        Schema::create('c_menus', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->bigInteger('parent_id')->nullable()->default(0)->comment('父级ID/Parent ID');
            $table->string('title', 100)->comment('中文标题/Title');
            $table->string('title_en', 100)->nullable()->comment('英文标题/English title');
            $table->string('icon', 50)->nullable()->comment('图标标识/Icon key');
            $table->string('path', 200)->unique()->comment('跳转路由/Route path');
            $table->string('url', 500)->nullable()->comment('外部链接/External URL');
            $table->integer('sort')->default(0)->comment('排序/Sort');
            $table->tinyInteger('is_show')->default(1)->comment('是否显示：1显示 0隐藏/Visible: 1 show, 0 hide');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            // 索引：按父级递归查询子菜单 / parent_id for recursive children lookup
            $table->index('parent_id', 'c_menus_parent_id_index');
            $table->comment('C端用户菜单表/C-end user menus table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('c_menus');
    }
};
