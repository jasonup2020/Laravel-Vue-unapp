# Excel 异步队列导出扩展（async-export.md）

> 按需复制。大数据量导出禁止同步跑（PHP 超时/内存溢出），改为提交 Job → 后台生成 → 前端轮询 task_id 下载。

---

## 1. 场景说明

- 管理员导出订单、用户列表（10w+ 行）；
- 接口立即返回 `task_id`；队列 worker 异步生成 xlsx 落 `storage/app/exports/`；
- 前端轮询任务状态，完成后返回下载 URL。

---

## 2. 迁移：export_tasks 表

```php
// database/migrations/2026_01_01_000003_create_export_tasks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('export_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snow_id')->nullable()->unique();
            $table->string('task_type', 64);        // users|orders|products
            $table->unsignedBigInteger('admin_id');
            $table->json('filters')->nullable();
            $table->string('status', 16)->default('pending'); // pending|processing|done|failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->string('file_path', 500)->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('export_tasks'); }
};
```

---

## 3. 代码片段

### 3.1 Job — `app/Jobs/GenerateUserExportJob.php`

```php
namespace App\Jobs;

use App\Models\ExportTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateUserExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = ExportTask::findOrFail($this->taskId);
        $task->update(['status' => 'processing']);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(['ID', '姓名', '邮箱', '状态', '注册时间'], null, 'A1');

            $row = 2; $total = 0;
            \App\Models\User::query()
                ->select(['id','snow_id','name','email','status','created_at'])
                ->chunk(500, function ($users) use ($sheet, &$row, &$total) {
                    foreach ($users as $u) {
                        $sheet->fromArray([
                            $u->snow_id, $u->name, $u->email, $u->status, $u->created_at,
                        ], null, "A{$row}");
                        $row++; $total++;
                    }
                });

            $path = 'exports/users_' . $task->id . '_' . time() . '.xlsx';
            $full = storage_path('app/' . $path);
            (new Xlsx($spreadsheet))->save($full);

            $task->update([
                'status'        => 'done',
                'file_path'     => $path,
                'total_rows'    => $total,
                'completed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $task->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }
}
```

### 3.2 控制器

`app/Http/Controllers/Api/Admin/ExportController.php`：

```php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateUserExportJob;
use App\Models\ExportTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    // 提交导出
    public function users(Request $request): JsonResponse
    {
        $task = ExportTask::create([
            'task_type' => 'users',
            'admin_id'  => $request->user()->id,
            'filters'   => $request->only(['status']),
        ]);
        GenerateUserExportJob::dispatch($task->id);
        return message('success', 200, ['task_id' => $task->snow_id]);
    }

    // 轮询任务状态
    public function status(string $taskId): JsonResponse
    {
        $task = ExportTask::where('snow_id', $taskId)->firstOrFail();
        return message('success', 200, [
            'status'     => $task->status,
            'total_rows' => $task->total_rows,
            'error'      => $task->error,
            'download_url' => $task->status === 'done'
                ? '/api/admin/export/' . $task->snow_id . '/download'
                : null,
        ]);
    }

    // 下载文件
    public function download(string $taskId)
    {
        $task = ExportTask::where('snow_id', $taskId)->where('status','done')->firstOrFail();
        return Storage::download($task->file_path);
    }
}
```

路由：

```php
Route::middleware(['auth:admin-api'])->group(function () {
    Route::post('/export/users', [ExportController::class, 'users']);
    Route::get('/export/{taskId}/status', [ExportController::class, 'status']);
    Route::get('/export/{taskId}/download', [ExportController::class, 'download']);
});
```

依赖：`composer require phpoffice/phpspreadsheet`。

---

## 4. 使用步骤

1. 跑迁移；
2. 复制 Job / 控制器 / 路由；
3. 队列 worker 已由 Supervisor 守护（`queue:work`）；
4. 前端提交后每 2 秒轮询 `status`，拿到 `download_url` 后下载；
5. 计划任务每天清理 7 天前 `exports/` 目录文件与 done 记录。

---

## 5. 注意事项

- Job `chunk(500)` 避免一次性把全表加载进内存；
- `timeout=600` 与 Supervisor `queue:work --timeout=90` 协调：Job 内部 timeout 应大于外层；
- 下载接口要校验 `admin_id` 等于任务发起人（或 super-admin），防越权下别人的导出文件；
- 文件落盘后权限只读，Nginx 不解析 storage 目录里的 PHP。
