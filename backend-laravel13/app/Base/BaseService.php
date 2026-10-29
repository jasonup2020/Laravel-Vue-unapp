<?php

declare(strict_types=1);

namespace App\Base;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base service/服务层基类
 *
 * Provides transaction wrapper and generic CRUD skeleton delegating to a repository.
 * 提供事务封装与通用 CRUD 骨架，CRUD 委托给子类注入的 Repository。
 */
abstract class BaseService
{
    /**
     * The repository instance/当前模块的 Repository 实例
     *
     * Subclasses must inject their repository via constructor and assign here.
     * 子类必须通过构造函数注入对应 Repository 并赋值给本属性。
     */
    protected BaseRepository $repository;

    /**
     * Get the repository/获取当前 Repository
     */
    protected function repo(): BaseRepository
    {
        return $this->repository;
    }

    /**
     * Run a callable inside a database transaction/在数据库事务中执行回调
     *
     * Any Throwable is rethrown as ApiException (business error) so the global
     * handler renders it uniformly; the transaction is rolled back automatically.
     * 任意异常都会被包装成 ApiException（业务错误）抛出，由全局异常处理器统一渲染；事务自动回滚。
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     *
     * @throws ApiException
     */
    protected function transaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (ApiException $e) {
            // Let business exceptions propagate as-is/业务异常原样向上抛
            throw $e;
        } catch (Throwable $e) {
            throw ApiException::business('api.common.biz_error', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Paginated list/分页列表
     *
     * @param  array{page?:int,page_size?:int,filters?:array<string,mixed>}  $params
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,page_size:int}
     */
    public function index(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? 15);

        return $this->repo()->paginate($page, $pageSize, $params['filters'] ?? []);
    }

    /**
     * Find one by snow_id/按 snow_id 查询单条
     *
     * @throws ApiException when not found/找不到时抛出异常
     */
    public function show(int|string $snowId): \Illuminate\Database\Eloquent\Model
    {
        return $this->repo()->findBySnowIdOrFail($snowId);
    }

    /**
     * Create a record/新建记录
     *
     * @param  array<string,mixed>  $data
     */
    public function store(array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->transaction(function () use ($data) {
            $model = $this->repo()->create($data);
            $this->repo()->forgetCache();

            return $model;
        });
    }

    /**
     * Update a record by snow_id/按 snow_id 更新记录
     *
     * @param  array<string,mixed>  $data
     */
    public function update(int|string $snowId, array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->transaction(function () use ($snowId, $data) {
            $model = $this->repo()->findBySnowIdOrFail($snowId);
            $this->repo()->update($model, $data);
            $this->repo()->forgetCache();

            return $model->refresh();
        });
    }

    /**
     * Soft-delete a record by snow_id/按 snow_id 软删除记录
     */
    public function destroy(int|string $snowId): bool
    {
        return $this->transaction(function () use ($snowId) {
            $model = $this->repo()->findBySnowIdOrFail($snowId);
            $this->repo()->delete($model);
            $this->repo()->forgetCache();

            return true;
        });
    }
}
