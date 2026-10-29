<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | LangSync translator binding/lang:sync 翻译器绑定
    |--------------------------------------------------------------------------
    |
    | FQCN of the translator used when `--translate` is passed. The default
    | CopyWithMarkerTranslator returns source text unchanged and logs TODO
    | markers. Swap to a real MT-backed implementation (OpenAI / Google /
    | self-hosted) by pointing this at your own class.
    | 传 --translate 时使用的翻译器类名。默认 CopyWithMarkerTranslator 原样返回源文本并记日志。
    | 接入 OpenAI/Google/自研翻译时把本项指向自己的实现类即可。
    |
    */
    'translator' => \App\Support\LangSync\CopyWithMarkerTranslator::class,

    /*
    |--------------------------------------------------------------------------
    | Source locale/源语言
    |--------------------------------------------------------------------------
    */
    'source' => env('LANG_SYNC_SOURCE', 'zh-CN'),

    /*
    |--------------------------------------------------------------------------
    | Excluded files/不同步的文件
    |--------------------------------------------------------------------------
    |
    | Filenames (without .php) that should never be touched by lang:sync.
    | lang:sync 跳过的文件名（不带 .php 后缀）。
    |
    */
    'exclude' => [
        //
    ],
];
