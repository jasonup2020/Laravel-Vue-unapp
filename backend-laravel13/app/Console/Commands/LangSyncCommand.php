<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\LangSyncTranslator;
use Illuminate\Support\Facades\File;

/**
 * lang:sync — copy missing i18n keys from source locale to target locales/
 * lang:sync — 把源语言缺失的 key 同步到目标语言
 *
 * Scans lang/{source}/**​/*.php, recursively merges missing keys into each
 * target locale's lang files. Existing target keys are preserved unless
 * --overwrite is passed. With --translate, missing values are routed through
 * the configured translator (default: copy source text, log TODO).
 * 扫描 lang/{source}/**​/*.php，递归把目标语言缺失的 key 合并过去；
 * 默认保留目标已有键，--overwrite 时覆盖；--translate 时把新增值送配置的翻译器。
 */
class LangSyncCommand extends BaseCommand
{
    /**
     * The name and signature/命令签名
     *
     * {--source=zh-CN}  source locale/源语言
     * {--targets=}      comma-separated target locales, or "all"/逗号分隔目标语言，或 all
     * {--translate}     run translator on new values/对新增值调用翻译器
     * {--overwrite}     overwrite existing target keys/覆盖目标已有键
     * {--dry-run}       preview only, do not write files/仅预览不写文件
     */
    protected $signature = 'lang:sync
                            {--source=zh-CN : Source locale/源语言}
                            {--targets= : Comma-separated targets or "all"/逗号分隔目标或 all}
                            {--translate : Run translator on new values/对新增值调用翻译器}
                            {--overwrite : Overwrite existing target keys/覆盖目标已有键}
                            {--dry-run : Preview only, do not write files/仅预览}';

    /**
     * The console command description/命令描述
     */
    protected $description = 'Sync missing i18n keys from source locale to targets/同步多语言缺失键';

    /**
     * Translator instance/翻译器实例
     */
    protected ?LangSyncTranslator $translator = null;

    /**
     * Execute the console command/执行命令
     */
    public function handle(): int
    {
        $source = (string) $this->option('source');
        $targetsOpt = (string) $this->option('targets');
        $useTranslate = (bool) $this->option('translate');
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        $langDir = lang_path();
        $sourceDir = $langDir . DIRECTORY_SEPARATOR . $source;

        if (!is_dir($sourceDir)) {
            $this->error("Source locale dir not found/源语言目录不存在: {$sourceDir}");

            return self::FAILURE;
        }

        $targets = $this->resolveTargets($langDir, $source, $targetsOpt);
        if ($targets === []) {
            $this->warn('No target locales resolved/未解析到目标语言');

            return self::SUCCESS;
        }

        if ($useTranslate) {
            $this->translator = $this->resolveTranslator();
            $this->info('Using translator/使用翻译器: ' . get_class($this->translator));
        }

        // Collect source files recursively/递归收集源语言文件
        $exclude = (array) config('lang_sync.exclude', []);
        $sourceFiles = $this->collectPhpFiles($sourceDir, $exclude);

        $this->info("Source/源语言: {$source}, targets/目标: " . implode(',', $targets));
        $this->line("Source files/源文件数: " . count($sourceFiles));
        $this->newLine();

        $totalAdded = 0;
        $totalOverwritten = 0;

        foreach ($sourceFiles as $relPath => $absPath) {
            $sourceArr = $this->loadArray($absPath);

            foreach ($targets as $target) {
                $targetFile = $langDir . DIRECTORY_SEPARATOR . $target . DIRECTORY_SEPARATOR . $relPath;
                $targetArr = is_file($targetFile) ? $this->loadArray($targetFile) : [];

                [$merged, $added, $overwritten, $translated] = $this->mergeRecursive(
                    $sourceArr,
                    $targetArr,
                    $source,
                    $target,
                    $overwrite,
                );

                $totalAdded += $added;
                $totalOverwritten += $overwritten;

                if ($added === 0 && $overwritten === 0) {
                    $this->line("  [{$target}] {$relPath}: up to date/已是最新");
                    continue;
                }

                $this->line(sprintf(
                    "  [%s] %s: +%d new, ~%d overwrite%s",
                    $target,
                    $relPath,
                    $added,
                    $overwritten,
                    $dryRun ? ' (dry-run, not written)' : ''
                ));

                if ($translated !== []) {
                    foreach ($translated as $dotKey => $pair) {
                        $this->line("      translate/翻译: {$dotKey}  {$pair[0]} -> {$pair[1]}");
                    }
                }

                if (!$dryRun) {
                    $this->writePhpArrayFile($targetFile, $merged);
                }
            }
        }

        $this->newLine();
        $this->info("Done/完成: +{$totalAdded} added, ~{$totalOverwritten} overwritten" . ($dryRun ? ' (dry-run, no files written)' : ''));

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Internal/内部方法
    // ---------------------------------------------------------------------

    /**
     * Resolve target locales/解析目标语言列表
     *
     * @return array<int,string>
     */
    protected function resolveTargets(string $langDir, string $source, string $opt): array
    {
        if ($opt === '' || $opt === 'all') {
            $dirs = glob($langDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            $targets = [];
            foreach ($dirs ?: [] as $d) {
                $name = basename($d);
                if ($name !== $source) {
                    $targets[] = $name;
                }
            }

            return $targets;
        }

        return array_values(array_filter(array_map('trim', explode(',', $opt))));
    }

    /**
     * Collect PHP files under source dir/收集源语言目录下的 PHP 文件
     *
     * @param  array<int,string>  $exclude  basenames without .php/排除的文件名
     * @return array<string,string>  [relativePath => absolutePath]
     */
    protected function collectPhpFiles(string $dir, array $exclude): array
    {
        $out = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $base = $file->getBasename('.php');
            if (in_array($base, $exclude, true)) {
                continue;
            }
            $rel = ltrim(str_replace($dir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $out[$rel] = $file->getPathname();
        }
        ksort($out);

        return $out;
    }

    /**
     * Load a PHP lang file as array/加载 PHP 语言文件为数组
     *
     * @return array<mixed>
     */
    protected function loadArray(string $path): array
    {
        $data = include $path;

        return is_array($data) ? $data : [];
    }

    /**
     * Recursively merge source into target/递归合并源数组到目标数组
     *
     * Keys present in source but missing in target are added; keys present in
     * both are preserved (or overwritten when $overwrite=true).
     * 源有目标无的 key 补入；两边都有则保留目标值（$overwrite=true 时覆盖）。
     *
     * @param  array<mixed>  $source
     * @param  array<mixed>  $target
     * @return array{0:array<mixed>,1:int,2:int,3:array<string,array{0:string,1:string}>}
     */
    protected function mergeRecursive(array $source, array $target, string $sourceLang, string $targetLang, bool $overwrite, string $dotPrefix = ''): array
    {
        $added = 0;
        $overwritten = 0;
        $translated = [];

        foreach ($source as $key => $srcValue) {
            $dotKey = $dotPrefix === '' ? (string) $key : $dotPrefix . '.' . $key;

            if (is_array($srcValue)) {
                // Nested: recurse/嵌套：递归
                $tgtValue = isset($target[$key]) && is_array($target[$key]) ? $target[$key] : [];
                [$merged, $a, $o, $t] = $this->mergeRecursive($srcValue, $tgtValue, $sourceLang, $targetLang, $overwrite, $dotKey);
                $target[$key] = $merged;
                $added += $a;
                $overwritten += $o;
                $translated = array_merge($translated, $t);
                continue;
            }

            // Scalar/标量
            if (!array_key_exists($key, $target)) {
                $target[$key] = $this->maybeTranslate((string) $srcValue, $sourceLang, $targetLang);
                $added++;
                if ($this->translator) {
                    $translated[$dotKey] = [(string) $srcValue, (string) $target[$key]];
                }
            } elseif ($overwrite) {
                $target[$key] = $this->maybeTranslate((string) $srcValue, $sourceLang, $targetLang);
                $overwritten++;
                if ($this->translator) {
                    $translated[$dotKey] = [(string) $srcValue, (string) $target[$key]];
                }
            }
            // else: keep target value/否则保留目标值
        }

        return [$target, $added, $overwritten, $translated];
    }

    /**
     * Run translator if configured/若配置了翻译器则执行翻译
     */
    protected function maybeTranslate(string $text, string $sourceLang, string $targetLang): string
    {
        if ($this->translator === null) {
            return $text;
        }

        return $this->translator->translate($text, $sourceLang, $targetLang);
    }

    /**
     * Resolve translator instance from config/从配置解析翻译器实例
     */
    protected function resolveTranslator(): LangSyncTranslator
    {
        $class = (string) config('lang_sync.translator', \App\Support\LangSync\CopyWithMarkerTranslator::class);

        return app($class);
    }

    /**
     * Write array to PHP lang file/把数组写回 PHP 语言文件
     *
     * UTF-8 no BOM, starts with <?php + declare(strict_types=1);
     * UTF-8 无 BOM，以 <?php + declare(strict_types=1); 开头。
     */
    protected function writePhpArrayFile(string $path, array $arr): void
    {
        File::ensureDirectoryExists(dirname($path));
        $body = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->dumpArray($arr, 1) . ";\n";
        File::put($path, $body);
    }

    /**
     * Recursively dump array to PHP expression string/递归把数组转为 PHP 表达式字符串
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
            if (is_array($v)) {
                $val = $this->dumpArray($v, $depth + 1);
            } else {
                $val = var_export($v, true);
            }
            $key = $isList ? '' : var_export($k, true) . ' => ';
            $lines[] = $indent . $key . $val . ',';
        }
        $lines[] = $outdent . ']';

        return implode("\n", $lines);
    }

    /**
     * Command examples/命令示例
     *
     * @return array<int,array{cmd:string,desc:string}>
     */
    protected function examples(): array
    {
        return [
            ['cmd' => 'lang:sync --source=zh-CN --targets=en', 'desc' => 'Sync missing keys to en/同步缺失键到英文'],
            ['cmd' => 'lang:sync --source=zh-CN --targets=en,ja,ko', 'desc' => 'Sync to multiple targets/同步到多个目标语言'],
            ['cmd' => 'lang:sync --source=zh-CN --targets=all --translate --dry-run', 'desc' => 'Preview translation sync to all locales/预览同步到全部语言（带翻译）'],
            ['cmd' => 'lang:sync --source=zh-CN --targets=en --overwrite', 'desc' => 'Overwrite existing target keys/覆盖目标已有键'],
            ['cmd' => 'lang:sync --show-full-help', 'desc' => 'Show this bilingual help/显示本完整双语帮助'],
        ];
    }
}
