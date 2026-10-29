<?php

declare(strict_types=1);

// ============================================================================
// API routes/C 端与管理端 API 路由
// Registered by S16 per contract section 6 (68 endpoints: 18 C-end + 50 admin).
// 由 S16 按契约第 6 节注册（68 接口：C 端 18 + 管理端 50）。
//
// Notes/注意事项:
// - routes/api.php is auto-prefixed with '/api' by the framework
//   (bootstrap/app.php withRouting api:), so paths here are written without '/api'.
//   本文件由框架自动加 '/api' 前缀，故路径不写 '/api'。
// - ip.blacklist & lang are global prepend middleware (bootstrap/app.php),
//   NOT repeated here.
//   ip.blacklist 与 lang 已在 bootstrap/app.php 全局 prepend，此处不重复挂载。
// - api.composite.rate is mounted WITHOUT parameters: rules are resolved
//   dynamically from rate_limit_rules (contract 13.5), fallback 60/60.
//   api.composite.rate 一律无参挂载：规则由后台 rate_limit_rules 动态解析（契约 13.5），未匹配兜底 60/60。
// ============================================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CMenuController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\LocaleController as PublicLocaleController;
use App\Http\Controllers\Api\User\AuthController as UserAuthController;
use App\Http\Controllers\Api\User\UserController as UserUserController;
use App\Http\Controllers\Api\User\ProductController as UserProductController;
use App\Http\Controllers\Api\User\DictController as UserDictController;
use App\Http\Controllers\Api\User\MessageController as UserMessageController;
use App\Http\Controllers\Api\User\FavoriteController as UserFavoriteController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CMenuController as AdminCMenuController;
use App\Http\Controllers\Api\Admin\AdminController as AdminAdminController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\DictController as AdminDictController;
use App\Http\Controllers\Api\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Api\Admin\FavoriteController as AdminFavoriteController;
use App\Http\Controllers\Api\Admin\OperationLogController;
use App\Http\Controllers\Api\Admin\IpBlacklistController;
use App\Http\Controllers\Api\Admin\RecycleBinController;
use App\Http\Controllers\Api\Admin\RateLimitRuleController;
use App\Http\Controllers\Api\Admin\LocaleController as AdminLocaleController;
use App\Http\Controllers\Api\Admin\CacheController;

// ============================================================================
// C-end /api/* (contract 6.1) / C 端
// ============================================================================
// #1 Health check—no guard, no extra rate/健康检查—无守卫、无额外限流
Route::get('/health', [HealthController::class, 'index']);

// Captcha image—public, no auth (login pre-check)/验证码图片—公开无鉴权（登录前置校验）
Route::get('/captcha', [CaptchaController::class, 'index']);

// #2 Login—dynamic rate limit (rule_key=login)/登录—动态限流（rule_key=login）
Route::post('/login', [UserAuthController::class, 'login'])->middleware('api.composite.rate');

// #3 Refresh token—dynamic rate limit/刷新 token—动态限流
Route::post('/refresh', [UserAuthController::class, 'refresh'])->middleware('api.composite.rate');

// Authenticated C-end business routes/已登录 C 端业务路由
// Group middleware: auth:api + check.user.online + default dynamic rate.
// 组中间件：auth:api + check.user.online + 默认动态限流。
Route::middleware(['auth:api', 'check.user.online', 'api.composite.rate'])->group(function () {

    // #4 Logout/登出
    Route::post('/logout', [UserAuthController::class, 'logout']);

    // #5/#6 Profile read & update (response desensitize)/个人信息读改（响应脱敏）
    Route::get('/me', [UserUserController::class, 'profile'])->middleware('response.desensitize');
    Route::put('/me', [UserUserController::class, 'update'])->middleware('response.desensitize');

    // #7 Change password (+idempotency)/修改密码（+幂等）
    Route::put('/me/password', [UserUserController::class, 'changePassword'])
            ->middleware('response.desensitize', 'idempotency');

    // #11-#14 My messages/我的消息
    Route::get('/messages', [UserMessageController::class, 'index']);
    Route::get('/messages/unread-count', [UserMessageController::class, 'unreadCount']);
    Route::put('/messages/read/{id}', [UserMessageController::class, 'markRead']);
    Route::put('/messages/read-all', [UserMessageController::class, 'markAllRead']);

    // #15-#18 Favorites/收藏
    Route::post('/favorites', [UserFavoriteController::class, 'store'])->middleware('idempotency');
    Route::get('/favorites', [UserFavoriteController::class, 'index']);
    Route::get('/favorites/status/{productId}', [UserFavoriteController::class, 'status']);
    Route::delete('/favorites/{id}', [UserFavoriteController::class, 'destroy']);
});

// Public read-only products & dicts (no guard, dynamic rate)/公开只读商品与字典（无守卫，动态限流）
Route::middleware('api.composite.rate')->group(function () {
    // #8 Product list/商品分页
    Route::get('/products', [UserProductController::class, 'index']);
    // #9 Product detail ({id}=snow_id)/商品详情（{id}=snow_id）
    Route::get('/products/{id}', [UserProductController::class, 'show']);
    // #10 Dict by type (Redis cache)/按 type 取字典（Redis 缓存）
    Route::get('/dicts/{type}', [UserDictController::class, 'show']);

    // C-end public menus (cached, no auth)/C端公开菜单（缓存，无鉴权）
    Route::get('/menus', [CMenuController::class, 'index']);

    // Locale pack (public, no auth; frontend boot needs it)/语言包（公开无鉴权，前端启动需要）
    // Lang resolution: path {lang?} > ?lang=xx > config('app.locale')
    Route::get('/locale/{lang?}', [PublicLocaleController::class, 'pack']);
    // Lightweight meta for md5 diffing/轻量 meta，供前端 md5 比对
    Route::get('/locale/{lang}/meta', [PublicLocaleController::class, 'meta']);
});

// ============================================================================
// Admin /api/admin/* (contract 6.2) / 管理端
// ============================================================================
Route::prefix('admin')->group(function () {

    // #19 Admin login—dynamic rate (rule_key=admin:login)/管理员登录—动态限流
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('api.composite.rate');

    // #20 Admin refresh—dynamic rate/管理员刷新—动态限流
    Route::post('/refresh', [AdminAuthController::class, 'refresh'])->middleware('api.composite.rate');

    // Authenticated admin subgroup/已登录管理端子组
    // Group middleware: auth:admin-api + default dynamic rate (admin:global).
    // 组中间件：auth:admin-api + 默认动态限流（admin:global）。
    Route::middleware(['auth:admin-api', 'check.admin.online', 'api.composite.rate'])->group(function () {

        // #21 Admin logout/管理员登出
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        // #22/#23 My profile & change password (high risk)/本人信息 & 改密（高危）
        Route::get('/me', [AdminAdminController::class, 'profile']);
        Route::put('/me/password', [AdminAdminController::class, 'changePassword'])
                ->middleware('admin.operation.log:1', 'idempotency');

        // #24/#25 Menus: my-tree (cached, no perm) / all-tree (perm required)
        // 菜单：my-tree（缓存读，无权限码）/ all-tree（需权限）
        Route::get('/menu/my-tree', [MenuController::class, 'myTree']);
        Route::get('/menu/all-tree', [MenuController::class, 'allTree'])
                ->middleware('admin.permission:admin:menu:list');

        // #26-#28 Menu CRUD (clears menu cache)/菜单 CRUD（写后清菜单缓存）
        Route::post('/menu', [MenuController::class, 'store'])->middleware('admin.permission:admin:menu:create', 'admin.operation.log:0', 'idempotency');
        Route::put('/menu/{id}', [MenuController::class, 'update'])->middleware('admin.permission:admin:menu:update', 'admin.operation.log:0', 'idempotency');
        Route::delete('/menu/{id}', [MenuController::class, 'destroy'])->middleware('admin.permission:admin:menu:delete', 'admin.operation.log:0');

        // #29 Permissions full list/权限全表
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('admin.permission:admin:permission:list');

        // #69-#71 Permission CRUD/权限 CRUD
        Route::post('/permissions', [PermissionController::class, 'store'])->middleware('admin.permission:admin:permission:create', 'admin.operation.log:0', 'idempotency');
        Route::put('/permissions/{id}', [PermissionController::class, 'update'])->middleware('admin.permission:admin:permission:update', 'admin.operation.log:0', 'idempotency');
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy'])->middleware('admin.permission:admin:permission:delete', 'admin.operation.log:0');

        // #30-#35 Roles/角色
        Route::get('/roles', [RoleController::class, 'index'])
                ->middleware('admin.permission:admin:role:list');
        Route::post('/roles', [RoleController::class, 'store'])
                ->middleware('admin.permission:admin:role:create', 'admin.operation.log:1', 'idempotency');
        Route::put('/roles/{id}', [RoleController::class, 'update'])
                ->middleware('admin.permission:admin:role:update', 'admin.operation.log:1', 'idempotency');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])
                ->middleware('admin.permission:admin:role:delete', 'admin.operation.log:1');
        Route::put('/roles/{id}/permissions', [RoleController::class, 'assignPermissions'])
                ->middleware('admin.permission:admin:role:assign', 'admin.operation.log:1', 'idempotency');
        Route::put('/roles/{id}/menus', [RoleController::class, 'assignMenus'])
                ->middleware('admin.permission:admin:role:assign', 'admin.operation.log:1', 'idempotency');

        // #36-#41 Admin accounts/管理员账号
        Route::get('/admins', [AdminAdminController::class, 'index'])
                ->middleware('admin.permission:admin:admin:list');
        Route::post('/admins', [AdminAdminController::class, 'store'])
                ->middleware('admin.permission:admin:admin:create', 'admin.operation.log:1', 'idempotency');
        Route::put('/admins/{id}', [AdminAdminController::class, 'update'])
                ->middleware('admin.permission:admin:admin:update', 'admin.operation.log:1', 'idempotency');
        Route::put('/admins/{id}/password', [AdminAdminController::class, 'resetPassword'])
                ->middleware('admin.permission:admin:admin:update', 'admin.operation.log:1', 'idempotency');
        Route::put('/admins/{id}/status', [AdminAdminController::class, 'updateStatus'])
                ->middleware('admin.permission:admin:admin:update', 'admin.operation.log:0');
        Route::delete('/admins/{id}', [AdminAdminController::class, 'destroy'])
                ->middleware('admin.permission:admin:admin:delete', 'admin.operation.log:1');

        // #42-#46 C-end user management/普通用户管理
        Route::get('/users', [AdminUserController::class, 'index'])
                ->middleware('admin.permission:admin:user:list');
        Route::get('/users/{id}', [AdminUserController::class, 'show'])
                ->middleware('admin.permission:admin:user:list');
        Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus'])
                ->middleware('admin.permission:admin:user:update', 'admin.operation.log:0');
        Route::get('/users/{id}/online', [AdminUserController::class, 'online'])
                ->middleware('admin.permission:admin:user:list');
        Route::post('/users/{id}/kick', [AdminUserController::class, 'kick'])
                ->middleware('admin.permission:admin:user:kick', 'admin.operation.log:1', 'idempotency');

        // #47-#50 Products/商品
        Route::get('/products', [AdminProductController::class, 'index'])
                ->middleware('admin.permission:admin:product:list');
        Route::post('/products', [AdminProductController::class, 'store'])
                ->middleware('admin.permission:admin:product:create', 'admin.operation.log:0', 'idempotency');
        Route::put('/products/{id}', [AdminProductController::class, 'update'])
                ->middleware('admin.permission:admin:product:update', 'admin.operation.log:0', 'idempotency');
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy'])
                ->middleware('admin.permission:admin:product:delete', 'admin.operation.log:0');

        // #51-#54 Dicts (write clears dict cache)/字典（写后清字典缓存）
        Route::get('/dicts', [AdminDictController::class, 'index'])
                ->middleware('admin.permission:admin:dict:list');
        Route::post('/dicts', [AdminDictController::class, 'store'])
                ->middleware('admin.permission:admin:dict:create', 'admin.operation.log:0', 'idempotency');
        Route::put('/dicts/{id}', [AdminDictController::class, 'update'])
                ->middleware('admin.permission:admin:dict:update', 'admin.operation.log:0', 'idempotency');
        Route::delete('/dicts/{id}', [AdminDictController::class, 'destroy'])
                ->middleware('admin.permission:admin:dict:delete', 'admin.operation.log:0');

        // #55/#56 Messages/站内消息
        Route::get('/messages', [AdminMessageController::class, 'index'])
                ->middleware('admin.permission:admin:message:list');
        Route::post('/messages', [AdminMessageController::class, 'send'])
                ->middleware('admin.permission:admin:message:create', 'admin.operation.log:0', 'idempotency');

        // #57 Favorites list (filter by user_id/product_id)/收藏列表（可按 user_id/product_id 过滤）
        Route::get('/favorites', [AdminFavoriteController::class, 'index'])
                ->middleware('admin.permission:admin:favorite:list');

        // #58 Operation logs (driverized audit storage)/操作日志（驱动化审计存储）
        Route::get('/operation-logs', [OperationLogController::class, 'index'])
                ->middleware('admin.permission:admin:log:list');

        // #59-#61 IP blacklist/IP 黑名单
        Route::get('/ip-blacklist', [IpBlacklistController::class, 'index'])->middleware('admin.permission:admin:ip:list');
        Route::post('/ip-blacklist', [IpBlacklistController::class, 'store'])->middleware('admin.permission:admin:ip:create', 'admin.operation.log:0', 'idempotency');
        Route::delete('/ip-blacklist/{id}', [IpBlacklistController::class, 'destroy'])->middleware('admin.permission:admin:ip:delete', 'admin.operation.log:0');

        // #62-#64 Recycle bin/回收站
        Route::get('/recycle-bin/{resource}', [RecycleBinController::class, 'index'])->middleware('admin.permission:admin:recycle:list');
        Route::put('/recycle-bin/{resource}/{id}/restore', [RecycleBinController::class, 'restore'])->middleware('admin.permission:admin:recycle:restore', 'admin.operation.log:0', 'idempotency');
        Route::put('/recycle-bin/{resource}/restore-batch', [RecycleBinController::class, 'restoreBatch'])->middleware('admin.permission:admin:recycle:restore', 'admin.operation.log:0', 'idempotency');

        // #65-#68 Rate limit rules (S19; write clears rate_limit:rules cache)/限流规则管理（写后清 rate_limit:rules 缓存）
        Route::get('/rate-limit-rules', [RateLimitRuleController::class, 'index'])->middleware('admin.permission:admin:rate:list');
        Route::post('/rate-limit-rules', [RateLimitRuleController::class, 'store'])->middleware('admin.permission:admin:rate:create', 'admin.operation.log:0', 'idempotency');
        Route::put('/rate-limit-rules/{id}', [RateLimitRuleController::class, 'update'])->middleware('admin.permission:admin:rate:update', 'admin.operation.log:0', 'idempotency');
        Route::delete('/rate-limit-rules/{id}', [RateLimitRuleController::class, 'destroy'])->middleware('admin.permission:admin:rate:delete', 'admin.operation.log:0');

        // C-end menu CRUD (no RBAC, any logged-in admin can manage; write clears c_menu:list cache)/
        // C端菜单 CRUD（无 RBAC，登录管理员均可管理；写后清 c_menu:list 缓存）
        Route::get('/c-menus', [AdminCMenuController::class, 'index']);
        Route::post('/c-menus', [AdminCMenuController::class, 'store'])->middleware('admin.operation.log:0', 'idempotency');
        Route::put('/c-menus/{id}', [AdminCMenuController::class, 'update'])->middleware('admin.operation.log:0', 'idempotency');
        Route::delete('/c-menus/{id}', [AdminCMenuController::class, 'destroy'])->middleware('admin.operation.log:0');

        // Flush all application caches (any logged-in admin; clears locale pack,
        // menu tree, dict, c_menu list, rate rules...)/清空全部应用缓存（登录管理员可用；
        // 清空 语言包、菜单树、字典、C端菜单列表、限流规则 等缓存）
        Route::post('/cache/flush', [CacheController::class, 'flush']);

        // i18n 多语言管理（public pack is under /api/locale; these are admin-only）
        // 多语言管理（公开语言包在 /api/locale；以下为后台管理）
        // NOTE: literal routes must be declared BEFORE /locale/{lang}./
        // 注意：字面量路由必须声明在 /locale/{lang} 之前。
        Route::get('/locale/languages', [AdminLocaleController::class, 'languages'])->middleware('admin.permission:admin:i18n:list');
        Route::post('/locale/refresh', [AdminLocaleController::class, 'refresh'])->middleware('admin.permission:admin:i18n:refresh');
        Route::get('/locale/{lang}', [AdminLocaleController::class, 'show'])->middleware('admin.permission:admin:i18n:list');
        Route::put('/locale/{lang}', [AdminLocaleController::class, 'update'])->middleware('admin.permission:admin:i18n:update', 'admin.operation.log:0', 'idempotency');
    });
});

// ===== Module: Notices (generated by make:module) =====
// C-end readonly/C 端只读
Route::get('/notices', [\App\Http\Controllers\Api\User\NoticesController::class, 'index']);
Route::get('/notices/{snowId}', [\App\Http\Controllers\Api\User\NoticesController::class, 'show']);

// Admin CRUD/管理端 CRUD
Route::middleware(['auth:admin-api', 'check.admin.online'])->prefix('admin/notices')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Admin\NoticesController::class, 'index'])->middleware('admin.permission:admin:notice:list');
    Route::post('/', [\App\Http\Controllers\Api\Admin\NoticesController::class, 'store'])->middleware('admin.permission:admin:notice:create', 'admin.operation.log:0', 'idempotency');
    Route::put('/{snowId}', [\App\Http\Controllers\Api\Admin\NoticesController::class, 'update'])->middleware('admin.permission:admin:notice:update', 'admin.operation.log:0', 'idempotency');
    Route::delete('/{snowId}', [\App\Http\Controllers\Api\Admin\NoticesController::class, 'destroy'])->middleware('admin.permission:admin:notice:delete', 'admin.operation.log:0');
});
