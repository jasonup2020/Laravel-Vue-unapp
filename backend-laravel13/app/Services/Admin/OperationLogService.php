<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Admin operation audit log service (driver-based storage)/
 * 管理员操作审计日志服务（驱动化存储）
 *
 * Per contract §13.4 audit data is append-only and MUST NOT be written to MySQL.
 * The record(array $data) signature is unchanged so the S5 AdminOperationLog
 * middleware needs zero modification; internally the row is dispatched to the
 * configured driver (file by default, elasticsearch optional).
 * 按契约 §13.4，审计数据为 append-only 海量数据，不写 MySQL。
 * record(array $data) 签名保持不变，S5 AdminOperationLog 中间件零改动；
 * 内部按驱动分发（默认 file，可选 elasticsearch）。
 */
class OperationLogService
{
    /**
     * Persist one audit row/落库一条审计日志
     *
     * Accepts the exact array shape built by AdminOperationLog middleware:
     * admin_id / url / method / ip / request_params / response_result /
     * cost_ms / is_high_risk. Password fields are re-masked here as a guard.
     * 接收中间件构造的数组：admin_id / url / method / ip / request_params /
     * response_result / cost_ms / is_high_risk。密码字段兜底脱敏。
     *
     * @param  array<string, mixed> $data audit payload/审计数据
     */
    public function record(array $data): void
    {
        // Fallback redaction on the JSON-encoded request params/对 JSON 编码后的请求参数兜底脱敏
        if (!empty($data['request_params']) && is_string($data['request_params'])) {
            $decoded = json_decode($data['request_params'], true);
            if (is_array($decoded)) {
                $encoded = json_encode(
                    $this->sanitizePasswordFields($decoded),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                $data['request_params'] = $encoded === false ? $data['request_params'] : $encoded;
            }
        }

        // Assemble the canonical audit row/组装标准审计行
        $row = [
            // log_id = millisecond timestamp + random suffix/log_id = 毫秒时间戳 + 随机后缀
            'log_id'         => $this->generateLogId(),
            'admin_id'       => isset($data['admin_id']) && $data['admin_id'] !== null ? (int) $data['admin_id'] : null,
            'url'            => (string) ($data['url'] ?? ''),
            'method'         => (string) ($data['method'] ?? ''),
            'ip'             => (string) ($data['ip'] ?? ''),
            'request_params' => $data['request_params'] ?? null,
            'response_result'=> $data['response_result'] ?? null,
            'cost_ms'        => isset($data['cost_ms']) && $data['cost_ms'] !== null ? (int) $data['cost_ms'] : null,
            'is_high_risk'   => !empty($data['is_high_risk']) ? 1 : 0,
            'created_at'     => now()->toIso8601String(),
        ];

        // Dispatch to the configured driver; never throw into middleware flow/
        // 按驱动分发；绝不把异常抛回中间件流程
        try {
            $driver = (string) config('audit.driver', 'file');

            if ($driver === 'elasticsearch') {
                $this->writeToElasticsearch($row);
            } else {
                $this->writeToFile($row);
            }
        } catch (\Throwable $e) {
            // Best-effort fallback: file, then a plain warning/尽力回退 file，再打 warning
            Log::warning('audit_write_failed', ['err' => $e->getMessage()]);
            try {
                $this->writeToFile($row);
            } catch (\Throwable) {
                // Give up silently; audit must never block business/静默放弃；审计绝不阻断业务
            }
        }
    }

    /**
     * Read audit logs with filters, paginated, newest first/
     * 按过滤条件分页读取审计日志，按时间倒序
     *
     * @param  array{admin_id?: int|string, date?: string} $filters admin_id / Y-m-d filter
     * @return array{list: array<int, array<string, mixed>>, total: int, page: int, page_size: int}
     */
    public function read(array $filters, int $page, int $pageSize): array
    {
        $driver = (string) config('audit.driver', 'file');

        if ($driver === 'elasticsearch') {
            return $this->readFromElasticsearch($filters, $page, $pageSize);
        }

        return $this->readFromFile($filters, $page, $pageSize);
    }

    /**
     * Fetch one audit row by log_id/按 log_id 查询单条审计记录
     *
     * @return array<string, mixed>|null null when not found/未找到返回 null
     */
    public function show(string $logId): ?array
    {
        $driver = (string) config('audit.driver', 'file');

        if ($driver === 'elasticsearch') {
            return $this->showFromElasticsearch($logId);
        }

        return $this->showFromFile($logId);
    }

    // ---------------------------------------------------------------------
    // file driver/file 驱动
    // ---------------------------------------------------------------------

    /**
     * Append one JSON line to the daily audit log file/追加一行 JSON 到当日审计日志文件
     *
     * @param  array<string, mixed> $row audit row/审计行
     */
    private function writeToFile(array $row): void
    {
        $path = $this->dailyFilePath();

        // Auto-create the directory if missing/目录不存在自动创建
        File::ensureDirectoryExists(dirname($path));

        File::append(
            $path,
            json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    /**
     * Resolve today's audit log file path/当日审计日志文件路径
     */
    private function dailyFilePath(?\DateTimeInterface $date = null): string
    {
        $date ??= now();

        $dir = (string) config('audit.file.path', storage_path('logs/audit'));

        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'audit-' . $date->format('Y-m-d') . '.log';
    }

    /**
     * Read from file driver: parse audit files, filter, sort, paginate/
     * 文件驱动读取：解析审计文件、过滤、排序、分页
     *
     * @return array{list: array<int, array<string, mixed>>, total: int, page: int, page_size: int}
     */
    private function readFromFile(array $filters, int $page, int $pageSize): array
    {
        $rows = $this->collectFileRows($filters);

        // Sort by created_at desc/按 created_at 倒序
        usort($rows, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        $total = count($rows);
        $offset = max(0, ($page - 1) * $pageSize);

        return [
            'list'      => array_slice($rows, $offset, $pageSize),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * Show from file driver: scan audit files for log_id/
     * 文件驱动查单条：扫描审计文件按 log_id 查找
     *
     * @return array<string, mixed>|null
     */
    private function showFromFile(string $logId): ?array
    {
        foreach ($this->auditFileIterator() as $path) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (is_array($row) && ($row['log_id'] ?? null) === $logId) {
                    fclose($handle);
                    return $row;
                }
            }
            fclose($handle);
        }

        return null;
    }

    /**
     * Collect rows from audit files matching the filters/收集符合过滤条件的审计行
     *
     * @param  array{admin_id?: int|string, date?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    private function collectFileRows(array $filters): array
    {
        $rows = [];
        $adminId = isset($filters['admin_id']) && $filters['admin_id'] !== '' ? (int) $filters['admin_id'] : null;
        $date = isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : null;

        foreach ($this->auditFileIterator($date) as $path) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row)) {
                    continue;
                }
                if ($adminId !== null && (int) ($row['admin_id'] ?? 0) !== $adminId) {
                    continue;
                }
                $rows[] = $row;
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Yield audit log files, newest date first/产出审计文件路径，按日期倒序
     *
     * @return \Generator<int, string>
     */
    private function auditFileIterator(?string $date = null): \Generator
    {
        $dir = (string) config('audit.file.path', storage_path('logs/audit'));

        if (!is_dir($dir)) {
            return;
        }

        // Filter by date if provided/若指定日期则只取该日文件
        $pattern = $date !== null
            ? 'audit-' . $date . '.log'
            : 'audit-*.log';

        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern) ?: [];

        // Newest first by filename (YYYY-MM-DD desc)/按文件名（YYYY-MM-DD）倒序
        rsort($files);

        foreach ($files as $f) {
            yield $f;
        }
    }

    // ---------------------------------------------------------------------
    // elasticsearch driver/elasticsearch 驱动
    // ---------------------------------------------------------------------

    /**
     * Write one row to Elasticsearch via _bulk; on any failure fall back to file/
     * 经 _bulk 写入 Elasticsearch；任何失败回退 file
     *
     * @param  array<string, mixed> $row audit row/审计行
     */
    private function writeToElasticsearch(array $row): void
    {
        $host = (string) config('audit.es.host', 'http://127.0.0.1:9200');
        $prefix = (string) config('audit.es.index_prefix', 'audit_logs');
        $index = $prefix . '-' . now()->format('Y.m.d');

        $client = new \GuzzleHttp\Client(['base_uri' => rtrim($host, '/'), 'timeout' => 3]);

        $bulkBody = json_encode(['index' => ['_index' => $index]], JSON_UNESCAPED_UNICODE) . "\n"
            . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        try {
            $resp = $client->post('/_bulk', [
                'body'    => $bulkBody,
                'headers' => ['Content-Type' => 'application/x-ndjson'],
            ]);

            $payload = json_decode((string) $resp->getBody(), true);
            if (!empty($payload['errors'])) {
                throw new \RuntimeException('elasticsearch_bulk_reported_errors');
            }
        } catch (\Throwable $e) {
            // ES unavailable: warn and fall back to file/ES 不可达：warning 并回退 file
            Log::warning('audit_es_unavailable_fallback_file', ['err' => $e->getMessage()]);
            $this->writeToFile($row);
        }
    }

    /**
     * Search Elasticsearch for paginated audit rows/从 Elasticsearch 分页查询审计行
     *
     * @return array{list: array<int, array<string, mixed>>, total: int, page: int, page_size: int}
     */
    private function readFromElasticsearch(array $filters, int $page, int $pageSize): array
    {
        try {
            $client = $this->esClient();
            $prefix = (string) config('audit.es.index_prefix', 'audit_logs');

            $query = ['match_all' => new \stdClass()];
            if (!empty($filters['admin_id'])) {
                $query = ['term' => ['admin_id' => (int) $filters['admin_id']]];
            }

            $resp = $client->post("/{$prefix}-*/_search", [
                'json' => [
                    'query' => $query,
                    'sort'  => [['created_at' => 'desc']],
                    'from'  => max(0, ($page - 1) * $pageSize),
                    'size'  => $pageSize,
                ],
            ]);

            $payload = json_decode((string) $resp->getBody(), true);
            $hits = $payload['hits']['hits'] ?? [];

            $list = array_map(fn (array $h): array => $h['_source'] ?? [], $hits);

            return [
                'list'      => $list,
                'total'     => (int) ($payload['hits']['total']['value'] ?? count($list)),
                'page'      => $page,
                'page_size' => $pageSize,
            ];
        } catch (\Throwable $e) {
            Log::warning('audit_es_read_failed_fallback_file', ['err' => $e->getMessage()]);

            return $this->readFromFile($filters, $page, $pageSize);
        }
    }

    /**
     * Fetch one row from Elasticsearch by log_id/从 Elasticsearch 按 log_id 查单条
     *
     * @return array<string, mixed>|null
     */
    private function showFromElasticsearch(string $logId): ?array
    {
        try {
            $client = $this->esClient();
            $prefix = (string) config('audit.es.index_prefix', 'audit_logs');

            $resp = $client->post("/{$prefix}-*/_search", [
                'json' => [
                    'query' => ['term' => ['log_id' => $logId]],
                    'size'  => 1,
                ],
            ]);

            $payload = json_decode((string) $resp->getBody(), true);
            $hit = $payload['hits']['hits'][0] ?? null;

            return $hit['_source'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('audit_es_show_failed_fallback_file', ['err' => $e->getMessage()]);

            return $this->showFromFile($logId);
        }
    }

    /**
     * Build a Guzzle client for Elasticsearch/构造 Elasticsearch Guzzle 客户端
     */
    private function esClient(): \GuzzleHttp\Client
    {
        $host = (string) config('audit.es.host', 'http://127.0.0.1:9200');

        return new \GuzzleHttp\Client(['base_uri' => rtrim($host, '/'), 'timeout' => 3]);
    }

    // ---------------------------------------------------------------------
    // helpers/辅助方法
    // ---------------------------------------------------------------------

    /**
     * Generate a globally-ish unique log id (ms timestamp + random)/
     * 生成 log_id（毫秒时间戳 + 随机数）
     */
    private function generateLogId(): string
    {
        return sprintf('%d%04d', (int) (microtime(true) * 1000), random_int(0, 9999));
    }

    /**
     * Recursively mask password-like fields/递归脱敏密码类字段
     *
     * @param  array<mixed> $params input array/输入数组
     * @return array<mixed>
     */
    private function sanitizePasswordFields(array $params): array
    {
        foreach ($params as $k => $v) {
            $key = is_string($k) ? strtolower($k) : '';

            if (
                is_string($v)
                && (str_contains($key, 'password') || str_contains($key, 'passwd') || $key === 'pwd')
            ) {
                $params[$k] = '***';
                continue;
            }

            if (is_array($v)) {
                $params[$k] = $this->sanitizePasswordFields($v);
            }
        }

        return $params;
    }
}
