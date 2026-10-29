<?php

declare(strict_types=1);

namespace App\Support\LangSync;

use App\Contracts\LangSyncTranslator;
use Illuminate\Support\Facades\Log;

/**
 * Default translator: copy source text, log TODO markers/默认翻译器：复制源文本并记录 TODO
 *
 * PHP arrays cannot carry inline translation markers inside string values without
 * polluting runtime output, so this implementation simply returns the source text
 * unchanged and logs a list of untranslated strings. Teams integrate a real MT
 * engine by swapping the binding in config/lang_sync.php.
 * PHP 数组无法在字符串值内联翻译标记而不污染运行时输出，因此本实现直接返回源文本原样，
 * 并把待翻译清单写入日志。接入真实翻译时在 config/lang_sync.php 替换绑定即可。
 */
class CopyWithMarkerTranslator implements LangSyncTranslator
{
    /**
     * Translate: return source text unchanged, log a TODO/翻译：原样返回源文本并记录 TODO
     */
    public function translate(string $text, string $sourceLang, string $targetLang): string
    {
        Log::debug("[lang:sync] TODO translate from {$sourceLang} to {$targetLang}: {$text}");

        return $text;
    }
}
