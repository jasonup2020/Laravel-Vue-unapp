# 短信验证码模块扩展（sms.md）

> 按需复制。支持注册/登录/改绑手机验证码，Redis 限流 + 验证码表校验。

---

## 1. 场景说明

- C 端手机号登录、注册、找回密码；
- 同一手机号 60 秒内不可重复发，1 天最多 10 次；
- 验证码 5 分钟有效，验证成功即失效，防爆破。

---

## 2. 迁移：sms_codes 表

```php
// database/migrations/2026_01_01_000002_create_sms_codes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20)->index();
            $table->string('scene', 32);              // login|register|reset_password|bind_mobile
            $table->string('code', 6);
            $table->unsignedTinyInteger('used')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['mobile', 'scene']);
        });
    }
    public function down(): void { Schema::dropIfExists('sms_codes'); }
};
```

---

## 3. 代码片段

### 3.1 SmsService

`app/Services/User/SmsService.php`：

```php
namespace App\Services\User;

use App\Exceptions\ApiException;
use App\Enums\ErrorCode;
use App\Models\SmsCode;
use Illuminate\Support\Facades\Cache;

class SmsService
{
    private const RESEND_SECONDS = 60;
    private const DAILY_LIMIT    = 10;
    private const CODE_TTL       = 300; // 5 分钟

    public function send(string $mobile, string $scene): void
    {
        $this->guardRateLimit($mobile, $scene);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 落库（供校验）
        SmsCode::create(compact('mobile', 'scene', 'code'));

        // TODO: 调真实短信网关（阿里云 dysmsapi / 腾讯云 sms）
        // SmsGateway::send($mobile, "【示例】您的验证码是 {$code}，5 分钟内有效。");

        // 记录发送时间戳，做 60 秒重发限制
        Cache::put("sms:sent_at:{$mobile}:{$scene}", time(), self::RESEND_SECONDS);
    }

    public function verify(string $mobile, string $scene, string $code): bool
    {
        $record = SmsCode::where('mobile', $mobile)
            ->where('scene', $scene)
            ->where('used', 0)
            ->where('created_at', '>=', now()->subSeconds(self::CODE_TTL))
            ->latest('id')
            ->first();

        if (!$record || !hash_equals($record->code, $code)) {
            throw new ApiException('api.sms.invalid_code', ErrorCode::BadRequest->value);
        }

        $record->update(['used' => 1, 'used_at' => now()]);
        return true;
    }

    private function guardRateLimit(string $mobile, string $scene): void
    {
        // 60 秒重发限制
        $sentAt = Cache::get("sms:sent_at:{$mobile}:{$scene}");
        if ($sentAt && time() - $sentAt < self::RESEND_SECONDS) {
            throw new ApiException('api.sms.too_frequent', ErrorCode::TooManyRequests->value);
        }

        // 每天 10 次
        $dayKey = 'sms:daily:' . date('Ymd') . ":{$mobile}";
        $count  = Cache::get($dayKey, 0);
        if ($count >= self::DAILY_LIMIT) {
            throw new ApiException('api.sms.daily_limit', ErrorCode::TooManyRequests->value);
        }
        Cache::put($dayKey, $count + 1, 86400);
    }
}
```

### 3.2 控制器

`app/Http/Controllers/Api/User/SmsController.php`：

```php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\User\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private readonly SmsService $svc) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
            'scene'  => 'required|in:login,register,reset_password,bind_mobile',
        ]);
        $this->svc->send($data['mobile'], $data['scene']);
        return message('api.sms.sent');
    }
}
```

路由（登录防爆破限流）：

```php
Route::post('/sms/send', [SmsController::class, 'send'])
    ->middleware('api.composite.rate:5,60');
```

---

## 4. 使用步骤

1. 跑迁移建 `sms_codes` 表；
2. 实现真实 `SmsGateway`（阿里云/腾讯云 SDK）；
3. 在登录/注册接口里调用 `SmsService::verify()` 通过后再发 token；
4. 补 lang key：`api.sms.sent / invalid_code / too_frequent / daily_limit`。

---

## 5. 注意事项

- 验证码表会持续增长，计划任务每天清理 24 小时前记录；
- 网关 AK/SK 放 `.env`，禁止硬编码；
- 测试环境建议加白名单：固定验证码 `123456` 直通，不真发短信；
- 不要把验证码返回在响应里（仅日志 debug 环境）。
