<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Base\BaseController;
use App\Services\Locale\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Locale pack controller (C-end, public, no auth)/
 * 语言包控制器（C 端公开，无鉴权）
 *
 * Frontend calls this on app boot to load all i18n strings for the active locale
 * so in-app message edits take effect without redeploy./
 * 前端启动时调用本接口加载当前语言全部文案，后台改文案后无需发版即可生效。
 */
class LocaleController extends BaseController
{
    public function __construct(
        protected LocaleService $service,
    ) {
    }

    /**
     * GET /api/locale/{lang?}/获取语言包
     *
     * Lang resolution order/语言解析顺序：
     * 1) path param {lang} / 路径 {lang}
     * 2) query ?lang=xx / query 参数
     * 3) config('app.locale') / 配置默认语言
     *
     * Response/响应：{success:true, code:0, msg, data:{lang, md5, items:{...}}}
     * md5 is a stable fingerprint of items; frontend can cache locally and compare./
     * md5 为 items 的稳定指纹，前端可本地缓存后比对。
     * Illegal lang -> body code 1004 api.not_found/非法语言 -> 体 code 1004。
     */
    public function pack(Request $request, ?string $lang = null): JsonResponse
    {
        $lang = $lang ?? $request->query('lang') ?? (string) config('app.locale', 'zh-CN');

        $pack = $this->service->pack($lang);

        return message('api.success', 0, [
            'lang'  => $pack['lang'],
            'md5'   => $pack['md5'],
            'items' => $pack['items'],
        ]);
    }

    /**
     * GET /api/locale/{lang}/meta/轻量元信息
     *
     * Returns only {lang, md5} without items — frontend uses this to decide
     * whether to re-fetch the full pack./
     * 仅返回 {lang, md5}，不返回 items；前端据此判断是否需要重新拉全量包。
     */
    public function meta(string $lang): JsonResponse
    {
        $meta = $this->service->meta($lang);

        return message('api.success', 0, $meta);
    }
}
