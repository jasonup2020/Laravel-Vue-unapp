# C 端 / 管理员全层完全隔离开发模板（extend-example-full-isolation.md）

> 最高隔离模式。当 C 端业务与后台管理业务未来迭代方向差异巨大、需要最大限度解耦时使用。本工程以「用户收藏」模块为完整落地示例。

---

## 1. 隔离规则（规格 §14，最高优先级）

1. **Controller、Service、Model、FormRequest 全部不复用**：C 端一套，管理员一套，物理两个类文件。
2. **仅共享数据库物理表与迁移脚本**：两套 Model 用 `$table` 指向同一张物理表，各自定义 `fillable`、`casts`、关联、scope。
3. **Service 禁止读 `auth()` / `request()` 单例**：身份 ID 全部由控制器方法参数传入，保证可单元测试。
4. **路由分组、守卫、中间件物理隔离**：C 端 `/api/*`（`auth:api`），管理员 `/api/admin/*`（`auth:admin-api` + `admin.permission`）。
5. **禁止在同一个类内部 `if-else` 判断身份分支**（如 `if (auth('api')->check()) {...} else {...}`）——必须拆成两个类。

---

## 2. 以「收藏模块」为完整示例

物理表：`user_favorites`（迁移由数据层统一写，含 `user_id`、`product_id`、`snow_id`、`timestamps`、`softDeletes`，联合唯一索引 `(user_id,product_id)`）。

### 2.1 迁移（共享，只写一份）

`database/migrations/0001_01_01_000120_create_user_favorites_table.php`：

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snow_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'product_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_favorites'); }
};
```

### 2.2 C 端侧（User\）

#### Model — `app/Models/UserFavorite.php`

```php
namespace App\Models;

use App\Models\Traits\HasSnowId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFavorite extends Model
{
    use HasSnowId, SoftDeletes;

    protected $table = 'user_favorites';

    protected $fillable = ['user_id', 'product_id'];

    protected $hidden = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
```

#### Request — `app/Http/Requests/User/FavoriteStoreRequest.php`

```php
namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class FavoriteStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return ['product_id' => 'required|integer'];
    }
}
```

#### Service — `app/Services/User/FavoriteService.php`

> 不读 auth/request；`int $userId` 由控制器传入。

```php
namespace App\Services\User;

use App\Exceptions\ApiException;
use App\Models\UserFavorite;
use App\Enums\ErrorCode;

class FavoriteService
{
    public function add(int $userId, int $productId): array
    {
        $exists = UserFavorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            throw new ApiException('api.favorite.duplicate', ErrorCode::Conflict->value);
        }
        $fav = UserFavorite::create(compact('userId', 'productId'));
        return ['snow_id' => $fav->snow_id, 'product_id' => $productId];
    }

    public function cancel(int $userId, int $snowId): void
    {
        $fav = UserFavorite::where('user_id', $userId)
            ->where('snow_id', $snowId)
            ->first();
        if (!$fav) {
            throw new ApiException('api.favorite.not_found', ErrorCode::NotFound->value);
        }
        $fav->delete(); // 软删
    }

    public function list(int $userId, int $page, int $pageSize): array
    {
        $pageSize = min($pageSize, 100);
        $rows = UserFavorite::where('user_id', $userId)
            ->with('product:id,snow_id,name,price,status')
            ->paginate($pageSize, ['*'], 'page', $page);
        return [
            'list'     => collect($rows->items())->map(fn($f) => [
                'snow_id'    => $f->snow_id,
                'product'    => $f->product?->only(['snow_id','name','price']),
            ])->all(),
            'total'    => $rows->total(),
            'page'     => $page,
            'page_size'=> $pageSize,
        ];
    }
}
```

#### Controller — `app/Http/Controllers/Api/User/FavoriteController.php`

```php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\FavoriteStoreRequest;
use App\Services\User\FavoriteService;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $svc) {}

    public function store(FavoriteStoreRequest $request): JsonResponse
    {
        $data = $this->svc->add(
            $request->user()->id,
            (int) $request->validated('product_id'),
        );
        return message('api.favorite.added', 200, $data);
    }

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $this->svc->list(
            $request->user()->id,
            (int) $request->query('page', 1),
            (int) $request->query('page_size', 15),
        );
        return message('success', 200, $data);
    }

    public function destroy(\Illuminate\Http\Request $request, int $snowId): JsonResponse
    {
        $this->svc->cancel($request->user()->id, $snowId);
        return message('api.favorite.removed');
    }
}
```

#### 路由（C 端组内，`routes/api.php`）

```php
Route::middleware(['auth:api', 'check.user.online'])->group(function () {
    Route::post('/favorites', [FavoriteController::class, 'store'])->middleware('idempotency');
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::get('/favorites/status/{productId}', [FavoriteController::class, 'status']);
    Route::delete('/favorites/{snowId}', [FavoriteController::class, 'destroy']);
});
```

### 2.3 管理员侧（Admin\）

#### Model — `app/Models/AdminFavorite.php`（指向同一张表）

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminFavorite extends Model
{
    use SoftDeletes;

    protected $table = 'user_favorites';   // 同一张物理表

    protected $fillable = ['user_id', 'product_id'];

    protected $hidden = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
```

> 注意：管理员侧 Model 不需要 `HasSnowId` trait 时可省略（snow_id 已由 C 端创建时写入；若管理员侧也要新建记录，同样 use HasSnowId）。

#### Service — `app/Services/Admin/FavoriteService.php`

```php
namespace App\Services\Admin;

use App\Models\AdminFavorite;

class FavoriteService
{
    public function list(int $page, int $pageSize, ?int $userId, ?int $productId): array
    {
        $pageSize = min($pageSize, 100);
        $q = AdminFavorite::with(['user:id,snow_id,name,email', 'product:id,snow_id,name']);
        if ($userId)    $q->where('user_id', $userId);
        if ($productId) $q->where('product_id', $productId);
        $rows = $q->orderByDesc('id')->paginate($pageSize, ['*'], 'page', $page);
        return [
            'list' => collect($rows->items())->map(fn($f) => [
                'snow_id' => $f->snow_id,
                'user'    => $f->user?->only(['snow_id','name','email']),
                'product' => $f->product?->only(['snow_id','name']),
            ])->all(),
            'total' => $rows->total(), 'page' => $page, 'page_size' => $pageSize,
        ];
    }
}
```

#### Controller — `app/Http/Controllers/Api/Admin/FavoriteController.php`

```php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->svc->list(
            (int) $request->query('page', 1),
            (int) $request->query('page_size', 15),
            $request->query('user_id') ? (int) $request->query('user_id') : null,
            $request->query('product_id') ? (int) $request->query('product_id') : null,
        );
        return message('success', 200, $data);
    }
}
```

#### 路由（管理员组内）

```php
Route::middleware(['auth:admin-api'])->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->middleware('admin.permission:admin:favorite:list');
});
```

> **动作级权限约定**：管理员接口按 `{resource}:{action}` 挂 `admin.permission`——查用 `list`、增 `create`、改 `update`、删 `delete`；导出/审计等自定义动作（如 `admin:favorite:export`）在后台「权限管理」新建（type=custom）后挂角色。本例收藏管理只读，故只挂 `admin:favorite:list`；若后续加导出，再挂 `admin.permission:admin:favorite:export`。

---

## 3. 文件清单对照

| 层 | C 端 | 管理员 |
|---|---|---|
| Model | `app/Models/UserFavorite.php` | `app/Models/AdminFavorite.php`（同表） |
| Request | `app/Http/Requests/User/FavoriteStoreRequest.php` | （管理员只读列表，无写 Request） |
| Service | `app/Services/User/FavoriteService.php` | `app/Services/Admin/FavoriteService.php` |
| Controller | `app/Http/Controllers/Api/User/FavoriteController.php` | `app/Http/Controllers/Api/Admin/FavoriteController.php` |
| 路由 | `/api/favorites`（auth:api） | `/api/admin/favorites`（auth:admin-api + 权限） |
| 迁移 | `user_favorites`（共享一份） | 同左 |

---

## 4. 适用 / 不适用场景

### 适用
- C 端与后台后台业务演进差异大（如 C 端收藏 vs 后台风控分析收藏）。
- 团队分两组并行开发，互不干扰。
- 一端要大改逻辑，另一端不能受影响（高解耦）。

### 不适用
- 两端逻辑高度同构（如商品列表只是分页 vs 分页+筛选），强行拆两套会大量重复代码——此时用轻量分层即可。
- 纯只读 C 端接口、后台只做简单 CRUD，差异不足以抵消双份维护成本。
- 表结构极简单、无复杂业务规则时，直接复用一套 Service 反而清晰。
