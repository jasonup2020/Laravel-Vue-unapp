# 部署文档（deploy.md）

> 适用工程：laravel13-api-skeleton（Laravel 13 + PHP 8.3）
> 后端根目录：`D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13\`
> 本文命令均以**绝对路径 PHP** 示例，Windows / Linux 通用（路径按实际环境替换）。

---

## 1. 环境要求

| 组件 | 官方推荐 | 本工程实测兼容版本 | 说明 |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8**（宝塔 `D:\BtSoft\php\83\php.exe`） | 扩展必须：`pdo_mysql`、`redis`、`openssl`、`mbstring`、`curl`、`fileinfo`、`bcmath`、`tokenizer`、`xml` |
| MySQL | 8.0 | **5.7.38 实测通过** | 迁移写法兼容 5.7：utf8mb4、JSON 类型可用、不使用 8.0 专属语法（如 `json_table`、窗口函数 DDL） |
| Redis | 6.0+ | **5.0.10 实测通过** | phpredis 扩展可用；避免 5.0 不支持的高阶命令（如 FUNCTION、ZPOPMIN 高版本特性） |
| Nginx | 1.20+ | 任意主流版本 | 反向代理 + SSL + WebSocket 代理 |
| Supervisor | 4.x | Linux 生产守护进程 | Windows 本机开发不需要 |

> 实测说明：本工程在 **MySQL 5.7.38 + Redis 5.0.10** 下完成迁移与中间件联调，生产可直接使用 5.7 系列；若升级到 MySQL 8.0 / Redis 6+ 无需改动代码。

### 1.1 扩展自检命令

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

输出需包含上述扩展名，缺任一即先装扩展再继续。

---

## 2. 从零部署步骤

### 2.1 拉代码并进入后端目录

```powershell
cd D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13
```

### 2.2 复制环境配置文件

```powershell
Copy-Item .env.example .env
```

编辑 `.env`，至少核对/填写以下键（本工程默认值参考）：

```ini
APP_NAME=laravel13-api-skeleton
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_LOCALE=zh-CN
APP_FALLBACK_LOCALE=zh-CN

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ju_test
DB_USERNAME=ju_test
DB_PASSWORD=请替换为真实密码

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# JWT（首次部署后由下面的命令生成，这里先留空）
JWT_SECRET=

# Reverb WebSocket（广播驱动=redis）
REVERB_APP_ID=skeleton
REVERB_APP_KEY=skeleton-key
REVERB_APP_SECRET=skeleton-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# 业务扩展
SNOW_WORKER_ID=0
CORS_ALLOWED_ORIGINS=https://admin.example.com

# 审计日志驱动化存储（不落 MySQL）
AUDIT_LOG_DRIVER=file                      # file | elasticsearch
AUDIT_ES_HOST=http://127.0.0.1:9200       # 仅 elasticsearch 驱动用
AUDIT_ES_INDEX_PREFIX=audit_logs          # ES 索引前缀，按天滚动 audit_logs-YYYY.MM.DD
AUDIT_RETENTION_DAYS=90                    # 文件/索引保留天数，schedule 每日清理
```

> 多实例部署时，**所有实例必须指向同一个 Redis**（`CACHE_STORE=redis`、`QUEUE_CONNECTION=redis`、`BROADCAST_CONNECTION=reverb`），限流计数、JWT 黑名单、菜单缓存、多端登录状态才能全局一致。**禁止生产使用 `CACHE_STORE=file`**。

### 2.2.1 审计日志存储（驱动化，不落 MySQL）

- 管理员操作审计**不建 `admin_operation_logs` 表**，按 `AUDIT_LOG_DRIVER` 存储：
  - `file`（默认）：`storage/logs/audit/audit-YYYY-MM-DD.log`，每行一条 JSON；
  - `elasticsearch`（可选）：写 `AUDIT_ES_INDEX_PREFIX` + `-YYYY.MM.DD` 索引。
- **保留期清理**：`AUDIT_RETENTION_DAYS=90`（默认 90 天），由 schedule 每日任务删除过期文件 / 滚动删除 ES 索引；
- 部署后确认目录可写：`storage/logs/audit/` 权限对 php-fpm 用户可写。

### 2.2.2 限流规则表与 Redis 缓存

- 限流规则存 MySQL 表 `rate_limit_rules`（迁移由数据层提供），默认种子：`api:global` 60/60、`admin:global` 120/60、`login` 5/60、`admin:login` 5/60；
- 运行时全量规则缓存 Redis key = `rate_limit:rules`（TTL 300 秒），后台 `POST/PUT/DELETE /api/admin/rate-limit-rules` 写后自动 `Cache::forget('rate_limit:rules')`，多实例即时生效；
- 多实例共享同一 Redis，规则配置全局一致。

### 2.3 安装 PHP 依赖

> 说明：本工程 vendor 目录已随骨架提供；若全新克隆需执行 composer。Composer 命令格式见下。

```powershell
& "D:\BtSoft\php\83\php.exe" D:\BtSoft\panel\script\composer.phar install --no-dev --optimize-autoloader
```

开发环境去掉 `--no-dev`：

```powershell
& "D:\BtSoft\php\83\php.exe" D:\BtSoft\panel\script\composer.phar install
```

> 依赖包清单（已在 composer.json 声明）：`php-open-source-saver/jwt-auth`（JWT 双守卫）、`fruitcake/laravel-cors`（CORS 白名单）、`laravel/reverb`（WebSocket）。

### 2.4 生成应用 Key

```powershell
& "D:\BtSoft\php\83\php.exe" artisan key:generate
```

该命令写入 `APP_KEY`。

### 2.5 生成 JWT 密钥

```powershell
& "D:\BtSoft\php\83\php.exe" artisan jwt:secret
```

- 该命令由 `php-open-source-saver/jwt-auth` 包提供（包安装完成后自动注册 `jwt:secret` 与 `jwt:publish` 等命令）。
- 它会生成随机 HS256 密钥并写入 `.env` 的 `JWT_SECRET=`，同时把配置发布到 `config/jwt.php`。
- **若提示命令不存在**：说明 jwt-auth 未安装成功，先执行 `composer require php-open-source-saver/jwt-auth`，再重跑本命令；也可手动在 `.env` 写入一段 32+ 位随机串作为 `JWT_SECRET` 效果等价。
- 生产务必确认 `.env` 中 `JWT_BLACKLIST_ENABLED=true`（登出/踢下线靠它拉黑旧 token）。

### 2.6 执行数据库迁移

```powershell
& "D:\BtSoft\php\83\php.exe" artisan migrate --force
```

`--force` 在生产环境跳过确认提示。会创建 users / admins / roles / permissions / admin_role / role_permission / role_menu / admin_menus / products / dicts / messages / admin_operation_logs / user_favorites / ip_blacklist 等全部业务表。

### 2.7 初始化种子数据

```powershell
& "D:\BtSoft\php\83\php.exe" artisan db:seed --class=DatabaseSeeder --force
```

`DatabaseSeeder` 按顺序调用：AdminSeeder → PermissionSeeder → RoleSeeder → MenuSeeder → DemoSeeder。

初始化结果：

- 超级管理员：账号 `admin` / 密码 `admin123`，绑定 `super-admin` 角色（**首次登录后必须立即改密**）。
- 演示管理员：`demo` / `demo123`。
- 全量权限码（33 条）、角色（super-admin / operator）、动态菜单树、5 条商品与 6 条字典演示数据。

> 如需只跑某一个 seeder：`php artisan db:seed --class=AdminSeeder --force`。

### 2.8 清理并缓存配置（生产优化）

```powershell
& "D:\BtSoft\php\83\php.exe" artisan config:clear
& "D:\BtSoft\php\83\php.exe" artisan route:cache
& "D:\BtSoft\php\83\php.exe" artisan config:cache
& "D:\BtSoft\php\83\php.exe" artisan event:cache
```

> 改完 `.env` 或 config 后必须重跑 `config:cache`，否则不生效。开发环境不要 `config:cache`。

---

## 3. 进程托管（Supervisor，Linux 生产）

本工程有三个常驻进程必须后台守护：

| 进程 | 命令 | 作用 |
|---|---|---|
| Reverb WebSocket | `php artisan reverb:start` | 站内消息 WSS 推送 |
| 队列 Worker | `php artisan queue:work --tries=3 --timeout=90` | 异步消息推送、导出等 |
| 计划任务 | `php artisan schedule:work` | 归档历史消息、清理过期 IP 黑名单/Redis 数据、临时文件 |

### 3.1 Supervisor 配置示例（`/etc/supervisor/conf.d/laravel13.conf`）

```ini
[program:laravel13-reverb]
process_name=%(program_name)s
command=/usr/bin/php /www/laravel13-api-skeleton/backend-laravel13/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/www/laravel13-api-skeleton/backend-laravel13/storage/logs/reverb.log

[program:laravel13-queue]
process_name=%(program_name)s
command=/usr/bin/php /www/laravel13-api-skeleton/backend-laravel13/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/www/laravel13-api-skeleton/backend-laravel13/storage/logs/queue.log

[program:laravel13-schedule]
process_name=%(program_name)s
command=/usr/bin/php /www/laravel13-api-skeleton/backend-laravel13/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/www/laravel13-api-skeleton/backend-laravel13/storage/logs/schedule.log
```

加载并启动：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

> Windows 本机开发可直接开三个终端分别手动跑上述三条命令，无需 Supervisor。

---

## 4. Nginx 配置示例

以下配置包含：HTTPS 反向代理、Reverb WebSocket 代理、`X-Forwarded-For` 真实 IP 透传、请求体大小限制。

```nginx
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate     /etc/nginx/ssl/api.example.com.pem;
    ssl_certificate_key /etc/nginx/ssl/api.example.com.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    # 上传/请求体限制（防大表单 DOS）
    client_max_body_size 20m;

    # 安全响应头
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # ---------- 业务 API 反向代理到 PHP-FPM ----------
    location / {
        root /www/laravel13-api-skeleton/backend-laravel13/public;
        try_files $uri $uri/ /index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass   127.0.0.1:9000;   # 或 unix:/run/php/php8.3-fpm.sock
            fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include        fastcgi_params;

            # 透传真实客户端 IP（限流/黑名单/操作日志依赖）
            fastcgi_param  HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
            fastcgi_param  HTTP_X_REAL_IP       $remote_addr;
            fastcgi_param  X-Forwarded-Proto    $scheme;
        }
    }

    # ---------- Reverb WebSocket 代理 ----------
    # 前端 WSS 连接 wss://api.example.com/app/...
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade    $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host       $host;
        proxy_set_header X-Real-IP  $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout  86400;
        proxy_send_timeout  86400;
    }
}
```

> Laravel 侧已在 `bootstrap/app.php` 配置 `trustProxies(at: '*')`，配合上述 `X-Forwarded-For` 即可让 `client_ip()` / 限流拿到真实客户端 IP，而不是网关 IP。

---

## 5. 多实例 Redis 共享说明

1. 所有实例 `.env` 的 `REDIS_HOST` 指向同一台（或同一哨兵集群）Redis。
2. `CACHE_STORE=redis`：菜单缓存 `admin_menu:tree:{adminId}`、限流计数 `composite_rate:*`、幂等记录 `idempotency:*`、IP 黑名单缓存、JWT 黑名单、多端 token 集合 `user:token:list:{userId}` 全部落在共享 Redis。
3. 升级到 Redis Cluster 前注意：本工程 key 均为扁平结构、无 hash tag 强制要求；若切 Cluster，需确认多 key 原子操作（如限流 increment 单 key）不跨 slot。
4. Redis 必须设密码（`requirepass`），防火墙**禁止公网暴露 6379**。

---

## 6. 上线安全检查清单

- [ ] `.env` 中 `APP_DEBUG=false`、`APP_ENV=production`
- [ ] Redis 设置密码，防火墙禁止外网访问 6379
- [ ] Nginx 配置 `X-Forwarded-For` / `X-Real-IP`，Laravel `trustProxies` 生效，`GET /api/health` 日志里 IP 为真实客户端 IP
- [ ] 上传目录（`storage/app/public`）Nginx 禁止执行 PHP
- [ ] 数据库账号最小权限（不使用 root 做业务连接）
- [ ] `JWT_SECRET`、DB 密码、Redis 密码全部在 `.env`，**未硬编码进代码**
- [ ] 关键中间件已注册：`ip.blacklist`、`api.composite.rate`、`lang`
- [ ] Supervisor 三个进程（reverb / queue / schedule）全部 RUNNING
- [ ] 日志按天切割（`storage/logs`），配置磁盘告鳘
- [ ] 初始超级管理员 `admin/admin123` 已改密
- [ ] MySQL 定时备份（mysqldump 每日 + 异地存储）；Redis 开启 RDB/AOF 持久化

---

## 7. Windows 本机开发（宝塔环境）

后端根：`D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13\`

```powershell
# 1. 进入目录
cd D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13

# 2. 首次复制 env（如已有 .env 跳过）
Copy-Item .env.example .env

# 3. 生成 key / jwt secret（首次）
& "D:\BtSoft\php\83\php.exe" artisan key:generate
& "D:\BtSoft\php\83\php.exe" artisan jwt:secret

# 4. 迁移 + 种子
& "D:\BtSoft\php\83\php.exe" artisan migrate:fresh --seed

# 5. 启动开发服务器（任选一个端口）
& "D:\BtSoft\php\83\php.exe" artisan serve --host=0.0.0.0 --port=8000
```

另外开两个终端跑 Reverb 与队列（本机开发可不开 schedule）：

```powershell
# 终端 A：WebSocket
& "D:\BtSoft\php\83\php.exe" artisan reverb:start --host=0.0.0.0 --port=8080

# 终端 B：队列
& "D:\BtSoft\php\83\php.exe" artisan queue:work
```

宝塔面板里把 PHP 版本切到 8.3，网站运行目录指向 `backend-laravel13\public` 即可通过 Nginx 访问。

---

## 8. 10 万级 QPS 部署架构（非功能需求）

> **重要声明**：骨架提供**可水平扩展的架构与最佳实践**，实测 10 万 QPS 需按本节架构部署并压测验证，**骨架单点不承诺 10 万 QPS**。下面是支撑 10 万级 QPS 的完整部署栈与关键调优项。

### 8.1 Nginx worker 调优与长连接

```nginx
# /etc/nginx/nginx.conf
worker_processes  auto;          # = CPU 核数
worker_rlimit_nofile 65535;

events {
    worker_connections  10240;
    multi_accept        on;
    use                 epoll;
}

http {
    sendfile        on;
    tcp_nopush      on;
    tcp_nodelay     on;
    keepalive_timeout  65;
    keepalive_requests 1000;       # 单连接复用次数
    client_max_body_size 20m;

    gzip on;
    gzip_types text/css application/json application/javascript;

    upstream php-fpm {
        server 127.0.0.1:9000;
        keepalive 64;              # Nginx→FPM 长连接，减少 TCP 握手
    }
}
```

### 8.2 PHP-FPM 进程池调优

`www.conf`：

```ini
pm = dynamic
pm.max_children = 40          # 经验值：可用内存 / 单进程常驻(~50MB)；4C8G 起步 30~50
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 20
pm.max_requests = 1000         # 防内存泄漏，处理 1000 请求后重启 worker
request_terminate_timeout = 60
```

> 估算：单 worker 常驻 ~40~60MB，`max_children` 不要压满内存（留 20% 给 OS/Redis）。

### 8.3 OpCache 字节码缓存（必开）

`php.ini`：

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0      # 生产不检查文件 mtime，发版后需 reload php-fpm
opcache.save_comments=1
```

> `validate_timestamps=0` 后，每次发版必须 `systemctl reload php8.3-fpm`，否则新代码不生效。

### 8.4 Redis 热数据与连接

- 全部热路径状态（复合限流 / JWT 黑名单 / 菜单缓存 / 多端 token / 幂等 / IP 黑名单 / Reverb 广播）走 Redis，key 带 TTL；
- 生产 Redis 必须 `requirepass`，开启 AOF/RDB 持久化；
- 多实例部署时所有后端实例连**同一 Redis**（或 Redis Cluster/Sentinel）；
- phpredis 长连接 `persistent=1` 减少短连接握手；
- 用 `redis-cli --bigkeys` / `--hotkeys` 巡检大 key / 热 key。

### 8.5 MySQL 读写分离与从库

- 读多写少场景：主库写，2~3 个从库分担只读（C 端商品列表、字典、消息历史）；
- `config/database.php` 配 `mysql.read` / `mysql.write`：

```php
'mysql' => [
    'read'   => ['host' => ['10.0.0.11', '10.0.0.12']],
    'write'  => ['host' => ['10.0.0.10']],
    'sticky' => true,
],
```

- 业务侧：写后立即读走主库（`sticky=true`）；报表/导出类重查询路由从库；
- 监控主从延迟 `Seconds_Behind_Source`，超阈值告警并临时切回主库。

### 8.6 CDN 与静态资源分离

- 上传文件、商品图片、JS/CSS 全部走 CDN（OSS/COS + CDN）；
- 后端只出 JSON API，二进制静态资源不经过 PHP-FPM；
- 前端构建产物（Vue3 admin）托管 OSS/CDN，Nginx 只反代 API。

### 8.7 多实例水平扩展

- 后端无状态（session 不存本地，全部 Redis/JWT）；
- 前置 Nginx/云 LB 负载均衡到 N 个 PHP-FPM 实例；
- 所有实例共享同一 Redis（限流、菜单缓存、JWT 黑名单、多端登录状态全局一致）；
- Reverb 多实例前置 Redis 广播（BROADCAST_CONNECTION=reverb 已配）。

### 8.8 连接池与长连接

- Nginx→PHP-FPM：upstream `keepalive`；
- PHP→MySQL：PDO 长连接配合 FPM 进程常驻；
- PHP→Redis：`persistent`；
- 对外 HttpClient（短信/OSS/支付）：Guzzle curl 长连接复用。

### 8.9 监控告警

- 健康检查接口 `GET /api/health`，监控系统每 10s 探测 DB + Redis 连通；
- 告警项：HTTP 5xx 比例 >1%、P99 延迟 >800ms、FPM `max_children` 打满、Redis 内存 >80%、MySQL 主从延迟 >5s、磁盘 >80%、队列积压 >1000、Reverb 连接数突降；
- 日志按天切割；慢查询 >500ms 自动 warning（骨架已内置监听）。

---

## 9. 数据层规模化管理（1000 万级）

### 9.1 大表 RANGE 分区（MySQL 5.7 按月）

`messages` 与 `admin_operation_logs` 数据量大，按时间 RANGE 分区。MySQL 5.7 社区版支持分区。

```sql
-- messages 按月分区（建表时即带分区键进主键）
CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT,
  snow_id BIGINT UNSIGNED,
  receiver_type VARCHAR(16),
  receiver_id BIGINT UNSIGNED,
  msg_type VARCHAR(16),
  title VARCHAR(255),
  content TEXT,
  extra JSON NULL,
  is_read TINYINT(1) DEFAULT 0,
  is_pop TINYINT(1) DEFAULT 0,
  created_at DATETIME,
  updated_at DATETIME,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id, created_at),
  KEY idx_receiver (receiver_type, receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
  PARTITION p202601 VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01')),
  PARTITION p202602 VALUES LESS THAN (UNIX_TIMESTAMP('2026-03-01')),
  PARTITION p202603 VALUES LESS THAN (UNIX_TIMESTAMP('2026-04-01')),
  PARTITION pmax   VALUES LESS THAN MAXVALUE
);

-- admin_operation_logs 同理（按 created_at 月分区，无 updated_at）
```

> 分区键 `created_at` 必须包含在主键内；查询带 `created_at` 范围条件时 MySQL 可**分区裁剪**，只扫相关分区。
> 新增分区：计划任务每月初 `ALTER TABLE messages REORGANIZE PARTITION pmax INTO (...)`，避免数据落到 pmax。

### 9.2 归档策略

- `routes/console.php` 计划任务：
  - 每天 02:00 归档 **30 天前已读消息**（`is_read=1`）到历史表 `messages_archive`，原表 DELETE；
  - 每周清理过期 `ip_blacklist`（`expire_at < now()`）；
  - 每月 DROP 老分区（`ALTER TABLE messages DROP PARTITION p202512`）。
- 归档表冷存到 OSS/便宜存储，MySQL 只保留热数据。

### 9.3 user_favorites 分表 / 分区建议

- 千万级收藏量时按 `user_id` 哈希分表：`user_favorites_00 ~ user_favorites_15`（16 表），路由 `user_id % 16`；
- 或单表按 `user_id` RANGE 分区（同 messages 语法）；
- C 端查询永远带 `user_id` 前缀，命中单表/单分区；
- 管理员跨用户查询走 ES / 汇总表，不扫全部分表。

### 9.4 products 只保留在线数据

- 已下架/删除商品超过 180 天迁到 `products_archive`；
- 在线商品表（`products`）保持小而热，配合 Redis 缓存热门商品；
- C 端列表只查 `status=在线` 且未归档数据。

### 9.5 索引清单（与契约第 3 节一致）

| 表 | 索引 | 用途 |
|---|---|---|
| users | `email` unique、`status` | 登录、启停筛选 |
| admins | `username` unique、`status` | 登录 |
| admin_role | `(admin_id, role_id)` 联合主键 | 管理员角色 |
| role_permission | `(role_id, permission_id)` 联合主键 | 角色权限 |
| role_menu | `(role_id, menu_id)` 联合主键 | 角色菜单 |
| admin_menus | `parent_id`、`sort` | 菜单树组装 |
| products | `(status, snow_id)`、`price` | C 端列表筛选 |
| dicts | `(type, status)` | 字典按类型查 |
| messages | `(receiver_type, receiver_id, is_read)`、`created_at`（分区键） | 未读统计、历史拉取 |
| admin_operation_logs | `(admin_id, created_at)`、`is_high_risk`、`created_at`（分区键） | 操作日志筛选 |
| user_favorites | `(user_id, product_id)` 联合唯一、`user_id` | 收藏去重、按用户查 |
| ip_blacklist | `ip` unique、`expire_at` | 黑名单命中 |

> 所有 where/join/orderBy/groupBy 列必须有索引，迁移中 `->comment('用途')` 标注。

---

## 10. keyset 深分页（游标分页）

浅分页（`page/page_size` OFFSET）翻到第 10 万行时性能骤降（MySQL 仍要扫描前面所有行）。深分页改用 **keyset/cursor 分页**。

### 10.1 原理

利用上一页最后一条记录的 `snow_id`（或自增 id）作为游标，`WHERE snow_id < :cursor ORDER BY snow_id DESC LIMIT :page_size`，每次从索引位置直接定位，不扫前面行。

### 10.2 示例 SQL

```sql
-- 第一页（无游标）
SELECT snow_id, name, price FROM products
WHERE status = 1
ORDER BY snow_id DESC
LIMIT 15;

-- 第二页：用上一页最后一条 snow_id=987654 作为游标
SELECT snow_id, name, price FROM products
WHERE status = 1 AND snow_id < 987654
ORDER BY snow_id DESC
LIMIT 15;
```

### 10.3 Laravel 代码示例

```php
public function cursorList(Request $request): JsonResponse
{
    $cursor = $request->query('cursor');
    $pageSize = min((int) $request->query('page_size', 15), 100);

    $q = Product::where('status', 1)->orderByDesc('snow_id');
    if ($cursor) {
        $q->where('snow_id', '<', (int) $cursor);
    }
    $rows = $q->limit($pageSize)->get();

    return message('success', 200, [
        'list'        => $rows,
        'next_cursor' => $rows->last()?->snow_id,
        'has_more'    => $rows->count() === $pageSize,
    ]);
}
```

> 适用：消息历史、日志列表、无限滚动 feed。**不适用**：需要跳页（第 1 页跳第 50 页）或要 total 计数的场景——此时仍用浅分页。
