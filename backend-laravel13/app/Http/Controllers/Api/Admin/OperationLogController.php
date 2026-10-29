<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\OperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin operation log controller/管理员操作日志控制器
 *
 * Read-only: paginated audit log list (optional admin_id / date filter) and
 * detail by outward-facing log_id. Storage is driver-based (file default,
 * elasticsearch optional) — see contract §13.4.
 * 只读：审计日志分页列表（可选 admin_id / 日期过滤）与按 log_id 查详情。
 * 存储为驱动化（默认 file，可选 elasticsearch）——见契约 §13.4。
 */
class OperationLogController extends Controller
{
    /**
     * Paginate audit logs/审计日志分页列表
     */
    public function index(Request $request, OperationLogService $service): JsonResponse
    {
        $page     = max(1, (int) $request->input('page', 1));
        $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

        $filters = [];
        if (($adminId = $request->input('admin_id')) !== null && $adminId !== '') {
            $filters['admin_id'] = (int) $adminId;
        }
        if (($date = $request->input('date')) !== null && $date !== '') {
            $filters['date'] = (string) $date;
        }

        return message('api.log.list', 0, $service->read($filters, $page, $pageSize));
    }

    /**
     * Show one audit log detail by log_id/按 log_id 查看单条审计详情
     */
    public function show(string $logId, OperationLogService $service): JsonResponse
    {
        $row = $service->show($logId);

        if ($row === null) {
            throw \App\Exceptions\ApiException::notFound('api.not_found');
        }

        return message('api.success', 0, $row);
    }
}
