<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Base\BaseRepository;
use App\Models\Notices;

/**
 * Notices repository/Notices 数据访问层
 */
class NoticesRepository extends BaseRepository
{
    /**
     * Constructor/构造函数
     */
    public function __construct()
    {
        parent::__construct(Notices::class);
    }

    /**
     * Cache key for list read/列表读缓存 key
     *
     * Return null to disable cache; override TTL in service if needed.
     * 返回 null 关闭缓存；如需启用，在 Service 中调整 TTL。
     */
    protected function cacheKey(): ?string
    {
        return 'module.notice:list';
    }
}