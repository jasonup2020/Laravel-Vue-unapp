<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\AdminMenu;
use App\Models\Message;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFavorite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recycle bin service: list and restore soft-deleted records/
 * 回收站服务：列出并恢复软删除记录
 *
 * Only the whitelisted resources below are restorable. dicts and ip_blacklist
 * are excluded because their tables have no deleted_at column.
 * 仅以下白名单资源可恢复。dicts 与 ip_blacklist 因表无 deleted_at 列而不收录。
 */
class RecycleBinService
{
    /**
     * Resource name => Eloquent model class map/资源名 => Eloquent 模型类映射
     *
     * @var array<string, class-string<Model>>
     */
    private const RESOURCES = [
        'products'       => Product::class,
        'users'          => User::class,
        'admins'         => Admin::class,
        'admin_menus'    => AdminMenu::class,
        'roles'          => Role::class,
        'messages'       => Message::class,
        'user_favorites' => UserFavorite::class,
    ];

    /**
     * List soft-deleted rows of a whitelisted resource/分页列出指定资源的软删除记录
     *
     * @param  string $resource  resource key from the whitelist/白名单资源键
     * @param  int    $page     page number/页码
     * @param  int    $pageSize page size capped at 100/每页条数，上限 100
     * @return array{list: array<int, Model>, total: int, page: int, page_size: int}
     */
    public function paginateTrashed(string $resource, int $page, int $pageSize): array
    {
        $class = $this->resolveResource($resource);

        $paginator = $class::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list'      => $paginator->items(),
            'total'     => $paginator->total(),
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Restore one soft-deleted record by snow_id/按 snow_id 恢复单条软删除记录
     *
     * @param  string $resource resource key from the whitelist/白名单资源键
     * @param  int    $snowId  outward-facing snow id/对外雪花 ID
     */
    public function restoreBySnowId(string $resource, int $snowId): Model
    {
        $class = $this->resolveResource($resource);

        $model = $class::onlyTrashed()->where('snow_id', $snowId)->first();

        if ($model === null) {
            throw ApiException::notFound('api.recycle.not_found');
        }

        $model->restore();

        // exists guard after restore/恢复后再次校验记录存在
        if (!$model->exists) {
            throw ApiException::notFound('api.recycle.not_found');
        }

        $this->clearRestoreCache($resource, $model);

        return $model;
    }

    /**
     * Batch restore soft-deleted records in a DB transaction/事务内批量恢复软删除记录
     *
     * Missing snow ids are skipped and reported back in "missing"; restored count
     * is returned in "restored".
     * 不存在的 snow id 跳过并在 missing 中返回；成功数在 restored 中返回。
     *
     * @param  string    $resource resource key from the whitelist/白名单资源键
     * @param  array<int> $snowIds outward-facing snow ids to restore/待恢复的对外雪花 ID
     * @return array{restored: int, missing: int}
     */
    public function restoreBatch(string $resource, array $snowIds): array
    {
        $class = $this->resolveResource($resource);

        return DB::transaction(function () use ($class, $resource, $snowIds): array {
            $restored = 0;
            $missing  = 0;

            foreach ($snowIds as $snowId) {
                $model = $class::onlyTrashed()->where('snow_id', (int) $snowId)->first();

                if ($model === null) {
                    // Skip ids that no longer exist in trash/回收站中不存在的 id 跳过
                    $missing++;
                    continue;
                }

                $model->restore();
                $this->clearRestoreCache($resource, $model);
                $restored++;
            }

            return ['restored' => $restored, 'missing' => $missing];
        });
    }

    /**
     * Resolve and validate the resource key/解析并校验资源键
     *
     * @return class-string<Model>
     */
    private function resolveResource(string $resource): string
    {
        if (!isset(self::RESOURCES[$resource])) {
            throw ApiException::business('api.recycle.resource_invalid');
        }

        return self::RESOURCES[$resource];
    }

    /**
     * Clear caches that may hold stale data after a restore/恢复后清理可能持有旧数据的缓存
     *
     * Only admin_menus has a contract-defined cache (admin_menu:tree:{adminId},
     * TTL 7 days, plus the per-admin permission cache admin:permissions:{adminId});
     * restoring a menu therefore invalidates those keys for every enabled admin.
     * 仅 admin_menus 有契约定义的缓存（admin_menu:tree:{adminId}，TTL 7 天，
     * 以及按管理员维度的权限缓存 admin:permissions:{adminId}）；
     * 恢复菜单时清空全部启用管理员的这些 key。
     */
    private function clearRestoreCache(string $resource, Model $model): void
    {
        if ($resource !== 'admin_menus') {
            // Other whitelisted resources have no contract-defined cache key/
            // 其余白名单资源无契约定义的缓存键
            return;
        }

        foreach (Admin::query()->pluck('id') as $adminId) {
            Cache::forget("admin_menu:tree:{$adminId}");
            Cache::forget("admin:permissions:{$adminId}");
        }
    }
}
