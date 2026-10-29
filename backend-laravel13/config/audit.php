<?php

declare(strict_types=1);

/**
 * Audit log driver configuration/审计日志驱动配置
 *
 * Per contract §13.4, admin operation audit logs are append-only and MUST NOT
 * be written to MySQL. Default driver writes JSON-line files; optional
 * Elasticsearch driver is fully implemented but disabled by default.
 * 按契约 §13.4，管理员操作审计为 append-only 海量数据，不写 MySQL。
 * 默认驱动写 JSON 行文件；可选 elasticsearch 驱动完整实现，默认不启用。
 */
return [
    // Storage driver: file | elasticsearch/存储驱动：file | elasticsearch
    'driver' => env('AUDIT_LOG_DRIVER', 'file'),

    // File driver settings/file 驱动设置
    'file' => [
        // Directory for daily audit files/audit-YYYY-MM-DD.log/每日审计文件目录
        'path' => storage_path('logs/audit'),
    ],

    // Elasticsearch driver settings/elasticsearch 驱动设置
    'es' => [
        // ES base URL (no trailing slash)/ES 基础地址（不带尾斜杠）
        'host' => env('AUDIT_ES_HOST', 'http://127.0.0.1:9200'),
        // Daily index prefix: audit_logs-YYYY.MM.DD/每日索引前缀：audit_logs-YYYY.MM.DD
        'index_prefix' => env('AUDIT_ES_INDEX_PREFIX', 'audit_logs'),
    ],

    // Days of audit logs to retain (file deletion handled by scheduled task)/
    // 审计日志保留天数（文件清理由计划任务执行）
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),
];
