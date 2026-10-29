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
        // 站内消息表 / In-app messaging table (WebSocket)
        Schema::create('messages', function (Blueprint $table) {
            $table->id()->comment('主键/Primary key');
            $table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('雪花业务ID/Snowflake business ID');
            $table->string('receiver_type')->comment('接收方类型：user/admin/all_user/all_admin/Receiver type');
            $table->unsignedBigInteger('receiver_id')->nullable()->comment('接收人ID，广播为null/Receiver ID, null=broadcast');
            $table->string('msg_type')->comment('消息类型：announce公告/biz业务/warn告警/Message type');
            $table->string('title')->comment('消息标题/Message title');
            $table->text('content')->nullable()->comment('消息内容/Message content');
            $table->json('extra')->nullable()->comment('扩展参数JSON，含跳转URL等/Extra JSON payload');
            $table->tinyInteger('is_read')->default(0)->comment('是否已读：0未读 1已读/Is read: 0 unread, 1 read');
            $table->tinyInteger('is_pop')->default(0)->comment('是否客户端弹窗/Should popup on client');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间/Soft delete timestamp');
            $table->index(['receiver_type', 'receiver_id', 'is_read']);
            // 联合索引：按类型+时间查历史消息 / receiver_type + created_at for history queries
            $table->index(['receiver_type', 'created_at'], 'messages_receiver_type_created_at_index');
            // 联合索引：定向用户历史消息 / receiver_id + created_at for targeted user history
            $table->index(['receiver_id', 'created_at'], 'messages_receiver_id_created_at_index');
            $table->comment('站内消息表/In-app messages table');
        });
    }

    /**
     * 回滚迁移 / Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
