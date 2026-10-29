<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| These tasks are run by `php artisan schedule:work` (dev) or the system
| cron entry `* * * * * php artisan schedule:run` (production).
|
*/

// 每分钟：清理过期 IP 黑名单。
// - 作废黑名单活动缓存（让 IpBlacklist 中间件下次重新查表）
// - 物理删除已过期的黑名单行（expire_at 不为空且早于当前时间）
Schedule::call(function () {
    Cache::forget('ip_blacklist:active');
    DB::table('ip_blacklist')
        ->whereNotNull('expire_at')
        ->where('expire_at', '<', now())
        ->delete();
})->everyMinute()->name('clean-expired-ip-blacklist')->withoutOverlapping();

// 每小时：归档 30 天前已读站内消息到 messages_archive 表。
// 若 messages_archive 表尚不存在（迁移未建），则跳过本次并记录日志，
// 不中断调度链。
Schedule::call(function () {
    if (! Schema::hasTable('messages_archive')) {
        // 归档表尚未建立，跳过；待迁移落地后此任务自动生效。
        return;
    }

    $cutoff = now()->subDays(30);

    $archived = DB::transaction(function () use ($cutoff) {
        $rows = DB::table('messages')
            ->where('is_read', 1)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        DB::table('messages_archive')->insert(
            $rows->map(fn ($row) => (array) $row)->all()
        );

        DB::table('messages')
            ->where('is_read', 1)
            ->where('created_at', '<', $cutoff)
            ->delete();

        return $rows->count();
    });

    if ($archived > 0) {
        Log::info("Archived {$archived} read messages older than 30 days.");
    }
})->hourly()->name('archive-read-messages')->withoutOverlapping();

// 每 10 分钟：清理幂等缓存。
// 幂等记录以 idempotency:{method}:{path}:{...}:{key} 为 key，前缀不可枚举
// （Cache store 不支持 scan），故这里依赖写入时的短 TTL
// （config('app.idempotency_ttl',600)）自然过期；此任务仅做兜底探针日志，
// 不做全量删除。
Schedule::call(function () {
    // 幂等缓存靠 TTL 自然过期，无需主动删除；此处保留钩子便于未来扩展。
    Log::debug('Idempotency cache sweep tick (TTL-based expiry).');
})->everyTenMinutes()->name('sweep-idempotency-cache')->withoutOverlapping();

// 每日：清理过期审计日志（file 驱动）。
// - 文本型审计日志按天分文件存放在 storage/logs/audit/audit-YYYY-MM-DD.log；
//   超过 config('audit.retention_days', 90) 天的历史文件物理删除，避免磁盘膨胀。
// - ES 驱动不在此清理（ES 侧按索引滚动删除，见 docs/deploy.md 审计章节）。
Schedule::call(function () {
    $dir = storage_path('logs/audit');
    if (! is_dir($dir)) {
        return;
    }

    $retentionDays = (int) config('audit.retention_days', 90);
    $cutoff = now()->subDays($retentionDays)->startOfDay();

    $removed = 0;
    foreach (glob($dir . '/audit-*.log') ?: [] as $file) {
        // 文件名形如 audit-YYYY-MM-DD.log，解析日期判断保留期
        if (preg_match('/audit-(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $m)
            && \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $m[1])->lt($cutoff)
            && @unlink($file)) {
            $removed++;
        }
    }

    if ($removed > 0) {
        Log::info("Cleaned {$removed} audit log file(s) older than {$retentionDays} days.");
    }
})->daily()->name('clean-expired-audit-logs')->withoutOverlapping();
