<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Snowflake ID generator/雪花 ID 生成器
 *
 * Bit layout (64-bit signed int, always positive)/位布局（64 位有符号 int，恒为正）：
 * - 1 bit sign (0)
 * - 41 bits millisecond timestamp (relative to EPOCH)
 * - 5  bits workerId (from config('app.snow_worker_id', 0))
 * - 12 bits per-millisecond sequence
 *
 * Clock rollback protection/防时钟回拨：
 * when current time < last timestamp, reuse the last timestamp to stay monotonic;
 * when the sequence exhausts within the same ms, virtually advance 1 ms to avoid duplicates.
 */
class Snowflake
{
    /** Epoch start: 2024-01-01 00:00:00 UTC (ms)/纪元起点：2024-01-01 00:00:00 UTC（毫秒） */
    private const EPOCH = 1704067200000;

    private const WORKER_ID_BITS = 5;
    private const SEQUENCE_BITS  = 12;

    private const MAX_WORKER_ID = -1 ^ (-1 << self::WORKER_ID_BITS); // 31
    private const SEQUENCE_MASK = -1 ^ (-1 << self::SEQUENCE_BITS);  // 4095

    private const WORKER_ID_SHIFT = self::SEQUENCE_BITS;
    private const TIMESTAMP_SHIFT = self::SEQUENCE_BITS + self::WORKER_ID_BITS;

    private static int $lastTimestamp = 0;
    private static int $sequence      = 0;
    private static ?int $workerId     = null;

    /** Generate next snowflake ID/生成下一个雪花 ID */
    public static function id(): int
    {
        $workerId  = self::workerId();
        $timestamp = self::nowMs();

        // Clock rollback: reuse last timestamp to stay monotonic/时钟回拨保护：沿用上一次时间戳，保证单调递增
        if ($timestamp < self::$lastTimestamp) {
            $timestamp = self::$lastTimestamp;
        }

        if ($timestamp === self::$lastTimestamp) {
            // Same ms: increment sequence/同一毫秒内序列自增
            self::$sequence = (self::$sequence + 1) & self::SEQUENCE_MASK;
            if (self::$sequence === 0) {
                // Sequence exhausted: virtually advance 1 ms/序列耗尽：虚拟推进 1ms，下一时刻再重置序列
                $timestamp = self::$lastTimestamp + 1;
            }
        } else {
            self::$sequence = 0;
        }

        self::$lastTimestamp = $timestamp;

        return (($timestamp - self::EPOCH) << self::TIMESTAMP_SHIFT)
            | ($workerId << self::WORKER_ID_SHIFT)
            | self::$sequence;
    }

    /**
     * Resolve workerId from config and clamp into valid range/取 workerId，从 config 读取并做边界钳制
     */
    private static function workerId(): int
    {
        if (self::$workerId === null) {
            $wid = (int) config('app.snow_worker_id', 0);
            if ($wid < 0) {
                $wid = 0;
            }
            if ($wid > self::MAX_WORKER_ID) {
                $wid = self::MAX_WORKER_ID;
            }
            self::$workerId = $wid;
        }

        return self::$workerId;
    }

    private static function nowMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
}
