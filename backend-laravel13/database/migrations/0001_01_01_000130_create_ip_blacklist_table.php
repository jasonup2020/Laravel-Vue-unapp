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
        // IP黑名单表 / IP blacklist table
        Schema::create('ip_blacklist', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('ip')->unique()->comment('封禁IP地址/Banned IP address');
            $table->string('remark')->nullable()->comment('封禁备注原因/Ban remark reason');
            $table->dateTime('expire_at')->nullable()->comment('过期时间，null为永久封禁/Expiry time, null=permanent');
            $table->timestamps();
            $table->comment('IP黑名单表/IP blacklist table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_blacklist');
    }
};
