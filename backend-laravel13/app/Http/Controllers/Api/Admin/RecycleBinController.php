<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecycleRestoreBatchRequest;
use App\Services\Admin\RecycleBinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recycle bin controller/回收站控制器
 *
 * Lists soft-deleted rows for whitelisted resources, restores a single row by
 * snow_id, and restores a batch of snow_ids inside a DB transaction.
 * 列出白名单资源的软删除记录、按 snow_id 单条恢复、事务内按 snow_id 批量恢复。
 */
class RecycleBinController extends Controller
{
    /**
     * List soft-deleted rows of a resource/分页列出指定资源的软删除记录
     */
    public function index(string $resource, Request $request, RecycleBinService $service): JsonResponse
    {
        $page     = max(1, (int) $request->input('page', 1));
        $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

        return message('api.recycle.list', 0, $service->paginateTrashed($resource, $page, $pageSize));
    }

    /**
     * Restore one soft-deleted row by snow_id/按 snow_id 恢复单条软删除记录
     */
    public function restore(string $resource, string $snowId, RecycleBinService $service): JsonResponse
    {
        return message('api.recycle.restored', 0, $service->restoreBySnowId($resource, (int) $snowId));
    }

    /**
     * Batch restore soft-deleted rows by snow_ids/按 snow_id 列表批量恢复
     */
    public function restoreBatch(string $resource, RecycleRestoreBatchRequest $request, RecycleBinService $service): JsonResponse
    {
        $result = $service->restoreBatch($resource, $request->validated()['ids']);

        return message('api.recycle.batch_restored', 0, $result);
    }
}
