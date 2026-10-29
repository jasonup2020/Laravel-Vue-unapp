<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base TestCase for all feature tests/功能测试基类
 *
 * Boots the Laravel application and provides HTTP-level login helpers that
 * hit the real JWT login endpoints, so assertions exercise the actual
 * authentication pipeline instead of mocking guards.
 *
 * 启动 Laravel 应用，并提供走真实 JWT 登录接口的 HTTP 层登录助手，
 * 使断言覆盖真实认证链路而非 mock 守卫。
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment/测试环境准备
     *
     * RefreshDatabase only resets the DB; the array cache (rate-limit counters,
     * multi-device token lists, JWT blacklist) would otherwise leak between
     * tests. Flush it after the app boots so each case starts clean.
     *
     * RefreshDatabase 只重置数据库；array 缓存（限流计数、多端 token 集合、
     * JWT 黑名单）否则会跨用例残留。应用启动后清空，保证每用例干净起步。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Flush cross-test cache residue/清理跨测试缓存残留
        \Illuminate\Support\Facades\Cache::flush();
    }

    /**
     * Reset JWT/guard singletons before every dispatched request/
     * 每次分发请求前重置 JWT 与守卫单例
     *
     * In production each HTTP request boots a fresh process, so the JWTGuard
     * user and the JWT token are re-parsed per request. Pest reuses one app
     * instance across multiple requests inside a single test, and jwt-auth
     * caches the first resolved user on JWTGuard::$user and the first non-null
     * token on JWT::$token forever. Without this reset a later request with a
     * different (or cross-guard) bearer token would see the stale cached user
     * and be rejected. Resetting here reproduces production semantics.
     *
     * 生产环境每个 HTTP 请求启动全新进程，JWTGuard 用户与 JWT token 按请求重新解析。
     * Pest 在单个用例内复用同一 app 实例，jwt-auth 会把首个解析出的用户缓存在
     * JWTGuard::$user、首个非空 token 缓存在 JWT::$token 且永不失效。不重置的话，
     * 后续携带不同（或跨守卫）bearer token 的请求会命中陈旧缓存用户而被拒。此处
     * 重置以复现生产语义。
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // Drop cached guard instances (clears JWTGuard::$user)/丢弃已缓存守卫实例（清 JWTGuard::$user）
        app('auth')->forgetGuards();

        // Clear the cached JWT token so the next request re-parses the bearer token/
        // 清空已缓存 JWT token，使下次请求重新解析 bearer token
        app(\PHPOpenSourceSaver\JWTAuth\JWT::class)->unsetToken();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Fetch a fresh captcha and read its plaintext code from Cache (array store in tests)/
     * 拉取新验证码并从 Cache（测试环境 array 驱动）读取明文码.
     *
     * @return array{captcha_id: string, captcha_code: string}
     */
    protected function captchaPair(): array
    {
        $res = $this->getJson('/api/captcha');
        $id = (string) $res->json('data.captcha_id');

        // Test cache driver is array, so we can read back the stored code directly/
        // 测试缓存为 array，可直接回读存储的码
        $code = (string) \Illuminate\Support\Facades\Cache::get("captcha:{$id}");

        return ['captcha_id' => $id, 'captcha_code' => $code];
    }

    /**
     * Login a C-end user via POST /api/login and return context
     * 通过 POST /api/login 登录 C 端用户并返回上下文
     *
     * @param  array<string,mixed>  $attrs  user factory overrides/用户工厂覆盖属性
     * @return array{user: User, headers: array<string,string>, response: \Illuminate\Testing\TestResponse}
     */
    protected function loginUser(array $attrs = []): array
    {
        // Password is fixed to "password" so login payload stays predictable/密码固定为 password 便于登录
        $user = User::factory()->create(array_merge(['password' => 'password'], $attrs));

        // Fetch captcha first (one-time) and attach to payload/先取一次性验证码并入请求
        $captcha = $this->captchaPair();

        // Real login request against the contract endpoint/对契约接口发起真实登录请求
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_id' => $captcha['captcha_id'],
            'captcha_code' => $captcha['captcha_code'],
        ]);

        // Per contract 5.1 login data shape is {token, expires_in}/按契约 5.1 登录 data 结构为 {token, expires_in}
        $token = $response->json('data.token');

        return [
            'user' => $user,
            'headers' => ['Authorization' => 'Bearer '.$token],
            'response' => $response,
        ];
    }

    /**
     * Login an admin via POST /api/admin/login and return auth headers
     * 通过 POST /api/admin/login 登录管理员并返回认证头
     *
     * @return array<string,string>  bearer header array/认证头数组
     */
    protected function loginAdmin(string $username = 'admin', string $password = 'admin123'): array
    {
        // Fetch captcha first (one-time)/先取一次性验证码
        $captcha = $this->captchaPair();

        $response = $this->postJson('/api/admin/login', [
            'username' => $username,
            'password' => $password,
            'captcha_id' => $captcha['captcha_id'],
            'captcha_code' => $captcha['captcha_code'],
        ]);

        $token = $response->json('data.token');

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * Build a C-end auth header array from a raw JWT string
     * 由原始 JWT 字符串构造 C 端认证头数组
     *
     * @return array<string,string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
