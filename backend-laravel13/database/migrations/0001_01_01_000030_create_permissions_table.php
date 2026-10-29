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
        // 权限表 / Permissions table（接口按钮级权限）
        Schema::create('permissions', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('name')->comment('权限名称/Permission display name');
            $table->string('label_en')->nullable()->comment('英文显示文本/English display label');
            $table->string('code')->unique()->comment('权限标识如 admin:product:list/Permission code e.g. admin:product:list');
            $table->string('group')->nullable()->comment('权限分组模块/Permission group module');
            $table->string('action', 50)->nullable()->comment('Action: list/create/update/delete or custom/动作：查/增/改/删或自定义（export/audit/kick 等）');
            $table->string('remark', 255)->nullable()->comment('备注/Remark');
            $table->tinyInteger('type')->default(1)->comment('Type 1=system preset 2=custom/类型 1=系统预置 2=自定义');
            $table->timestamps();
            $table->comment('权限表/Permissions table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
