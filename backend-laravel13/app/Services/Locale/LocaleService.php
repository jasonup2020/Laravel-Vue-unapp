<?php

declare(strict_types=1);

namespace App\Services\Locale;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Locale pack service/语言包服务
 *
 * Responsibilities/职责：
 * - list available locales under lang/列出 lang/ 下可用语言
 * - build flattened dot-key pack ({file}.{dot.path} => value)
 *   构建点号扁平 key-value 包（{文件}.{嵌套路径} => 文案）
 * - cache pack under `locale:pack:{lang}` (TTL 86400)/包按 `locale:pack:{lang}` 缓存（TTL 86400）
 * - admin update: overwrite existing keys only, never add new ones/
 *   后台更新：仅覆盖已存在 key，不新增 key
 * - manual cache refresh/手动刷新缓存
 */
class LocaleService
{
    /** Cache TTL for locale pack (seconds)/语言包缓存 TTL（秒） */
    public const PACK_TTL = 86400;

    /**
     * List available locale directories/列出可用语言目录
     *
     * @return array<int,string>
     */
    public function listLocales(): array
    {
        $dirs = glob(lang_path() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $out = [];
        foreach ($dirs ?: [] as $d) {
            $out[] = basename($d);
        }
        sort($out);

        return $out;
    }

    /**
     * Get flattened key-value pack for a locale (cached)/
     * 取某语言的扁平 key-value 包（带缓存）
     *
     * @return array{lang:string,md5:string,items:array<string,string>}
     */
    public function pack(string $lang): array
    {
        $this->assertLocaleExists($lang);

        $payload = $this->resolvePayload($lang);

        return [
            'lang'  => $lang,
            'md5'   => $payload['md5'],
            'items' => $payload['items'],
        ];
    }

    /**
     * Lightweight meta: return only lang + md5 (no items)/
     * 轻量 meta：仅返回 lang + md5，不展开全量文案
     *
     * @return array{lang:string,md5:string}
     */
    public function meta(string $lang): array
    {
        $this->assertLocaleExists($lang);

        $payload = $this->resolvePayload($lang);

        return [
            'lang' => $lang,
            'md5'  => $payload['md5'],
        ];
    }

    /**
     * Resolve cached payload, transparently migrating legacy flat items/
     * 解析缓存负载，对早期纯 items 旧格式自动迁移（补 md5 并回写）
     *
     * Handles three cases/三种情况：
     * - cache hit with new shape {md5, items}/新格式直接用
     * - cache hit with legacy flat items array/旧格式补 md5 并回写
     * - cache miss → build from files/未命中从文件构建
     *
     * @return array{md5:string,items:array<string,string>}
     */
    protected function resolvePayload(string $lang): array
    {
        $key = $this->packKey($lang);
        $cached = Cache::get($key);

        // New shape: ['md5' => ..., 'items' => ...]/新格式
        if (is_array($cached) && isset($cached['md5'], $cached['items']) && is_array($cached['items'])) {
            return $cached;
        }

        // Legacy shape: cached value IS the flat items map/旧格式（纯 items 数组）
        if (is_array($cached) && $cached !== [] && isset($cached[array_key_first($cached)])) {
            /** @var array<string,string> $items */
            $items = $cached;
            ksort($items);
            $md5 = md5(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payload = ['md5' => $md5, 'items' => $items];
            Cache::put($key, $payload, self::PACK_TTL);

            return $payload;
        }

        // Cache miss or corrupt → build from files/未命中或损坏，从文件构建
        $payload = $this->buildPayload($lang);
        Cache::put($key, $payload, self::PACK_TTL);

        return $payload;
    }

    /**
     * Build cached payload {md5, items}/构建缓存负载 {md5, items}
     *
     * @return array{md5:string,items:array<string,string>}
     */
    protected function buildPayload(string $lang): array
    {
        $items = $this->loadPackFromFiles($lang);
        ksort($items);
        // Stable md5: sorted keys + unescaped unicode/slashes/
        // 稳定 md5：键已排序 + 不转义 unicode/斜杠
        $md5 = md5(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['md5' => $md5, 'items' => $items];
    }

    /**
     * Update existing keys only/仅覆盖已存在 key
     *
     * New keys (not present in source files) are skipped and returned in $skipped;
     * they must be added via `lang:sync` or code release./
     * 源文件中不存在的新 key 将被跳过并放入 $skipped，需走 `lang:sync` 或代码发布。
     *
     * @param  array<string,string>  $items  dot-key => new value
     * @return array{0:int,1:array<int,string>}  [updatedCount, skippedKeys]
     */
    public function update(string $lang, array $items): array
    {
        $this->assertLocaleExists($lang);

        // Group items by file prefix (first segment)/按文件前缀（第一段）分组
        $byFile = [];
        foreach ($items as $dotKey => $value) {
            [$prefix, $nested] = $this->splitKey((string) $dotKey);
            if ($prefix === '' || $nested === '') {
                // Malformed key (no file prefix)/key 格式异常（无文件前缀），直接跳过
                continue;
            }
            $byFile[$prefix][] = [$nested, (string) $value, (string) $dotKey];
        }

        $updated = 0;
        $skipped = [];

        foreach ($byFile as $prefix => $entries) {
            $file = $this->fileForPrefix($lang, $prefix);

            if (!is_file($file)) {
                // File itself missing -> all keys in this group skipped/文件不存在，本组全部跳过
                foreach ($entries as [, , $dotKey]) {
                    $skipped[] = $dotKey;
                }
                continue;
            }

            $arr = include $file;
            if (!is_array($arr)) {
                $arr = [];
            }

            foreach ($entries as [$nested, $value, $dotKey]) {
                if (\Arr::has($arr, $nested)) {
                    \Arr::set($arr, $nested, $value);
                    $updated++;
                } else {
                    $skipped[] = $dotKey;
                }
            }

            $this->writePhpArrayFile($file, $arr);
        }

        // Invalidate cached pack so next request picks up new values/
        // 失效缓存，下次请求读到新文案
        $this->refreshCache($lang);

        return [$updated, $skipped];
    }

    /**
     * Clear cached pack for one locale or all locales/
     * 清除指定语言缓存；$lang=null 时清空全部语言
     *
     * @return int number of locales invalidated/失效的语言数
     */
    public function refreshCache(?string $lang = null): int
    {
        $targets = $lang !== null && $lang !== '' ? [$lang] : $this->listLocales();
        $n = 0;
        foreach ($targets as $l) {
            Cache::forget($this->packKey($l));
            $n++;
        }

        return $n;
    }

    /**
     * Assert locale directory exists (and reject path traversal)/
     * 校验语言目录存在（并防目录穿越）
     *
     * @throws ApiException  1004 api.not_found when invalid
     */
    public function assertLocaleExists(string $lang): void
    {
        if ($lang === '' || preg_match('/[^a-zA-Z0-9_\-]/', $lang) !== 0) {
            throw ApiException::notFound('api.not_found');
        }
        if (!is_dir(lang_path() . DIRECTORY_SEPARATOR . $lang)) {
            throw ApiException::notFound('api.not_found');
        }
    }

    // ---------------------------------------------------------------------
    // Internal/内部方法
    // ---------------------------------------------------------------------

    /**
     * Load & flatten all PHP lang files for a locale/
     * 从文件加载并展开某语言全部文案
     *
     * @return array<string,string>
     */
    protected function loadPackFromFiles(string $lang): array
    {
        $dir = lang_path() . DIRECTORY_SEPARATOR . $lang;
        $out = [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $abs = $file->getPathname();
            $rel = ltrim(str_replace($dir, '', $abs), DIRECTORY_SEPARATOR);
            // admin/users.php -> admin.users/子目录文件 -> 点号前缀
            $prefix = str_replace(DIRECTORY_SEPARATOR, '.', substr($rel, 0, -4));

            $arr = include $abs;
            if (!is_array($arr)) {
                continue;
            }

            // Normalize: if the file's returned array is wrapped in a top-level key
            // matching its basename (e.g. api.php => ['api' => [...]]), unwrap it so
            // flattened keys follow Laravel's natural {file}.{key} convention./
            // 归一化：若文件返回数组被同名键包裹（如 api.php 返回 ['api' => [...]]），
            // 解包使展开后 key 符合 Laravel 原生 {file}.{key} 约定。
            if (count($arr) === 1 && isset($arr[$prefix]) && is_array($arr[$prefix])) {
                $arr = $arr[$prefix];
            }

            foreach ($this->flatten($arr) as $k => $v) {
                $out[$prefix . '.' . $k] = $v;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * Recursively flatten nested array into dot-key map/递归展开嵌套数组为点号 key
     *
     * @param  array<mixed>  $arr
     * @return array<string,mixed>
     */
    protected function flatten(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out = array_merge($out, $this->flatten($v, $key));
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /**
     * Split 'api.foo.bar' into ['api', 'foo.bar']/拆分 key 为文件前缀与嵌套路径
     *
     * @return array{0:string,1:string}
     */
    protected function splitKey(string $dotKey): array
    {
        $pos = strpos($dotKey, '.');
        if ($pos === false) {
            return [$dotKey, ''];
        }

        return [substr($dotKey, 0, $pos), substr($dotKey, $pos + 1)];
    }

    /**
     * Resolve file path for a prefix (e.g. 'api' -> lang/{lang}/api.php)/
     * 按前缀解析文件路径（如 'api' -> lang/{lang}/api.php）
     */
    protected function fileForPrefix(string $lang, string $prefix): string
    {
        return lang_path() . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR
            . str_replace('.', DIRECTORY_SEPARATOR, $prefix) . '.php';
    }

    /**
     * Cache key for locale pack/语言包缓存键
     */
    protected function packKey(string $lang): string
    {
        return "locale:pack:{$lang}";
    }

    /**
     * Write array to PHP lang file/把数组写回 PHP 语言文件
     *
     * UTF-8 no BOM, starts with `<?php` + `declare(strict_types=1);`, 4-space short array./
     * UTF-8 无 BOM，以 `<?php` + `declare(strict_types=1);` 开头，4 空格短数组。
     */
    protected function writePhpArrayFile(string $path, array $arr): void
    {
        File::ensureDirectoryExists(dirname($path));
        $body = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->dumpArray($arr, 1) . ";\n";
        File::put($path, $body);
    }

    /**
     * Recursively dump array into PHP expression string/递归把数组转为 PHP 表达式字符串
     *
     * @param  array<mixed>  $arr
     */
    protected function dumpArray(array $arr, int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $outdent = str_repeat('    ', $depth - 1);

        if ($arr === []) {
            return '[]';
        }

        $isList = array_is_list($arr);
        $lines = ['['];
        foreach ($arr as $k => $v) {
            $val = is_array($v) ? $this->dumpArray($v, $depth + 1) : var_export($v, true);
            $key = $isList ? '' : var_export($k, true) . ' => ';
            $lines[] = $indent . $key . $val . ',';
        }
        $lines[] = $outdent . ']';

        return implode("\n", $lines);
    }
}
