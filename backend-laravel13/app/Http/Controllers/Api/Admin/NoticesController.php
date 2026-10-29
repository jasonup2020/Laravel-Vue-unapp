<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Base\BaseController;
use App\Http\Requests\Admin\NoticesStoreRequest;
use App\Http\Requests\Admin\NoticesUpdateRequest;
use App\Http\Requests\User\NoticesIndexRequest;
use App\Services\Admin\NoticesService;
use Illuminate\Http\JsonResponse;

/**
 * Admin Notices controller (CRUD)/管理端 Notices 控制器（CRUD）
 */
class NoticesController extends BaseController
{
    /**
     * Constructor/构造函数
     */
    public function __construct(protected NoticesService $service) {}

    /**
     * Paginated list/分页列表
     */
    public function index(NoticesIndexRequest $request): JsonResponse
    {
        [$page, $pageSize] = $this->parsePageParams($request);
        $data = $this->service->index([
            'page'      => $page,
            'page_size' => $pageSize,
            'filters'   => $request->validated(),
        ]);

        return $this->success($data, 'notice.success');
    }

    /**
     * Show one by snow_id/按 snow_id 详情
     */
    public function show(string $snowId): JsonResponse
    {
        $model = $this->service->show((int) $snowId);

        return $this->success($model, 'notice.success');
    }

    /**
     * Create/新建
     */
    public function store(NoticesStoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->success($model, 'notice.saved');
    }

    /**
     * Update by snow_id/按 snow_id 更新
     */
    public function update(NoticesUpdateRequest $request, string $snowId): JsonResponse
    {
        $model = $this->service->update((int) $snowId, $request->validated());

        return $this->success($model, 'notice.updated');
    }

    /**
     * Soft-delete by snow_id/按 snow_id 软删除
     */
    public function destroy(string $snowId): JsonResponse
    {
        $this->service->destroy((int) $snowId);

        return $this->success(null, 'notice.deleted');
    }
}