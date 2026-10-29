<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Base\BaseController;
use App\Http\Requests\Admin\LocaleUpdateRequest;
use App\Services\Locale\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin locale management controller/后台多语言管理控制器
 *
 * Endpoints/接口：
 * - GET  /api/admin/locale/languages  list available locales/列出可用语言
 * - GET  /api/admin/locale/{lang}     view flattened pack/查看某语言扁平包
 * - PUT  /api/admin/locale/{lang}     batch overwrite existing keys/批量覆盖已存在 key
 * - POST /api/admin/locale/refresh    manually clear cache/手动清缓存
 */
class LocaleController extends BaseController
{
    public function __construct(
        protected LocaleService $service,
    ) {
    }

    /**
     * List available locales/列出可用语言
     *
     * Permission: admin:i18n:list/权限：admin:i18n:list
     */
    public function languages(): JsonResponse
    {
        return message('api.success', 0, [
            'list' => $this->service->listLocales(),
        ]);
    }

    /**
     * View flattened pack for a locale/查看某语言扁平包
     *
     * Permission: admin:i18n:list/权限：admin:i18n:list
     */
    public function show(string $lang): JsonResponse
    {
        $items = $this->service->pack($lang);

        return message('api.success', 0, [
            'lang'  => $lang,
            'items' => $items,
        ]);
    }

    /**
     * Batch overwrite existing keys/批量覆盖已存在 key
     *
     * Body/请求体：{"items": {"api.success": "新文案", ...}}
     * Only existing keys are updated; unknown keys are reported in data.skipped./
     * 仅覆盖已存在 key；未知 key 放入 data.skipped 返回。
     *
     * Permission: admin:i18n:update/权限：admin:i18n:update
     */
    public function update(LocaleUpdateRequest $request, string $lang): JsonResponse
    {
        /** @var array<string,string> $items */
        $items = $request->validated()['items'] ?? [];

        [$updated, $skipped] = $this->service->update($lang, $items);

        return message('api.success', 0, [
            'lang'    => $lang,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Manually refresh locale pack cache/手动刷新语言包缓存
     *
     * Body (optional)/请求体（可选）：{"lang": "zh-CN"} — empty refreshes all locales./
     * 不带 lang 时清空全部语言缓存。
     *
     * Permission: admin:i18n:refresh/权限：admin:i18n:refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $lang = $request->input('lang');
        $n = $this->service->refreshCache(is_string($lang) ? $lang : null);

        return message('api.success', 0, [
            'refreshed' => $n,
        ]);
    }
}
