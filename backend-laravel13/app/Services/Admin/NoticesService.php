<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Base\BaseService;
use App\Repositories\NoticesRepository;

/**
 * Admin Notices service (full CRUD)/管理端 Notices 服务（全量 CRUD）
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