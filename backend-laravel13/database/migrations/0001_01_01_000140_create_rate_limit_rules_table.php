<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations / 运行迁移
     *
     * Creates the rate_limit_rules table that stores dynamic rate-limit rules
     * read by the api.composite.rate middleware at runtime.
     * 创建 rate_limit_rules 表，存储 api.composite.rate 中间件运行时读取的动态限流规则。
     */
    public function up(): void
    {
        // Rate limit rules table / 限流规则表
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id()->comment('Primary key/主键');
            // Rule key: login / admin:login / api:global / admin:global / custom
            // 规则标识：登录/管理端登录/全局默认/自定义
            $table->string('rule_key', 100)->unique()->comment('Rule key: login / admin:login / api:global / admin:global / custom/规则标识：登录/管理端登录/全局默认/自定义');
            // Max attempts within decay window / 时间窗口内最大尝试次数
            $table->integer('max_attempts')->default(60)->comment('Max attempts/最大尝试次数');
            // Decay window length in seconds / 时间窗口长度（秒）
            $table->integer('decay_seconds')->default(60)->comment('Decay window in seconds/时间窗口秒数');
            // 1=enabled, 0=disabled / 1=启用，0=停用
            $table->tinyInteger('enabled')->default(1)->comment('Enabled 1=on 0=off/是否启用 1=启用 0=停用');
            // Optional human-readable note / 可选备注
            $table->string('remark')->nullable()->comment('Remark/备注');
            $table->timestamps();
            $table->comment('动态限流规则表/Rate limit rules table');
        });
    }

    /**
     * Reverse the migrations / 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limit_rules');
    }
};
