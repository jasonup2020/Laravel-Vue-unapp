<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Base\BaseController;
use App\Http\Requests\User\NoticesIndexRequest;
use App\Services\User\NoticesService;
use Illuminate\Http\JsonResponse;

/**
 * C-end Notices controller (readonly)/C 端 Notices 控制器（只读）
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
}