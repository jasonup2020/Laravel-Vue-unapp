<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Base\BaseMiddleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language switch middleware/多语言切换中间件
 *
 * Reads the Accept-Language header or ?lang= query parameter, validates against
 * the whitelist ['zh-CN','en'], falls back to zh-CN on invalid input, and
 * applies the resolved locale via App::setLocale().
 * Extends BaseMiddleware for the shared handle() template and guard() helper.
 * 读取 Accept-Language 头或 ?lang= 参数，校验白名单 zh-CN/en，非法回退 zh-CN，
 * 并通过 App::setLocale() 应用解析出的语言。
 * 继承 BaseMiddleware 复用统一 handle() 模板与 guard() 守卫判定。
 *
 * Middleware alias/中间件别名: lang
 */
class LangSwitch extends BaseMiddleware
{
    /** Allowed locales/允许的语言列表 */
    private const ALLOWED = ['zh-CN', 'en'];

    /**
     * Handle an incoming request/处理请求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *         next pipeline handler/后续管道处理器
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read Accept-Language header/读取 Accept-Language 请求头
        $lang = (string) $request->header('Accept-Language', '');

        // Fallback to ?lang= query param when header absent/头缺失时回退 ?lang= 参数
        if ($lang === '') {
            $lang = (string) $request->query('lang', '');
        }

        // Accept-Language may look like "zh-CN,zh;q=0.9,en;q=0.8": take primary subtag and strip q-factor
        // Accept-Language 可能形如 "zh-CN,zh;q=0.9,en;q=0.8"：取首段子标签并剥离 q-factor
        $lang = trim(explode(',', $lang)[0] ?? '');
        if (str_contains($lang, ';')) {
            $lang = trim(explode(';', $lang)[0]);
        }

        // Reject unsupported locales and fall back to zh-CN/非法语言回退 zh-CN
        if (! in_array($lang, self::ALLOWED, true)) {
            $lang = 'zh-CN';
        }

        // Apply resolved locale to the application/应用解析出的语言
        App::setLocale($lang);

        return $next($request);
    }
}
