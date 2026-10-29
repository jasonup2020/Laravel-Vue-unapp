<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 慢查询监听：SQL 执行耗时 > 500ms 时记录 warning 日志。
        // 仅记录日志，不阻断业务；用于提早发现缺少索引、全表扫描问题。
        DB::listen(function (QueryExecuted $query) {
            if ($query->time > 500) {
                Log::warning('Slow SQL detected (>500ms)', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }
}
