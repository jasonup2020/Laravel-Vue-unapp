<?php

declare(strict_types=1);

namespace App\Services\User;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Multi-device login control service for C-end users/普通用户多端登录管控服务.
 *
 * Only applies to the C-end api guard/仅作用于 C 端 api 守卫.
 * Stores valid token set via Cache facade/通过 Cache facade 存储用户有效 Token 集合.
 * Key: user:token:list:{userId}; TTL = config('jwt.refresh_ttl')*60 seconds.
 * Max 3 devices, 4th kicks the oldest/最多 3 端同时在线，第 4 端登录自动踢最早设备.
 */
class MultiDeviceService
{
    /** Max concurrent online devices/最大同时在线终端数 */
    private const MAX_DEVICES = 3;

    /**
     * Build cache key/构造缓存 Key.
     */
    private function key(int $userId): string
    {
        return "user:token:list:{$userId}";
    }

    /**
     * Set TTL in seconds aligned with JWT refresh window/集合 TTL（秒），与 JWT refresh 窗口对齐.
     */
    private function ttl(): int
    {
        return (int) config('jwt.refresh_ttl', 20160) * 60;
    }

    /**
     * Register a new token on login; when over limit, invalidate the oldest one/
     * 登录成功后登记新 token；超出上限踢最早设备并打入黑名单.
     */
    public function register(int $userId, string $token): void
    {
        $key = $this->key($userId);

        // Read existing set/读取现有集合
        $list = Cache::get($key, []);
        if (! is_array($list)) {
            $list = [];
        }

        // Append new token and deduplicate/追加新 token 并去重
        $list[] = $token;
        $list = array_values(array_unique($list));

        // Over 3 devices: kick the earliest one/超过 3 端：踢出最早登录的设备
        if (count($list) > self::MAX_DEVICES) {
            $old = array_shift($list);
            if (is_string($old) && $old !== '') {
                try {
                    // Blacklist the old token/将旧 token 打入 JWT 黑名单
                    JWTAuth::setToken($old)->invalidate();
                } catch (\Throwable $e) {
                    // Tolerate failures to avoid breaking login/并发/已过期 token 失效失败不阻断登录
                    Log::warning('multi device kick invalidate failed', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Write back and refresh TTL/回写集合并刷新 TTL
        Cache::put($key, array_values($list), $this->ttl());
    }

    /**
     * Remove a token on logout/refresh and blacklist it/
     * 登出/刷新时移除指定 token 并打入黑名单.
     */
    public function remove(int $userId, string $token): void
    {
        $key = $this->key($userId);

        $list = Cache::get($key, []);
        if (! is_array($list)) {
            $list = [];
        }

        // Filter out this token/从集合剔除该 token
        $list = array_values(array_filter(
            $list,
            static fn ($t) => $t !== $token
        ));

        Cache::put($key, $list, $this->ttl());

        try {
            // Invalidate the JWT/使该 token 的 JWT 失效
            JWTAuth::setToken($token)->invalidate();
        } catch (\Throwable $e) {
            Log::warning('multi device remove invalidate failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * List all valid tokens of a user/获取用户全部有效 token.
     *
     * @return array<int, string>
     */
    public function listTokens(int $userId): array
    {
        $list = Cache::get($this->key($userId), []);

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * Count online devices of a user/统计用户在线终端数.
     */
    public function count(int $userId): int
    {
        return count($this->listTokens($userId));
    }

    /**
     * Whether a token is still online (used by check.user.online middleware)/
     * 判断某 token 是否仍在线（check.user.online 中间件使用）.
     */
    public function isOnline(int $userId, string $token): bool
    {
        return in_array($token, $this->listTokens($userId), true);
    }

    /**
     * Admin force kick: blacklist all tokens and clear the set/
     * 后台强制踢下线：全部 token 打入黑名单并清空集合.
     */
    public function kickAll(int $userId): void
    {
        // Invalidate every token/遍历全部 token 逐个 invalidate
        foreach ($this->listTokens($userId) as $t) {
            try {
                JWTAuth::setToken($t)->invalidate();
            } catch (\Throwable $e) {
                Log::warning('multi device kickAll invalidate failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear the redis set/清空 Redis 集合
        Cache::forget($this->key($userId));
    }
}
