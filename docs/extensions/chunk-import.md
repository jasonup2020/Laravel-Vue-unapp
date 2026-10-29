# Excel 分块导入扩展（chunk-import.md）

> 按需复制。大文件 Excel 上传后由队列分块读取入库，避免 PHP 单次内存/超时。

---

## 1. 场景说明

- 管理员批量导入商品（一次 1w+ 行 xlsx）；
- 上传原文件落 `storage/app/imports/`，立即返回 `task_id`；
- 队列 Job 用 Spout/Spreadsheet 流式读取，按 500 行 chunk 入库，错误行收集到错误报告。

---

## 2. 迁移：import_tasks 表

```php
// database/migrations/2026_01_01_000004_create_import_tasks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snow_id')->nullable()->unique();
            $table->string('task_type', 64);            // products|users
            $table->unsignedBigInteger('admin_id');
            $table->string('file_path', 500);
            $table->string('status', 16)->default('pending'); // pending|processing|done|failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();          // [{row:12, reason:"..."}]
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('import_tasks'); }
};
```

---

## 3. 代码片段

### 3.1 Job — `app/Jobs/ChunkImportProductsJob.php`

```php
namespace App\Jobs;

use App\Models\ImportTask;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;   // composer require box/spout

class ChunkImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = ImportTask::findOrFail($this->taskId);
        $task->update(['status' => 'processing']);

        $reader = new Reader();
        $reader->open(storage_path('app/' . $task->file_path));

        $errors = [];
        $success = 0; $failed = 0; $rowIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                if ($rowIndex === 1) continue; // 表头
                $cells = $row->toArray();

                try {
                    DB::transaction(function () use ($cells) {
                        Product::create([
                            'name'  => $cells[0] ?? '',
                            'price' => (float) ($cells[1] ?? 0),
                            'status'=> 1,
                        ]);
                    });
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['row' => $rowIndex, 'reason' => mb_substr($e->getMessage(), 0, 200)];
                }
            }
        }
        $reader->close();

        $task->update([
            'status'       => 'done',
            'total_rows'   => $rowIndex - 1,
            'success_rows' => $success,
            'failed_rows'  => $failed,
            'errors'       => array_slice($errors, 0, 500), // 最多存 500 条错误
            'completed_at' => now(),
        ]);
    }
}
```

### 3.2 控制器

```php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ChunkImportProductsJob;
use App\Models\ImportTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200', // 50MB
        ]);
        $path = $request->file('file')->store('imports');

        $task = ImportTask::create([
            'task_type' => 'products',
            'admin_id'  => $request->user()->id,
            'file_path' => $path,
        ]);
        ChunkImportProductsJob::dispatch($task->id);

        return message('success', 200, ['task_id' => $task->snow_id]);
    }

    public function status(string $taskId): JsonResponse
    {
        $task = ImportTask::where('snow_id', $taskId)->firstOrFail();
        return message('success', 200, $task->only([
            'status','total_rows','success_rows','failed_rows','errors',
        ]));
    }
}
```

路由：

```php
Route::post('/import/products', [ImportController::class, 'products']);
Route::get('/import/{taskId}/status', [ImportController::class, 'status']);
```

依赖：`composer require box/spout`（流式读 xlsx，内存占用远低于 PhpSpreadsheet）。

---

## 4. 使用步骤

1. 跑迁移；
2. 复制 Job / 控制器 / 路由；
3. 前端上传 xlsx → 拿 task_id → 轮询 status；
4. 完成后展示成功/失败行数，错误行可导出给管理员校对；
5. 计划任务每天清理 7 天前 `imports/` 原文件。

---

## 5. 注意事项

- 大文件务必流式（Spout），不要用 `PhpOffice\PhpSpreadsheet\IOFactory::load()` 一次性读全表；
- 每行独立事务，单行失败不影响整体；
- 错误 JSON 最多存 500 条，超过截断；
- 上传文件校验真实 MIME，禁止 `.php` 伪装。
