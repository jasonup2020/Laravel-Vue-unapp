# system_configs 系统配置表扩展（system-configs.md）

> 按需复制。业务参数（注册开关、默认分页、客服电话等）入库 + Redis 缓存；`.env` 只存环境信息。

---

## 1. 场景说明

- 客服电话、用户协议版本、是否开放注册、默认佣金比例等动态配置；
- 后台可视化改，前端无需发版；
- Redis 缓存，写后即清。

---

## 2. 迁移：system_configs 表

```php
// database/migrations/2026_01_01_000006_create_system_configs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();       // customer_service.phone
            $table->string('value', 1000)->nullable();
            $table->string('type', 16)->default('string'); // string|int|bool|json
            $table->string('group', 64)->default('common');  // common|register|pay
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('system_configs'); }
};
```

---

## 3. 代码片段

### 3.1 SystemConfigService

`app/Services/SystemConfigService.php`：

```php
namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Cache;

class SystemConfigService
{
    private const CACHE_KEY = 'system_configs:all';
    private const CACHE_TTL = 600;

    /** @return array<string,mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return SystemConfig::get()->mapWithKeys(function ($c) {
                return [$c->key => $this->cast($c->value, $c->type)];
            })->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value, 'type' => $type],
        );
        $this->refresh();
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'int'    => (int) $value,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOL),
            'json'   => json_decode($value, true),
            default  => $value,
        };
    }
}
```

### 3.2 辅助函数

`app/Helpers/common.php`：

```php
function sys_config(string $key, mixed $default = null): mixed
{
    return app(\App\Services\SystemConfigService::class)->get($key, $default);
}
```

### 3.3 后台接口

```php
// app/Http/Controllers/Api/Admin/SystemConfigController.php
public function index(SystemConfigService $svc): JsonResponse
{
    return message('success', 200, SystemConfig::all());
}

public function update(Request $request, string $key, SystemConfigService $svc): JsonResponse
{
    $svc->set($key, $request->input('value'), $request->input('type', 'string'));
    return message('success');
}
```

路由：

```php
Route::get('/system-configs', [SystemConfigController::class, 'index']);
Route::put('/system-configs/{key}', [SystemConfigController::class, 'update']);
```

### 3.4 业务使用示例

```php
if (sys_config('register.enabled', true)) {
    // 允许注册
}
$phone = sys_config('customer_service.phone', '400-000-0000');
```

---

## 4. 使用步骤

1. 跑迁移；
2. 写 `SystemConfigService` 与辅助函数；
3. 初始化几条默认配置（Seeder）；
4. 后台配置页 CRUD；
5. 业务代码用 `sys_config('key')` 读取。

---

## 5. 注意事项

- 敏感配置（密钥、密码）仍放 `.env`，不入 system_configs；
- Redis 缓存 10 分钟，写操作立即 `refresh()`；
- bool/json 类型转换要在 Service 内统一处理；
- key 命名用点分层：`customer_service.phone`、`register.enabled`。
