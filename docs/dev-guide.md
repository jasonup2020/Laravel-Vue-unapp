# 二开开发规范（dev-guide.md）

> 本文面向在本骨架上做二次开发的工程师。新增业务前请先读完「新增接口的完整步骤」与「编码约束全表」。

---

## 1. 目录结构说明

```
backend-laravel13/
├── app/
│   ├── Helpers/
│   │   ├── ApiHelper.php          # message() 统一响应助手
│   │   └── common.php             # snow_id()/client_ip()/client_fingerprint()/mask_text()
│   ├── Enums/                     # BackedEnum：UserStatus/AdminStatus/ProductStatus/
│   │                              #   DictStatus/MessageReceiverType/MessageType/OperationRisk/ErrorCode
│   ├── Support/Snowflake.php     # 雪花 ID 生成
│   ├── Exceptions/                 # ApiException + ApiExceptionHandler（全局 JSON 渲染）
│   ├── Models/                     # 业务模型平铺（唯一例外 AdminFavorite 指向 user_favorites）
│   │   └── Traits/HasSnowId.php  # creating 事件自动写 snow_id
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── HealthController.php
│   │   │   ├── User/              # C 端控制器（App\Http\Controllers\Api\User\*）
│   │   │   └── Admin/            # 管理员控制器（App\Http\Controllers\Api\Admin\*）
│   │   ├── Middleware/            # 8 个自定义中间件
│   │   └── Requests/
│   │       ├── User/               # C 端 FormRequest
│   │       └── Admin/             # 管理员 FormRequest
│   ├── Services/
│   │   ├── User/                  # C 端业务服务层
│   │   └── Admin/                 # 管理员业务服务层
│   └── Events/MessageSent.php     # ShouldBroadcast（Reverb 推送）
├── database/
│   ├── migrations/                 # 全部表结构迁移（改表必须写迁移，禁止直接改库）
│   ├── seeders/                   # DatabaseSeeder/AdminSeeder/PermissionSeeder/RoleSeeder/MenuSeeder/DemoSeeder
│   └── factories/                 # 供 Pest 测试
├── lang/
│   ├── zh-CN/api.php
│   └── en/api.php
├── openapi/openapi.yaml            # OpenAPI3 接口文档
├── routes/
│   ├── api.php                     # C 端 /api/* 与管理员 /api/admin/*
│   ├── channels.php                # Reverb 频道鉴权
│   └── console.php                 # schedule 计划任务
└── config/                         # auth/jwt/cors/broadcasting/reverb
```

命名空间约定：

- C 端控制器 `App\Http\Controllers\Api\User\*`，服务 `App\Services\User\*`，请求 `App\Http\Requests\User\*`。
- 管理员控制器 `App\Http\Controllers\Api\Admin\*`，服务 `App\Services\Admin\*`，请求 `App\Http\Requests\Admin\*`。
- 模型统一平铺 `App\Models\*`。

---

## 2. 新增接口的完整步骤（以 C 端「我的收货地址」为例）

> 目标：新增 `POST /api/addresses` 创建收货地址。

### 2.1 写迁移（改表先写迁移）

```powershell
& "D:\BtSoft\php\83\php.exe" artisan make:migration create_addresses_table
```

迁移内（MySQL 5.7 兼容写法，所有业务表统一加 snow_id + timestamps，软删加 softDeletes）：

```php
Schema::create('addresses', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('snow_id')->nullable()->unique(); // 对外只暴露 snow_id
    $table->unsignedBigInteger('user_id');
    $table->string('contact_name', 64);
    $table->string('mobile', 20);
    $table->string('province', 64);
    $table->string('city', 64);
    $table->string('district', 64);
    $table->string('detail', 255);
    $table->boolean('is_default')->default(false);
    $table->timestamps();
    $table->softDeletes();
    $table->index('user_id');
});
```

执行迁移（由集成阶段统一跑，本地自行验证可用）：

```powershell
& "D:\BtSoft\php\83\php.exe" artisan migrate
```

### 2.2 写 Model（`app/Models/Address.php`）

```php
namespace App\Models;

use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasSnowId, SoftDeletes;

    protected $fillable = [
        'user_id', 'contact_name', 'mobile',
        'province', 'city', 'district', 'detail', 'is_default',
    ];

    protected $hidden = ['id'];   // 自增 id 永不对外

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
```

### 2.3 写 FormRequest（`app/Http/Requests/User/AddressStoreRequest.php`）

```php
namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class AddressStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_name' => 'required|string|max:64',
            'mobile'       => 'required|string|regex:/^1[3-9]\d{9}$/',
            'province'     => 'required|string|max:64',
            'city'         => 'required|string|max:64',
            'district'     => 'required|string|max:64',
            'detail'       => 'required|string|max:255',
            'is_default'   => 'sometimes|boolean',
        ];
    }
}
```

> 校验失败由全局异常处理器统一返回（HTTP 200 + body.code=1005），`data` 为字段错误明细，控制器无需手写。

### 2.4 写 Service（`app/Services/User/AddressService.php`）

> 硬约束：Service 内**禁止读 `auth()` / `request()` 单例**，身份 ID 由控制器传入。

```php
namespace App\Services\User;

use App\Exceptions\ApiException;
use App\Models\Address;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /** @return array<string,mixed> */
    public function create(int $userId, array $data): array
    {
        return DB::transaction(function () use ($userId, $data) {
            if (!empty($data['is_default'])) {
                Address::where('user_id', $userId)->update(['is_default' => false]);
            }
            $address = Address::create(array_merge($data, ['user_id' => $userId]));
            return $address->only(['snow_id', 'contact_name', 'mobile', 'is_default']);
        });
    }
}
```

### 2.5 写 Controller（`app/Http/Controllers/Api/User/AddressController.php`）

```php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AddressStoreRequest;
use App\Services\User\AddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $svc) {}

    public function store(AddressStoreRequest $request): JsonResponse
    {
        $userId = $request->user()->id;          // 控制器取身份，传入 Service
        $data   = $this->svc->create($userId, $request->validated());
        return message('success', 200, $data);   // message() 全局助手
    }
}
```

### 2.6 挂路由（`routes/api.php`，C 端组内）

```php
Route::middleware(['auth:api', 'check.user.online'])->group(function () {
    // ...已有路由
    Route::post('/addresses', [AddressController::class, 'store'])
        ->middleware('idempotency');   // 写接口按需挂幂等
});
```

> 全局已挂 `ip.blacklist`、`lang`、`api.composite.rate`（**无参，限流参数后台配置，见 2.10**）。
> 管理员路由挂 `admin.permission:{resource}:{action}` 精确动作权限（见 2.11）。

### 2.10 配置限流规则（后台 rate_limit_rules，不改路由）

限流次数/时长**不写死在路由**。新接口如需独立限流，到后台「限流规则」页新增一条 `rate_limit_rules`：

- `rule_key` 填路由名（如 `addresses.store`）或 path（`/api/addresses`）；
- 填 `max_attempts` / `decay_seconds`，`enabled=1`；
- 保存后自动清 `rate_limit:rules` 缓存，**即时生效、多实例共享**。

运行时解析顺序：`rule_key==routeName` → `rule_key==path` → `api:global` / `admin:global` → 兜底 60/60。代码仍可临时 `api.composite.rate:10,60` 覆盖，但默认不用。

### 2.11 动作级权限约定

管理员接口按 `{resource}:{action}` 挂 `admin.permission`：

- 四基本动作：`list/create/update/delete`；
- 自定义动作（`export/audit/kick/import` 等）在后台「权限管理」新建（type=custom），再挂到角色；
- 菜单 `permission_code` 绑 `list` 权限作侧边栏可见性；
- 「能查」不等于「能改」——一个接口一个精确 code。

### 2.12 审计驱动化说明

管理员写接口挂 `admin.operation.log:{0|1}` 后，日志**不落 MySQL**，由 `OperationLogService` 按 `AUDIT_LOG_DRIVER` 分发：

- `file`（默认）：`storage/logs/audit/audit-YYYY-MM-DD.log` 每行一条 JSON；
- `elasticsearch`（可选）：索引 `audit_logs-YYYY.MM.DD`；
- 保留期 `AUDIT_RETENTION_DAYS=90`，schedule 每日清理；
- 新增业务模块无需写审计代码，挂中间件即可。

### 2.7 补 lang key

在 `lang/zh-CN/api.php` 与 `lang/en/api.php` 增补（文案由基础层统一维护，本侧只在回报中列出待补 key）：

```php
// zh-CN
'address.saved' => '地址创建成功',
// en
'address.saved' => 'Address created',
```

控制器把 `'success'` 换成 `'address.saved'`。

### 2.8 更新 OpenAPI

在 `openapi/openapi.yaml` 增补该接口的 path、请求体、响应 schema。

### 2.9 写测试

`tests/Feature/Api/AddressTest.php`：未登录（body.code=1002）、创建成功（code=0）、手机号格式错误（code=1005）、重复提交幂等（code=1007）。

```powershell
& "D:\BtSoft\php\83\php.exe" artisan test
# 或集成阶段用 ./vendor/bin/pest
```

---

## 3. 编码约束全表（契约第 10 节，硬约束）

| # | 约束 | 反例 → 正例 |
|---|---|---|
| 1 | 状态/类型一律 BackedEnum | `status=1` → `UserStatus::Active->value` |
| 2 | 禁止 `Model::all()`；列表分页 `page/page_size`，`page_size ≤ 100` | `Product::all()` → `Product::query()->paginate(min(request('page_size',15),100))` |
| 3 | 禁止 N+1，强制 `with()` 预加载 | `$list->each(fn($p)=>$p->category)` → `Product::with('category')->...` |
| 4 | 多表写操作必须 `DB::transaction()` | 多步 insert/update 包事务 |
| 5 | 对外接口禁止暴露自增 `id`（模型 `$hidden` 已隐藏；入参 `{id}` 一律按 `snow_id` 查询） | `Product::find($id)` → `Product::where('snow_id',$id)->firstOrFail()` |
| 6 | 业务模型统一 `SoftDeletes`（`dicts` 表无软删列可物理删） | 物理 delete → `->delete()` 软删，恢复走回收站 |
| 7 | 提示文案一律 lang key，禁止硬编码中文 | `return '操作成功'` → `message('success')` |
| 8 | 控制器只做入参出参；业务逻辑在 Service；入参用 FormRequest | 控制器里写大段业务 → 下沉 Service |
| 9 | Service 不读 `auth()/request()`，身份 ID 由控制器传入 | Service 内 `auth()->id()` → 方法签名收 `int $userId` |
| 10 | 所有 Redis 交互走 `Cache` facade（默认 store）；**禁止 `Redis::` 门面直用**（广播/队列连接例外） | `Redis::set(...)` → `Cache::put(...)` / `Cache::remember(...)` |
| 11 | 分页响应结构统一 `{list,total,page,page_size}` | 直接 `paginate()` 返回 → 手动映射 |
| 12 | 密码字段写日志一律 `***` | 日志打密码 → 中间件已脱敏，业务日志同样处理 |
| 13 | FormRequest 校验失败由全局处理器转 HTTP 200 + body.code=1005（data=errors） | 控制器自己 abort → 抛 ValidationException 自动处理 |
| 14 | Windows 文件 UTF-8 无 BOM；PHP 文件 `<?php` 开头 | 存成带 BOM UTF-8 → 用 Write 工具默认无 BOM |

---

## 4. 中间件挂载速查表

| 别名 | 作用 | 典型挂载位置 |
|---|---|---|
| `lang` | 读 `Accept-Language`/`?lang=`，白名单 zh-CN/en | 全局 prepend |
| `ip.blacklist` | 命中 `ip_blacklist` 表 + Redis 缓存 → body.code=1003 `api.ip.blocked` | 全局 prepend（最外层） |
| `api.composite.rate:{max},{decay}` | 复合限流（path+IP+uid/fp），超限 body.code=1006 + `X-RateLimit-*` 头 | 路由组默认走后台 `rate_limit_rules`；显式参数可临时覆盖 |
| `auth:api` | C 端 JWT 守卫 | C 端受保护路由 |
| `auth:admin-api` | 管理员 JWT 守卫 | 全部 `/api/admin/*` 受保护路由 |
| `check.user.online` | 校验 token 是否在 `user:token:list:{uid}` 内，被踢 → body.code=1002 `api.account.kicked` | C 端鉴权路由 |
| `admin.permission:{code}` | 校验管理员权限码（`super-admin` 旁路），无权 body.code=1003 `api.forbidden` | 管理员写/读敏感接口 |
| `idempotency` | `X-Idempotency-Key` 头，POST/PUT/DELETE 生效，重复返回缓存结果 | 写接口（改密码、收藏、踢人、菜单写等） |
| `response.desensitize` | 递归脱敏 mobile/id_card；`Skip-Desensitize:1` 跳过 | C 端 `/api/me` 等含敏感字段接口 |
| `admin.operation.log:{0\|1}` | 记录管理员操作日志，`1`=高危（改密/角色/踢人） | 管理员写接口 |

---

## 5. 缓存清理规则

| 缓存 | Key | TTL | 清理触发 |
|---|---|---|---|
| 管理员菜单树 | `admin_menu:tree:{adminId}` | 604800（7 天） | ① 菜单增/删/改 → 遍历全部启用 admin 逐个 forget；② 角色绑定菜单变更 → 该角色下全部 admin；③ 管理员所属角色变更 → 仅该 admin（同时清 `admin:permissions:{adminId}`） |
| 管理员权限集合 | `admin:permissions:{adminId}` | 600 | 管理员角色变更 / 角色权限变更时清理 |
| 字典选项 | `dict:{type}` | 建议 3600 | 字典增/删/改后 `Cache::forget('dict:'.$type)` |
| IP 黑名单活动集 | `ip_blacklist:active` | 300 | 后台新增/解除封禁后 forget |
| 限流计数 | `composite_rate:{path}:{ip}:{uid|fp}` | decay 秒 | 自动过期，不手动清 |
| 幂等记录 | `idempotency:{method}:{path}:{uid|ip}:{key}` | `config('app.idempotency_ttl',600)` | 自动过期 |

> 大量管理员场景下，「遍历全部 admin 清菜单 key」性能差时，改用**版本号策略**：在 Redis 存 `admin_menu:version`，读缓存时把版本号拼进 key，清缓存只需 `INCR` 版本号，避免 `SCAN` 批量删 key。

---

## 6. 统一响应状态码约定（HTTP 一律 200 + body.code）

> **定稿规则**：所有业务接口 **HTTP 状态码一律 200**，真实结果由响应体 `code` 表达；前端 axios 拦截器按 `body.code` 判断（0=成功），非 0 弹 `msg`，**不依赖 HTTP status**。
> 例外：基础设施/健康检查（`GET /api/health`、Nginx/网关层错误如 413/502）可保留真实 HTTP 状态。

### 6.1 响应结构

```json
{
  "code": 0,
  "msg": "操作成功",
  "data": { }
}
```

- `code:int`：0 成功，非 0 失败；
- `msg:string`：多语言翻译文案（由 `message()` 经 `__()` 翻译）；
- `data:object|null`：业务数据；校验失败时为字段错误明细。

### 6.2 系统状态码表（`App\Enums\AppCode`，BackedEnum:int）

| code | 含义 | lang key |
|---|---|---|
| 0 | 成功 | `api.success` |
| 1 | 通用失败 | `api.fail` |
| 1001 | 参数错误 | `api.param_error` |
| 1002 | 未认证 / 登录失效 / 被踢下线 | `api.unauthorized` / `api.account.kicked` |
| 1003 | 无权限（RBAC 不通过 / IP 被封） | `api.forbidden` / `api.ip.blocked` |
| 1004 | 资源不存在 | `api.not_found` |
| 1005 | 表单校验失败（data=字段错误明细） | `api.validation_error` |
| 1006 | 限流 | `api.rate_limited` |
| 1007 | 冲突 / 重复提交 | `api.favorite.duplicate` / `api.idempotency_replayed` |
| 1008 | 验证码错误 / 过期（登录接口） | `api.captcha.invalid` |
| 5000 | 服务器内部错误（生产不暴露堆栈） | `api.server_error` |

### 6.3 业务自定义码（2xxxx 段）

- 业务模块自定义错误一律用 **`2xxxx`** 段（20001~29999），在本文档「业务码登记表」登记后使用；
- 格式建议：`2{模块号}{序号}`，如商品模块 `21001 商品已下架`、订单模块 `22001 库存不足`；
- 新增业务码必须在本节登记含义与 lang key，禁止裸用数字。

**业务码登记表**（随业务增长追加）：

| code | 含义 | lang key |
|---|---|---|
| 21001 | 商品已下架 | `product.off_shelf` |
| 21002 | 库存不足 | `product.stock_not_enough` |

### 6.4 抛错写法

```php
// 业务失败（系统码）
throw new ApiException('api.product.not_found', AppCode::NotFound->value); // code=1004

// 业务自定义码
throw new ApiException('product.off_shelf', 21001);
```

---

## 7. message() 用法示例

签名（`app/Helpers/ApiHelper.php`）：

```php
message(string $msg = 'api.success', int $code = 200, mixed $data = null): JsonResponse
```

```php
// 1. 成功无数据（code=0）
return message();                              // {code:0,msg:"操作成功",data:null}

// 2. 成功带数据（登录返回 token）
return message('login.success', 0, [
    'token'      => $token,
    'expires_in' => config('jwt.ttl') * 60,
]);

// 3. 业务失败（系统码 1004 = 资源不存在）
throw new ApiException('api.product.not_found', 1004);

// 4. 业务失败（自定义码 21001）
throw new ApiException('product.off_shelf', 21001);

// 5. 校验失败明细由全局处理器自动包成 code=1005，控制器无需处理
```

响应结构（HTTP 状态始终 200）：

```json
{ "code": 0, "msg": "操作成功", "data": { } }
```

---

## 8. 缓存高并发最佳实践（10 万级 QPS 必备）

### 8.1 cache-aside 模式

读路径：先读缓存，未命中查 DB，回写缓存（带兜底 TTL）。写路径：更新 DB 后删缓存（或失效 key）。

```php
// 读：dict:{type}，TTL 3600
$options = Cache::remember("dict:{$type}", 3600, function () use ($type) {
    return Dict::where('type', $type)->where('status', 1)
        ->orderBy('sort')->get(['label','value']);
});

// 写：DB 更新后删缓存
Dict::create($data);
Cache::forget("dict:{$type}");
```

### 8.2 防击穿（热点 key 失效瞬间大量请求打 DB）

**方案 A：Cache::lock 互斥重建**（仅一个请求回源，其余等待后读缓存）。

```php
$key = "product:detail:{$snowId}";
$value = Cache::get($key);
if ($value === null) {
    $lock = Cache::lock("lock:{$key}", 5); // 5 秒锁
    try {
        $lock->block(3);                   // 最多等 3 秒
        $value = Cache::get($key);         // 二次读
        if ($value === null) {
            $value = Product::with('images')->where('snow_id', $snowId)->first();
            Cache::put($key, $value, 600);
        }
    } finally {
        optional($lock)->release();
    }
}
return $value;
```

**方案 B：逻辑过期**（值内嵌 `expires_at`，过期后异步重建，请求仍返回旧值）。

```php
$cached = Cache::get("home:banners");
if ($cached && $cached['expires_at'] > time()) {
    return $cached['data'];
}
// 异步重建：dispatch 一个 Job，立即返回旧值
RefreshBannerCache::dispatch();
return $cached['data'] ?? $default;
```

### 8.3 防雪崩（大量 key 同时过期，DB 瞬间压力飙升）

给 TTL 加随机抖动，避免同批 key 同时失效。

```php
// 基础 3600 秒，加 ±300 秒抖动
$ttl = 3600 + random_int(-300, 300);
Cache::put($key, $value, $ttl);
```

### 8.4 防穿透（查不存在的数据，请求反复打 DB）

对空结果也缓存短 TTL（空值缓存）。

```php
$product = Cache::remember("product:{$snowId}", 60, function () use ($snowId) {
    $p = Product::where('snow_id', $snowId)->first();
    return $p ?? ['__missing__' => true]; // 空结果也缓存 60 秒
});
if (is_array($product) && ($product['__missing__'] ?? false)) {
    throw new ApiException('api.product.not_found', ErrorCode::NotFound->value);
}
```

> 注意：空值 TTL 要短（30~120 秒），避免新写入数据要等很久才能被查到。

### 8.5 大表模块开发 checklist

新增一个会到千万行的模块时，按此清单逐项打勾：

- [ ] **索引先行**：where/join/orderBy/groupBy 列全部建索引，迁移字段 `->comment()` 标注用途；
- [ ] **keyset 深分页**：列表默认 `cursor + snow_id` 游标分页，浅分页 `page/page_size` 仅用于小数据量；
- [ ] **批量写**：批量 insert 用 `Model::insert([...])`，大批量用 `chunkById()`，禁止循环单条 create；
- [ ] **with 预加载**：列表必须 `with([...])` 避免 N+1；
- [ ] **缓存钩子**：Service/Repository 提供 `cacheKey()` / `forgetCache()`，热点数据走 cache-aside；
- [ ] **TTL 抖动**：缓存 TTL 加随机偏移防雪崩；
- [ ] **空值缓存**：不存在资源缓存短 TTL 防穿透；
- [ ] **分区/分表预案**：messages/admin_operation_logs 按月 RANGE 分区，user_favorites 按 user_id 分表（见 deploy.md 第 9 节）；
- [ ] **归档任务**：旧数据通过 schedule 归档到历史表，保持热表小而快；
- [ ] **禁止 `Model::all()`**：大表任何全量遍历都必须 chunk。

---

## 24. 语言包接口与后台多语言管理（i18n runtime）

> 前端（Vue3 / uni-app）运行时从接口加载全部文案，后台改文案后无需发版即时生效。
> 后端统一 key 体系：`{file}.{dot.path}`（与 `__()` 一致，例如 `api.success`）。

### 24.1 公开语言包接口（C 端，无鉴权）

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/api/locale/{lang?}` | 返回该语言全部文案的点号扁平 key-value |

- 语言解析顺序：路径 `{lang?}` → query `?lang=xx` → `config('app.locale')`。
- 非法 lang（目录不存在 / 含非法字符）返回 HTTP 200 + body `code=1004 api.not_found`。
- 响应结构：`{success:true, code:0, msg, data:{lang, items:{...}}}`。
- 缓存：`Cache::remember('locale:pack:{lang}', 86400, ...)`，TTL 1 天。
- 前端接入约定：App 启动时按当前语言拉一次，写入 i18n 实例；切换语言时重新拉取。

### 24.2 后台多语言管理（admin 守卫，RBAC）

| 方法 | 路径 | 权限码 | 说明 |
|---|---|---|---|
| GET | `/api/admin/locale/languages` | `admin:i18n:list` | 列出 `lang/` 下所有语言目录 |
| GET | `/api/admin/locale/{lang}` | `admin:i18n:list` | 查看某语言扁平 key-value |
| PUT | `/api/admin/locale/{lang}` | `admin:i18n:update` | 批量覆盖已存在 key（body `{"items":{"api.success":"新文案"}}`）|
| POST | `/api/admin/locale/refresh` | `admin:i18n:refresh` | 手动清缓存（body 可选 `{"lang":"zh-CN"}`，不带则清全部）|

### 24.3 编辑规则

- **只允许覆盖已存在 key**：请求体中不存在的 key 会被放入 `data.skipped` 返回，不会新增到文件。新增 key 必须走 `php artisan lang:sync` 或代码发布。
- 写回文件格式：UTF-8 无 BOM、`<?php` + `declare(strict_types=1);`、4 空格缩进短数组，与 `lang:sync` 命令的输出格式一致，不破坏键结构。
- 写后自动 `Cache::forget('locale:pack:{lang}')`，下次请求即读新值；如遇异常，可由 `POST /api/admin/locale/refresh` 手动兜底。

### 24.4 权限种子

`PermissionSeeder` 追加 3 条 system 权限（group=`system`，type=1）：
`admin:i18n:list` / `admin:i18n:update` / `admin:i18n:refresh`。超级管理员自动全量。

### 24.5 缓存键约定

| 键 | TTL | 用途 |
|---|---|---|
| `locale:pack:{lang}` | 86400 | 该语言扁平 key-value 包 |

清缓存触发时机：① PUT 写回成功后自动清对应语言；② POST refresh 手动清（单语言或全部）。
