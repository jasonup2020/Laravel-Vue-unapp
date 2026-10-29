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
        // 商品业务示例表 / Products business example table
        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('name')->comment('商品名称/Product name');
            $table->text('desc')->nullable()->comment('商品描述/Product description');
            $table->decimal('price', 10, 2)->default(0)->comment('商品价格/Product price');
            $table->tinyInteger('status')->default(1)->comment('上下架状态：1上架 0下架/Status: 1 on-shelf, 0 off-shelf');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            // 联合索引：前台按状态+时间列表 / status + created_at for frontend product listing
            $table->index(['status', 'created_at'], 'products_status_created_at_index');
            // 联合索引：按状态+价格排序 / status + price for price sorting
            $table->index(['status', 'price'], 'products_status_price_index');
            $table->comment('商品表/Products table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
