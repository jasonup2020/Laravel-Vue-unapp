# 图形滑块验证码扩展（captcha.md）

> 按需复制。高危接口（登录、短信发送、改密）在多次失败后强制弹滑块，防机器批量撞库。

---

## 1. 场景说明

- 登录失败 N 次后要求滑块验证通过才放行；
- 滑块二次校验在服务端完成，前端拿到的 ticket 一次性使用；
- 配置开关可按接口粒度启停。

---

## 2. 配置

`.env`：

```ini
CAPTCHA_ENABLED=true
CAPTCHA_SLIDER_THRESHOLD=160     # 滑动距离容忍误差 px
```

`config/app.php` 追加：

```php
'captcha' => [
    'enabled'   => (bool) env('CAPTCHA_ENABLED', true),
    'threshold' => (int) env('CAPTCHA_SLIDER_THRESHOLD', 160),
],
```

---

## 3. 代码片段

### 3.1 CaptchaService（生成 + 校验）

`app/Services/CaptchaService.php`：

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CaptchaService
{
    private const TTL = 300; // 5 分钟

    /** 生成拼图缺口坐标（生产可用 intervention/image 切图） */
    public function generate(): array
    {
        $x = random_int(80, 260);
        $ticket = bin2hex(random_bytes(16));

        // 把正确答案写 Redis，key=ticket
        Cache::put("captcha:answer:{$ticket}", $x, self::TTL);

        return [
            'ticket'   => $ticket,
            'bg_url'   => '/storage/captcha/bg/' . $this->pickBg() . '.jpg',
            'slider_x' => $x, // 生产不返回给前端，由前端拖拽后回传位置
        ];
    }

    public function verify(string $ticket, int $userX): bool
    {
        $key = "captcha:answer:{$ticket}";
        $answer = Cache::get($key);
        if ($answer === null) {
            return false;
        }
        // 一次性：无论对错都删
        Cache::forget($key);

        return abs($answer - $userX) <= config('captcha.threshold', 10);
    }

    private function pickBg(): string
    {
        return (string) random_int(1, 5);
    }
}
```

### 3.2 中间件 `CaptchaRequired`

`app/Http/Middleware/CaptchaRequired.php`：

```php
namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Enums\ErrorCode;
use App\Services\CaptchaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptchaRequired
{
    public function __construct(private readonly CaptchaService $captcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('captcha.enabled', false)) {
            return $next($request);
        }

        $ticket = (string) $request->header('X-Captcha-Ticket', '');
        $x      = (int) $request->header('X-Captcha-X', 0);

        if ($ticket === '' || !$this->captcha->verify($ticket, $x)) {
            throw new ApiException('api.captcha.required', ErrorCode::Forbidden->value);
        }

        return $next($request);
    }
}
```

注册别名（`bootstrap/app.php`）：

```php
$middleware->alias([
    // ...已有
    'captcha' => \App\Http\Middleware\CaptchaRequired::class,
]);
```

### 3.3 路由挂载（示例：登录失败 3 次后强制 captcha）

```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('api.composite.rate:5,60');
// 第二次登录起要求前端先调 GET /captcha，再带 ticket 走下面这条
Route::post('/login-with-captcha', [AuthController::class, 'login'])
    ->middleware(['api.composite.rate:5,60', 'captcha']);
```

> 实际策略：登录服务在该 IP/手机号失败计数 ≥3 时，响应里带 `captcha_required=true`，前端自动切到 captcha 流程。

### 3.4 生成接口

```php
// app/Http/Controllers/Api/CaptchaController.php
public function show(CaptchaService $svc): JsonResponse
{
    return message('success', 200, $svc->generate());
}
// GET /api/captcha
```

---

## 4. 使用步骤

1. 写 `CaptchaService` 与中间件，注册别名 `captcha`；
2. 前端集成滑块组件，登录失败计数触发；
3. 高危路由挂 `captcha`；
4. 补 lang key：`api.captcha.required`。

---

## 5. 注意事项

- 仅后端校验不够安全，生产建议接腾讯云/极验等第三方滑块，把本片段换成对应 SDK；
- ticket 一次性，Redis 5 分钟过期；
- 不要把正确答案 `slider_x` 返回前端；
- 测试环境可设 `CAPTCHA_ENABLED=false` 跳过。
