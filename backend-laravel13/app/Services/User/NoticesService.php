<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Base\BaseService;
use App\Repositories\NoticesRepository;

/**
 * C-end Notices service (readonly)/C 端 Notices 服务（只读）
 *
 * Only index/show are exposed to C-end; write operations belong to Admin service.
 * C 端仅暴露 index/show；写操作归 Admin 服务。
 */
class NoticesService extends BaseService
{
    /**
     * Constructor/构造函数
     */
    public function __construct(NoticesRepository $repo)
    {
        $this->repository = $repo;
    }
}