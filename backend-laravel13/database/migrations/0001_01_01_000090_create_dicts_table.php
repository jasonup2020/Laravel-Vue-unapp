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
        // 通用数据字典表 / Generic data dictionary table
        Schema::create('dicts', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('type')->comment('字典类型标识/Dictionary type key');
            $table->string('label')->comment('显示文本/Display label');
            $table->string('label_en')->nullable()->comment('英文显示文本/English display label');
            $table->string('value')->comment('存储值/Stored value');
            $table->integer('sort')->default(0)->comment('排序序号/Sort order');
            $table->tinyInteger('status')->default(1)->comment('启用状态：1启用 0禁用/Status: 1 enabled, 0 disabled');
            $table->timestamps();
            $table->index(['type', 'status']);
            $table->comment('数据字典表/Data dictionary table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('dicts');
    }
};
