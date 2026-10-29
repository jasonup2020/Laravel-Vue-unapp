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
        // 用户收藏表 / User favorites table
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->unsignedBigInteger('user_id')->comment('普通用户ID/C-end user ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID/Product ID');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            $table->unique(['user_id', 'product_id']);
            // 联合索引：用户收藏列表分页排序 / user_id + created_at for favorites list pagination
            $table->index(['user_id', 'created_at'], 'user_favorites_user_id_created_at_index');
            $table->comment('用户收藏表/User favorites table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
