<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\BaseModel;

/**
 * IpBlacklist model/IP黑名单模型
 *
 * Blocks malicious IPs permanently or until expire_at
 * 永久或定时封禁恶意 IP
 */
class IpBlacklist extends BaseModel
{

    /**
     * The table associated with the model/模型对应的数据表
     *
     * Override default plural ip_blacklists; table is singular ip_blacklist
     * 覆写默认复数 ip_blacklists，实际表名为单数 ip_blacklist
     *
     * @var string
     */
    protected $table = 'ip_blacklist';

    /**
     * The attributes that are mass assignable/可批量赋值的字段
     *
     * @var list<string>
     */
    protected $fillable = [
        'ip',
        'remark',
        'expire_at',
    ];

    /**
     * The attributes that should be hidden for serialization/序列化时隐藏的字段
     *
     * Hide auto-increment id; only snow_id is exposed externally
     * 隐藏自增 id，对外仅暴露 snow_id
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Get the attributes that should be cast/获取属性类型转换映射
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Expire time as Carbon datetime/过期时间转 Carbon 日期时间
            'expire_at' => 'datetime',
        ];
    }
}
