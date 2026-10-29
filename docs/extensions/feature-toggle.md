# Feature-Toggle 灰度开关扩展（feature-toggle.md）

> 按需复制。业务功能按用户/比例/白名单灰度放量。

---

## 1. 场景说明

- 新功能（如新版商品详情）先对内网白名单开，再 10% 用户，再全量；
- 不发版即可开关；
- 配置存 DB + Redis 缓存，命中即返回。

---

## 2. 迁移：feature_flags 表

```php
// database/migrations/2026_01_01_000005_create_feature_flags_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();       // new_product_detail
            $table->string('name', 128);
            $table->unsignedTinyInteger('enabled')->default(0);
            $table->unsignedTinyInteger('rollout_percent')->default(0); // 0-100
            $table->json('whitelist_user_ids')->nullable();              // 指定 user id
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('feature_flags'); }
};
```

---

## 3. 代码片段

### 3.1 FeatureFlagService

`app/Services/FeatureFlagService.php`：

```php
namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_KEY = 'feature_flags:all';
    private const CACHE_TTL = 300;

    /** @return array<string,array{enabled:int,percent:int,whitelist:array}> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return FeatureFlag::get()->mapWithKeys(fn ($f) => [
                $f->code => [
                    'enabled'   => (int) $f->enabled,
                    'percent'   => (int) $f->rollout_percent,
                    'whitelist' => $f->whitelist_user_ids ?? [],
                ],
            ])->all();
        });
    }

    public function enabled(string $code, ?int $userId = null): bool
    {
        $flags = $this->all();
        if (!isset($flags[$code]) || !$flags[$code]['enabled']) {
            return false;
        }
        $flag = $flags[$code];

        // 白名单直通
        if ($userId && in_array($userId, $flag['whitelist'], true)) {
            return true;
        }
        // 全量
        if ($flag['percent'] >= 100) {
            return true;
        }
        // 按 user_id hash 稳定分流（同一用户结果不变）
        if ($userId && $flag['percent'] > 0) {
            return (crc32((string) $userId . $code) % 100) < $flag['percent'];
        }
        return false;
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
```

### 3.2 辅助函数

`app/Helpers/common.php` 追加：

```php
function feature_enabled(string $code, ?int $userId = null): bool
{
    return app(\App\Services\FeatureFlagService::class)->enabled($code, $userId);
}
```

### 3.3 使用示例

```php
// 控制器/Service 内
if (feature_enabled('new_product_detail', $request->user()->id)) {
    return $this->newDetail($product);
}
return $this->legacyDetail($product);
```

### 3.4 后台开关接口（管理员）

```php
// app/Http/Controllers/Api/Admin/FeatureFlagController.php
public function index(FeatureFlagService $svc): JsonResponse
{
    return message('success', 200, FeatureFlag::all());
}
public function update(Request $request, int $id, FeatureFlagService $svc): JsonResponse
{
    $flag = FeatureFlag::findOrFail($id);
    $flag->update($request->only(['enabled','rollout_percent','whitelist_user_ids','name']));
    $svc->refresh();
    return message('success');
}
```

---

## 4. 使用步骤

1. 跑迁移；
2. 写 `FeatureFlagService` 与辅助函数；
3. 业务里用 `feature_enabled()` 包新老逻辑分支；
4. 后台提供管理页配置灰度；
5. 补 lang key 与权限码 `admin:feature:list/update`。

---

## 5. 注意事项

- 分流用 `crc32(userId+code)` 保证同一用户在 percent 提升前后结果稳定，不会反复横跳；
- Redis 缓存 5 分钟，后台改完立即 `refresh()`；
- 白名单用于内测/员工账号；
- 不要把开关判断埋到 SQL 里，保持在 PHP 业务层。
