<?php

declare(strict_types=1);

/**
 * Pest configuration/Pest 配置
 *
 * Apply the base TestCase (boots the Laravel app) and RefreshDatabase
 * to every test under tests/Feature. RefreshDatabase migrates an ephemeral
 * SQLite :memory: database per test so cases stay isolated.
 *
 * 为 tests/Feature 下全部用例挂载基础 TestCase（启动 Laravel 应用）与
 * RefreshDatabase（每个用例在临时 SQLite :memory: 库上迁移，保证用例隔离）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');
