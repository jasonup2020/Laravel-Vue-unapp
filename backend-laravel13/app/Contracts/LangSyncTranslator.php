<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * LangSync translator contract/翻译器契约
 *
 * Implement this interface to plug in OpenAI / Google / self-hosted MT engines.
 * The default CopyWithMarkerTranslator simply copies the source text back and
 * leaves a TODO marker in logs; production teams replace the binding in
 * config/lang_sync.php with a real MT-backed implementation.
 * 实现此接口即可接入 OpenAI / Google / 自研翻译引擎；默认 CopyWithMarkerTranslator
 * 直接回传源文本并在日志留 TODO 标记。生产环境在 config/lang_sync.php 中替换绑定为
 * 真实 MT 实现即可。
 */
interface LangSyncTranslator
{
    /**
     * Translate a single string/翻译单条字符串
     *
     * @param  string  $text       source text/源文本
     * @param  string  $sourceLang  source locale, e.g. zh-CN/源语言
     * @param  string  $targetLang  target locale, e.g. en/目标语言
     */
    public function translate(string $text, string $sourceLang, string $targetLang): string;
}
