<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * make:module code generator/代码生成器命令
 *
 * Scaffolds a full C-end + Admin module: migration, Model, Repository,
 * User/Admin Services, User/Admin Controllers, FormRequests, lang files
 * and appends route registration to routes/api.php.
 * 一次性生成 C 端 + 管理端完整模块：迁移、Model、Repository、User/Admin Service、
 * User/Admin Controller、FormRequest、双语 lang 文件，并向 routes/api.php 末尾追加路由注册块。
 */
class MakeModuleCommand extends BaseCommand
{
    /**
     * The name and signature of the console command/命令签名
     *
     * {name}        StudlyCase module name, e.g. Notices / UserFavorite
     * {--with-admin}  Generate admin-side files (default ON; pass --no-with-admin to skip)
     * {--soft-deletes} Add softDeletes() to migration and use SoftDeletes in model
     */
    protected $signature = 'make:module {name : StudlyCase module name, e.g. Notices}
                            {--with-admin : Generate admin-side CRUD (default on; use --no-with-admin to skip)}
                            {--soft-deletes : Add soft deletes column}';

    /**
     * The console command description/命令描述
     */
    protected $description = 'Scaffold a full module (migration/model/repository/service/controller/request/lang/routes)/一键生成模块全层代码';

    /**
     * Base path of the Laravel app/后端根目录
     */
    protected string $basePath;

    /**
     * Execute the console command/执行命令
     */
    public function handle(): int
    {
        $this->basePath = base_path();

        $name = trim((string) $this->argument('name'));
        $studly = Str::studly($name);
        $snake = Str::snake($studly);                    // e.g. notices / user_favorite
        $snakeSingular = Str::singular($snake);          // e.g. notice / user_favorite
        $snakePlural = Str::plural($snakeSingular);      // e.g. notices / user_favorites
        $table = $snakePlural;                           // e.g. notices / user_favorites
        $routePrefix = $snakePlural;                     // URI prefix
        $langFile = $snakeSingular;                      // lang file name
        $permModule = $snakeSingular;                    // permission module segment

        // --with-admin defaults to TRUE; --no-with-admin explicitly disables.
        // Symfony boolean flags report false both when absent AND when
        // --no-with-admin is passed, so we probe the raw input for the negation.
        // --with-admin 默认开启；仅当显式传 --no-with-admin 时才关闭。
        $withAdmin = ! $this->input->hasParameterOption('--no-with-admin', true);
        $withSoftDeletes = (bool) $this->option('soft-deletes');

        $this->info("Generating module: {$studly} (table={$table}, admin=" . ($withAdmin ? 'on' : 'off') . ", softDeletes=" . ($withSoftDeletes ? 'on' : 'off') . ")");

        $generated = [];

        // 1. Migration
        $migrationFile = $this->writeMigration($studly, $table, $withSoftDeletes);
        $generated[] = $migrationFile;

        // 2. Model
        $generated[] = $this->writeModel($studly, $withSoftDeletes);

        // 3. Repository
        $generated[] = $this->writeRepository($studly, $snakeSingular);

        // 4. Services
        $generated[] = $this->writeUserService($studly);
        if ($withAdmin) {
            $generated[] = $this->writeAdminService($studly);
        }

        // 5. Controllers
        $generated[] = $this->writeUserController($studly, $snakeSingular);
        if ($withAdmin) {
            $generated[] = $this->writeAdminController($studly, $snakeSingular);
        }

        // 6. FormRequests
        $generated[] = $this->writeUserIndexRequest($studly);
        if ($withAdmin) {
            $generated[] = $this->writeAdminStoreRequest($studly);
            $generated[] = $this->writeAdminUpdateRequest($studly);
        }

        // 7. Lang files
        $generated[] = $this->writeLang($langFile, 'zh-CN');
        $generated[] = $this->writeLang($langFile, 'en');

        // 8. Routes (append to routes/api.php, deduped)
        $routeMsg = $this->appendRoutes($studly, $routePrefix, $permModule, $withAdmin);
        $this->line($routeMsg);

        // Summary
        $this->newLine();
        $this->info('Generated files/生成文件：');
        foreach ($generated as $f) {
            $this->line('  - ' . $this->relative($f));
        }

        $this->newLine();
        $this->warn('Next steps/下一步：');
        $this->line("  1. Edit migration to add business fields/编辑迁移补充业务字段: database/migrations/*_create_{$table}_table.php");
        $this->line("  2. Add permission codes to PermissionSeeder/在 PermissionSeeder 补权限码: admin:{$permModule}:list/create/update/delete");
        $this->line('  3. Run migration after review/确认后执行迁移: php artisan migrate');
        $this->line('  4. Update OpenAPI doc and Pest tests/补充 OpenAPI 文档与 Pest 用例');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // File writers/各文件生成器
    // ---------------------------------------------------------------------

    /**
     * Write migration file/写迁移文件
     */
    protected function writeMigration(string $studly, string $table, bool $softDeletes): string
    {
        $stamp = date('Y_m_d_His');
        $file = "database/migrations/{$stamp}_create_{$table}_table.php";
        $path = $this->basePath . '/' . $file;

        $softDeletesLine = $softDeletes ? "            \$table->softDeletes()->comment('Soft delete at/软删除时间');\n" : '';

        $template = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

/**
 * Create {$table} table/创建 {$table} 表
 *
 * Auto-generated by make:module; add business fields as needed.
 * 由 make:module 自动生成；请按需补充业务字段。
 */
return new class extends Migration
{
    /**
     * Run the migrations/执行迁移
     */
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->bigIncrements('id')->comment('Primary auto-increment ID/自增主键');
            \$table->unsignedBigInteger('snow_id')->nullable()->unique()->comment('Snowflake public ID/对外雪花ID（接口仅暴露此ID）');
            \$table->string('name', 100)->comment('Name/名称');
            \$table->string('title', 200)->nullable()->comment('Title/标题');
            \$table->tinyInteger('status')->default(1)->comment('Status 1=enabled 0=disabled/状态 1=启用 0=禁用');
            \$table->string('remark', 500)->nullable()->comment('Remark/备注');
            \$table->timestamps();
{$softDeletesLine}            // Index: status for list filtering/索引：status 支撑列表筛选
            \$table->index('status', '{$table}_status_index');
            // Index: created_at for time-range paging/索引：created_at 支撑时间范围分页
            \$table->index('created_at', '{$table}_created_at_index');
        });
    }

    /**
     * Reverse the migrations/回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write model file/写模型文件
     *
     * When $softDeletes is true, emits `use SoftDeletes;` in the class body
     * (matching the migration's softDeletes() column). When false, no trait
     * is added — tables without deleted_at must NOT use SoftDeletes.
     * $softDeletes=true 时在类体中 `use SoftDeletes;`（与迁移 softDeletes() 列对应）；
     * false 时不加 trait——无 deleted_at 列的表不能引入 SoftDeletes。
     */
    protected function writeModel(string $studly, bool $softDeletes): string
    {
        $file = "app/Models/{$studly}.php";
        $path = $this->basePath . '/' . $file;

        $useSoftDeletesImport = $softDeletes
            ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n"
            : '';
        $useSoftDeletesTrait = $softDeletes
            ? "    use SoftDeletes;\n\n"
            : '';

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Models;

use App\\Base\\BaseModel;
{$useSoftDeletesImport}
/**
 * {$studly} model/{$studly} 模型
 */
class {$studly} extends BaseModel
{
{$useSoftDeletesTrait}    /**
     * The attributes that are mass assignable/可批量赋值字段
     *
     * @var list<string>
     */
    protected \$fillable = [
        'name',
        'title',
        'status',
        'remark',
    ];

    /**
     * Field comments mirroring migration ->comment()/字段注释（与迁移 comment 呼应）
     *
     * @return array<string,string>
     */
    public function attributeComments(): array
    {
        return [
            'name'   => 'Name/名称',
            'title'  => 'Title/标题',
            'status' => 'Status 1=enabled 0=disabled/状态 1=启用 0=禁用',
            'remark' => 'Remark/备注',
        ];
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write repository file/写 Repository 文件
     */
    protected function writeRepository(string $studly, string $snakeSingular): string
    {
        $file = "app/Repositories/{$studly}Repository.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Repositories;

use App\\Base\\BaseRepository;
use App\\Models\\{$studly};

/**
 * {$studly} repository/{$studly} 数据访问层
 */
class {$studly}Repository extends BaseRepository
{
    /**
     * Constructor/构造函数
     */
    public function __construct()
    {
        parent::__construct({$studly}::class);
    }

    /**
     * Cache key for list read/列表读缓存 key
     *
     * Return null to disable cache; override TTL in service if needed.
     * 返回 null 关闭缓存；如需启用，在 Service 中调整 TTL。
     */
    protected function cacheKey(): ?string
    {
        return 'module.{$snakeSingular}:list';
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write C-end service/写 C 端 Service
     */
    protected function writeUserService(string $studly): string
    {
        $file = "app/Services/User/{$studly}Service.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Services\\User;

use App\\Base\\BaseService;
use App\\Repositories\\{$studly}Repository;

/**
 * C-end {$studly} service (readonly)/C 端 {$studly} 服务（只读）
 *
 * Only index/show are exposed to C-end; write operations belong to Admin service.
 * C 端仅暴露 index/show；写操作归 Admin 服务。
 */
class {$studly}Service extends BaseService
{
    /**
     * Constructor/构造函数
     */
    public function __construct({$studly}Repository \$repo)
    {
        \$this->repository = \$repo;
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write admin service/写管理端 Service
     */
    protected function writeAdminService(string $studly): string
    {
        $file = "app/Services/Admin/{$studly}Service.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Services\\Admin;

use App\\Base\\BaseService;
use App\\Repositories\\{$studly}Repository;

/**
 * Admin {$studly} service (full CRUD)/管理端 {$studly} 服务（全量 CRUD）
 */
class {$studly}Service extends BaseService
{
    /**
     * Constructor/构造函数
     */
    public function __construct({$studly}Repository \$repo)
    {
        \$this->repository = \$repo;
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write C-end controller/写 C 端 Controller
     */
    protected function writeUserController(string $studly, string $snakeSingular): string
    {
        $file = "app/Http/Controllers/Api/User/{$studly}Controller.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\User;

use App\\Base\\BaseController;
use App\\Http\\Requests\\User\\{$studly}IndexRequest;
use App\\Services\\User\\{$studly}Service;
use Illuminate\\Http\\JsonResponse;

/**
 * C-end {$studly} controller (readonly)/C 端 {$studly} 控制器（只读）
 */
class {$studly}Controller extends BaseController
{
    /**
     * Constructor/构造函数
     */
    public function __construct(protected {$studly}Service \$service) {}

    /**
     * Paginated list/分页列表
     */
    public function index({$studly}IndexRequest \$request): JsonResponse
    {
        [\$page, \$pageSize] = \$this->parsePageParams(\$request);
        \$data = \$this->service->index([
            'page'      => \$page,
            'page_size' => \$pageSize,
            'filters'   => \$request->validated(),
        ]);

        return \$this->success(\$data, '{$snakeSingular}.success');
    }

    /**
     * Show one by snow_id/按 snow_id 详情
     */
    public function show(string \$snowId): JsonResponse
    {
        \$model = \$this->service->show((int) \$snowId);

        return \$this->success(\$model, '{$snakeSingular}.success');
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write admin controller/写管理端 Controller
     */
    protected function writeAdminController(string $studly, string $snakeSingular): string
    {
        $file = "app/Http/Controllers/Api/Admin/{$studly}Controller.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\Admin;

use App\\Base\\BaseController;
use App\\Http\\Requests\\Admin\\{$studly}StoreRequest;
use App\\Http\\Requests\\Admin\\{$studly}UpdateRequest;
use App\\Http\\Requests\\User\\{$studly}IndexRequest;
use App\\Services\\Admin\\{$studly}Service;
use Illuminate\\Http\\JsonResponse;

/**
 * Admin {$studly} controller (CRUD)/管理端 {$studly} 控制器（CRUD）
 */
class {$studly}Controller extends BaseController
{
    /**
     * Constructor/构造函数
     */
    public function __construct(protected {$studly}Service \$service) {}

    /**
     * Paginated list/分页列表
     */
    public function index({$studly}IndexRequest \$request): JsonResponse
    {
        [\$page, \$pageSize] = \$this->parsePageParams(\$request);
        \$data = \$this->service->index([
            'page'      => \$page,
            'page_size' => \$pageSize,
            'filters'   => \$request->validated(),
        ]);

        return \$this->success(\$data, '{$snakeSingular}.success');
    }

    /**
     * Show one by snow_id/按 snow_id 详情
     */
    public function show(string \$snowId): JsonResponse
    {
        \$model = \$this->service->show((int) \$snowId);

        return \$this->success(\$model, '{$snakeSingular}.success');
    }

    /**
     * Create/新建
     */
    public function store({$studly}StoreRequest \$request): JsonResponse
    {
        \$model = \$this->service->store(\$request->validated());

        return \$this->success(\$model, '{$snakeSingular}.saved');
    }

    /**
     * Update by snow_id/按 snow_id 更新
     */
    public function update({$studly}UpdateRequest \$request, string \$snowId): JsonResponse
    {
        \$model = \$this->service->update((int) \$snowId, \$request->validated());

        return \$this->success(\$model, '{$snakeSingular}.updated');
    }

    /**
     * Soft-delete by snow_id/按 snow_id 软删除
     */
    public function destroy(string \$snowId): JsonResponse
    {
        \$this->service->destroy((int) \$snowId);

        return \$this->success(null, '{$snakeSingular}.deleted');
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write C-end index request/写 C 端列表请求校验
     */
    protected function writeUserIndexRequest(string $studly): string
    {
        $file = "app/Http/Requests/User/{$studly}IndexRequest.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Http\\Requests\\User;

use Illuminate\\Foundation\\Http\\FormRequest;

/**
 * {$studly} list request/{$studly} 列表请求校验
 */
class {$studly}IndexRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'page'      => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
            'status'    => 'nullable|integer|in:0,1',
        ];
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write admin store request/写管理端新增请求校验
     */
    protected function writeAdminStoreRequest(string $studly): string
    {
        $file = "app/Http/Requests/Admin/{$studly}StoreRequest.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Http\\Requests\\Admin;

use Illuminate\\Foundation\\Http\\FormRequest;

/**
 * Admin {$studly} store request/管理端 {$studly} 新增请求校验
 */
class {$studly}StoreRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:100',
            'title'  => 'nullable|string|max:200',
            'status' => 'required|integer|in:0,1',
            'remark' => 'nullable|string|max:500',
        ];
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write admin update request/写管理端更新请求校验
     */
    protected function writeAdminUpdateRequest(string $studly): string
    {
        $file = "app/Http/Requests/Admin/{$studly}UpdateRequest.php";
        $path = $this->basePath . '/' . $file;

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Http\\Requests\\Admin;

use Illuminate\\Foundation\\Http\\FormRequest;

/**
 * Admin {$studly} update request/管理端 {$studly} 更新请求校验
 */
class {$studly}UpdateRequest extends FormRequest
{
    /**
     * Authorize/权限校验
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules/校验规则
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name'   => 'sometimes|required|string|max:100',
            'title'  => 'nullable|string|max:200',
            'status' => 'sometimes|required|integer|in:0,1',
            'remark' => 'nullable|string|max:500',
        ];
    }
}
PHP;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $template);

        return $path;
    }

    /**
     * Write lang file/写语言文件
     */
    protected function writeLang(string $langFile, string $locale): string
    {
        $dir = $this->basePath . "/lang/{$locale}";
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$langFile}.php";

        if ($locale === 'zh-CN') {
            $template = <<<PHP
<?php

// {$langFile} module lang pack/模块语言包
return [
    'success'    => '操作成功',
    'not_found'  => '记录不存在',
    'saved'      => '创建成功',
    'updated'    => '更新成功',
    'deleted'    => '删除成功',
];
PHP;
        } else {
            $template = <<<PHP
<?php

// {$langFile} module lang pack/模块语言包
return [
    'success'    => 'Operation success',
    'not_found'  => 'Resource not found',
    'saved'      => 'Created successfully',
    'updated'    => 'Updated successfully',
    'deleted'    => 'Deleted successfully',
];
PHP;
        }

        File::put($path, $template);

        return $path;
    }

    /**
     * Append route registration block to routes/api.php/向 routes/api.php 追加路由注册块
     *
     * Skips when a block for this module already exists.
     * 已存在同名注册块时跳过，避免重复。
     */
    protected function appendRoutes(string $studly, string $routePrefix, string $permModule, bool $withAdmin): string
    {
        $routeFile = $this->basePath . '/routes/api.php';
        $existing = File::get($routeFile);

        $marker = "// ===== Module: {$studly} (generated by make:module) =====";
        if (str_contains($existing, $marker)) {
            return "Routes already registered for {$studly}, skipped/路由已存在，跳过追加";
        }

        $userCtrl = "\\App\\Http\\Controllers\\Api\\User\\{$studly}Controller::class";
        $adminCtrl = "\\App\\Http\\Controllers\\Api\\Admin\\{$studly}Controller::class";

        $block = "\n" . $marker . "\n";
        $block .= "// C-end readonly/C 端只读\n";
        $block .= "Route::get('/{$routePrefix}', [{$userCtrl}, 'index']);\n";
        $block .= "Route::get('/{$routePrefix}/{snowId}', [{$userCtrl}, 'show']);\n";

        if ($withAdmin) {
            $block .= "\n// Admin CRUD/管理端 CRUD\n";
            $block .= "Route::middleware('auth:admin-api')->prefix('admin/{$routePrefix}')->group(function () {\n";
            $block .= "    Route::get('/', [{$adminCtrl}, 'index'])->middleware('admin.permission:admin:{$permModule}:list');\n";
            $block .= "    Route::post('/', [{$adminCtrl}, 'store'])->middleware('admin.permission:admin:{$permModule}:create,admin.operation.log:0,idempotency');\n";
            $block .= "    Route::put('/{snowId}', [{$adminCtrl}, 'update'])->middleware('admin.permission:admin:{$permModule}:update,admin.operation.log:0,idempotency');\n";
            $block .= "    Route::delete('/{snowId}', [{$adminCtrl}, 'destroy'])->middleware('admin.permission:admin:{$permModule}:delete,admin.operation.log:0');\n";
            $block .= "});\n";
        }

        File::append($routeFile, $block);

        return "Routes appended to routes/api.php/路由已追加到 routes/api.php";
    }

    /**
     * Convert absolute path to project-relative path/转项目相对路径
     */
    protected function relative(string $abs): string
    {
        return ltrim(str_replace($this->basePath, '', $abs), DIRECTORY_SEPARATOR);
    }

    /**
     * Command examples/命令示例
     *
     * @return array<int,array{cmd:string,desc:string}>
     */
    protected function examples(): array
    {
        return [
            ['cmd' => 'make:module Notices', 'desc' => 'Generate Notices module (C-end readonly + admin CRUD)/生成 Notices 模块（C 端只读 + 管理端 CRUD）'],
            ['cmd' => 'make:module Notices --soft-deletes', 'desc' => 'Add deleted_at column/加软删除列'],
            ['cmd' => 'make:module UserFavorite --no-with-admin', 'desc' => 'Only generate C-end files/仅生成 C 端文件'],
            ['cmd' => 'make:module Notices --show-full-help', 'desc' => 'Show this bilingual help/显示本完整双语帮助'],
        ];
    }
}
