# Laravel13 API Skeleton

> 生产级中后台管理系统骨架 · 一套后端，三端复用
> PHP 8.3 + Laravel 13 · Vue3 管理后台 · uni-app 用户端（PC + H5）

**Laravel13 API Skeleton** 是一套生产级中后台管理系统骨架，基于 PHP 8.3 + Laravel 13 + MySQL + Redis 构建，「一套后端、三端复用」，同时服务 Vue3 管理后台与 uni-app 用户端（PC 桌面版 + H5 移动版）。系统以**双表双守卫 JWT** 保障认证安全，权限采用**动作级 RBAC**（`资源:动作` 权限码），管理端实现**单点登录**、C 端支持**多端最多 3 端在线**，登录均带图形验证码与复合限流。内置审计日志（驱动化不落库）、IP 黑名单、幂等防重、响应脱敏、回收站软删恢复、动态菜单（第 5 级）与全站中英双语。数据采用雪花 ID、分页限制与索引完备设计，可支撑千万级数据与十万级 QPS 扩展；自动化测试（Pest 66 项）与多端浏览器实测全部通过，开箱即用。

**🌐 语言 / Language**

[简体中文](README_CN.md) · [English](README_EN.md) · [日本語](README_JP.md) · [Français](README_FR.md) · [Deutsch](README_DE.md) · [Español](README_ES.md) · [한국어](README_KR.md) · [Русский](README_RU.md)

---

## 目录

1. [项目介绍](#1-项目介绍)
2. [核心特性](#2-核心特性)
3. [技术栈](#3-技术栈)
4. [目录结构说明](#4-目录结构说明)
5. [数据库结构](#5-数据库结构)
6. [认证与权限](#6-认证与权限)
7. [统一 API 契约](#7-统一-api-契约)
8. [环境要求](#8-环境要求)
9. [快速开始（开发环境）](#9-快速开始开发环境)
10. [生产部署](#10-生产部署)
11. [演示账号](#11-演示账号)
12. [测试与验证](#12-测试与验证)
13. [相关文档](#13-相关文档)

---

## 1. 项目介绍

**Laravel13 API Skeleton** 是一套生产级中后台管理系统骨架，基于 PHP 8.3 + Laravel 13 + MySQL + Redis 构建，「一套后端、三端复用」，同时服务 Vue3 管理后台与 uni-app 用户端（PC 桌面版 + H5 移动版），实现真正的前后端分离与多端统一。

系统以**双表双守卫 JWT** 保障认证安全：普通用户与后台管理员彻底隔离；`users`/`admins` 密码均加 6 位随机盐后再 Hash 存储，杜绝撞库风险。权限采用**动作级 RBAC**（`资源:动作` 权限码），后台可灵活配置权限、角色与动态菜单，菜单支持到第 5 级并联动 C 端。管理端实现**单点登录**（同一账号只保留一个会话），C 端支持**多端最多 3 端在线**，超出自动踢最老并进黑名单。登录均带图形验证码与复合限流（IP/用户/指纹），接口统一返回 `HTTP 200 + body.code` 状态码规范，支持全站**中英双语**切换。

系统内置审计日志（驱动化存储、不落库）、IP 黑名单、幂等防重、响应脱敏、回收站软删除恢复、慢查询告警、健康检查与代码生成器等增强套件。数据采用雪花 ID、分页限制与索引完备设计，可支撑千万级数据与十万级 QPS 的规模化扩展。自动化测试（Pest 66 项）、迁移重建与多端浏览器实测全部通过，开箱即用。

## 2. 核心特性

| 分类 | 能力 |
|---|---|
| 认证 | 双表双守卫 JWT（C 端 `users` / 管理端 `admins`）、跨守卫隔离、Token 黑名单 |
| 权限 | 动作级 RBAC `{资源:动作}`、角色-权限-菜单三层、动态菜单（≤5 级） |
| 安全 | 图形验证码（Redis 一次性）、复合限流（IP/用户/指纹）、密码加盐、IP 黑名单 |
| 会话 | 管理端单点登录、C 端多端 3 端管控（踢最老 + 黑名单） |
| 多语言 | zh-CN / en 全站切换、dicts/permissions 双语 label、md5 缓存校验 |
| 审计 | 驱动化存储（file/Elasticsearch）、**不落 MySQL**、密码脱敏 |
| 增强 | 幂等防重、响应脱敏、回收站软删恢复、慢查询告警、健康检查、代码生成器 |
| 规模化 | 雪花 ID、分页限制（page_size≤100）、索引完备、缓存 cache-aside |

## 3. 技术栈

| 层 | 技术 |
|---|---|
| 后端 | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth`（双守卫 JWT）· `fruitcake/laravel-cors` · `laravel/reverb`（WebSocket） |
| 存储 | MySQL 5.7+/8.0（库 ju_test）· Redis 5.0+（限流/黑名单/缓存/多端/语言包/验证码） |
| 管理端 | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| 用户端 | uni-app（H5 / 小程序 / App）· HBuilderX 构建 · PC 桌面版 + H5 移动版 |

## 4. 目录结构说明

### 4.1 工程根

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md
│  └─ /README_DE.md / README_ES.md / README_KR.md / README_RU.md   # 项目说明（多语言系列）
├─ PROJECT_SPEC.md        # 权威技术方案 V1.2-Full
├─ SHARED_CONVENTIONS.md  # 唯一协调开发契约
├─ DELIVERY.md            # 交付说明（变更清单 + 验证基线）
├─ TEST_REPORT.md         # 浏览器真实测试报告（§17 多端实测）
├─ SYSTEM_SUMMARY.md      # 系统级总结与结构说明（中文）
├─ SYSTEM_SUMMARY_EN.md   # 系统级总结与结构说明（英文）
├─ _cserver.py            # C 端本地静态服务（托管 H5 + /api 反代 8000）
├─ backup_*.sql           # 数据库备份
├─ backend-laravel13/     # 后端
├─ frontend-vue3/         # 管理端
├─ frontend-uniapp/       # C 端
└─ docs/                  # 部署/开发指南/业务规则/测试用例/账号/代码生成/i18n 同步
```

### 4.2 后端 `backend-laravel13/`

```
app/
├─ Base/                    # 五基类：BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator；StatusCode/ErrorCode/8 业务枚举
├─ Exceptions/              # ApiException + ApiExceptionHandler（HTTP 恒 200 + body.code）
├─ Helpers/                 # message() 助手 + common.php（snow_id/client_ip/fingerprint/mask_text）
├─ Http/
│  ├─ Controllers/          # Api/(C端) + Api/Admin/(管理端) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 个中间件（IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...）
│  └─ Requests/             # User/ + Admin/ 各模块 FormRequest
├─ Models/                  # 14 模型 + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C端) + Admin/(管理端) 双隔离业务层
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider（慢查询 >500ms 监听）

database/
├─ migrations/              # 18 个 create_*（字段中英注释 + 表级 $table->comment）
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule（幂等 upsert）
└─ factories/               # 6 个工厂
lang/{zh-CN,en}/            # api.php（~444 键含 ui.*）+ notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php（94+ 条）+ channels.php + console.php（4 计划任务）
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 个 Pest 文件
docs/                       # 13 主文档 + extensions/ 8 扩展片段
```

### 4.3 管理端 `frontend-vue3/`（17 个页面）

`src/views/`：login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
其他：`layout/index.vue`（顶栏刷新下拉 + 用户下拉）、`layout/MenuTree.vue`（自定义手风琴侧边栏，5 级）、`api/*`、`i18n/index.js`、`store`（Pinia）、`router/index.js`

### 4.4 C 端 `frontend-uniapp/`（8 个页面）

`pages/`：index（首页入口网格+商品）、product/list、product/detail、dict/index、favorite/index、message/index、mine/index、login/index
其他：`App.vue`（PC 桌面版 media query）、`index.html`（PC 顶栏双语）、`common/i18n.js`、`common/request.js`

## 5. 数据库结构（18 张业务表）

| 表 | 用途 | 关键约束 |
|---|---|---|
| `users` | C 端普通用户 | email unique、salt、软删 |
| `password_reset_tokens` / `sessions` | 框架表 | 骨架原样 |
| `admins` | 后台管理员 | username unique、salt、软删 |
| `roles` | 角色 | code unique、软删 |
| `permissions` | 权限（动作级） | code unique、group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | 关联中间表 | 联合主键 |
| `admin_menus` | 后台动态菜单 | parent_id、title_en、软删 |
| `products` | 商品示例 | 索引(status,created_at)、(status,price)、软删 |
| `dicts` | 数据字典 | 索引(type,status)、label_en、物理删 |
| `messages` | 站内消息 | 索引(receiver_type,receiver_id,is_read)、软删 |
| `user_favorites` | 收藏 | (user_id,product_id) 联合唯一、软删 |
| `ip_blacklist` | IP 黑名单 | ip unique、物理删 |
| `rate_limit_rules` | 限流规则 | rule_key unique |
| `notices` | 公告（生成器示例） | — |
| `c_menus` | C 端用户菜单 | parent_id、title_en、仅显隐+父子+多语言 |

> 全部业务表含 `snow_id`（对外暴露）；`$table->comment('表名 - 用途/English')` 已加；审计日志**不建表**（驱动化存储）。

## 6. 认证与权限

- **双守卫 JWT**：`api`（users）/ `admin-api`（admins），payload 带 `guard` claim，跨守卫隔离。
- **密码加盐**：`salt varchar(6)`，`SaltedPasswordCast` 写入 `Hash(明文+盐)`，4 处校验用 `SaltHasher::check`。
- **管理端单点登录**：`CheckAdminOnline`，登录 `Cache::put('admin_online:{id}', token)`，新登录挤旧会话，旧 Token 401 → 前端自动跳登录页。
- **C 端多端 3 端**：`MultiDeviceService`，>3 端 `array_shift` 踢最老 + `invalidate` 进黑名单；管理端可强制踢下线。
- **动作级 RBAC**：`admin.permission:{code}` 中间件按精确码校验；权限缓存 `admin:permissions:{adminId}`（Redis，纯数组）。
- **动态菜单**：缓存 `admin_menu:tree:{adminId}`（TTL 7 天），`buildTree` 按 snow_id 递归，支持 ≤5 级；C 端菜单独立 `c_menus` 表 + `/api/menus` 双语公开接口联动。

## 7. 统一 API 契约

- **HTTP 恒 200 + `body.code`**：`0` 成功 / `1` 通用错误 / `1001` 参数 / `1002` 未认证 / `1003` 无权限 / `1004` 不存在 / `1005` 校验失败 / `1006` 限流 / `1007` 冲突 / `1008` 验证码错误 / `5000` 服务端错误（+2xxxx 业务码）。
- **成功响应**：`{code:0, msg:"操作成功", data:{...}}`；**失败**：`{code:1005, msg:"输入校验失败", data:errors}`。
- **接口分组**：公开（health/captcha/locale/products/dicts/menus/notices）；C 端（auth:api + check.user.online：login/refresh/me/messages/favorites）；管理端（auth:admin-api + admin.permission:{code}：菜单/角色/管理员/用户/商品/字典/消息/收藏/日志/IP/限流/回收站/C菜单/权限/个人中心）。

## 8. 环境要求

| 组件 | 官方推荐 | 本工程实测兼容 | 说明 |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | 扩展：`pdo_mysql`、`redis`、`openssl`、`mbstring`、`curl`、`fileinfo`、`bcmath`、`tokenizer`、`xml` |
| MySQL | 8.0 | **5.7.38** | 迁移写法兼容 5.7（utf8mb4、JSON）；升级 8.0 无需改代码 |
| Redis | 6.0+ | **5.0.10** | phpredis 扩展；避免 5.0 不支持的高阶命令 |
| Nginx | 1.20+ | 任意主流 | 反向代理 + SSL + WebSocket 代理（生产） |
| Supervisor | 4.x | Linux 生产 | 守护 reverb / queue / schedule；Windows 开发不需要 |
| Composer | 2.x | — | 依赖管理 |
| Node.js | 18+ | — | 管理端 Vite 构建 |
| HBuilderX | 最新 | — | C 端 H5 构建（内置 node + uniapp-cli-vite） |

**PHP 扩展自检**：

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. 快速开始（开发环境）

### 9.1 后端（Windows 宝塔 / Linux 通用）

```powershell
cd laravel13-api-skeleton\backend-laravel13
Copy-Item .env.example .env            # 配置 MySQL/Redis/AUDIT_LOG_DRIVER

# 首次生成密钥
& "D:\BtSoft\php\83\php.exe" artisan key:generate
& "D:\BtSoft\php\83\php.exe" artisan jwt:secret

# 开发库重建 + 种子（或 migrate --seed）
& "D:\BtSoft\php\83\php.exe" artisan migrate:fresh --seed

# 启动开发服务器
& "D:\BtSoft\php\83\php.exe" artisan serve --host=127.0.0.1 --port=8000

# 另开终端：Reverb WebSocket 与队列（开发可选）
& "D:\BtSoft\php\83\php.exe" artisan reverb:start --host=0.0.0.0 --port=8080
& "D:\BtSoft\php\83\php.exe" artisan queue:work
```

### 9.2 管理端

```powershell
cd laravel13-api-skeleton\frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173（/api 代理到 8000）
npm run build          # 生产构建
```

### 9.3 C 端（HBuilderX 构建 H5）

```powershell
# 用 HBuilderX 内置 node + @dcloudio/vite-plugin-uni CLI：
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp 绝对路径>
#   node node_modules\@dcloudio\vite-plugin-uni\bin\uni.js build --platform h5
# 产物 frontend-uniapp/dist/build/h5；由 _cserver.py 托管 + /api 反代 8000
# 访问 http://127.0.0.1:5174
```

> PC 桌面版（≥768px）：顶栏导航 + 1200px 居中 + 商品 4 列；手机（<768px）：底部 tabBar + 单列。

## 10. 生产部署

### 10.1 配置 `.env`（关键键）

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<由 jwt:secret 生成>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> 多实例部署必须指向**同一 Redis**（限流/黑名单/菜单缓存/多端状态全局一致）；**禁止生产 `CACHE_STORE=file`**。

### 10.2 迁移与种子

```powershell
& "D:\BtSoft\php\83\php.exe" artisan migrate --force
& "D:\BtSoft\php\83\php.exe" artisan db:seed --class=DatabaseSeeder --force
& "D:\BtSoft\php\83\php.exe" artisan config:clear
& "D:\BtSoft\php\83\php.exe" artisan route:cache && config:cache && event:cache
```

### 10.3 进程托管（Supervisor，Linux）

三个常驻进程：`php artisan reverb:start`（WebSocket）、`php artisan queue:work --tries=3 --timeout=90`（队列）、`php artisan schedule:work`（计划任务）。配置 `/etc/supervisor/conf.d/laravel13.conf` 三个 program 后：

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` 反代到 PHP-FPM，`try_files $uri $uri/ /index.php?$query_string;`
- 透传真实 IP：`fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;`（限流/黑名单/日志依赖）；
- `location /app/` 反代 Reverb（`proxy_set_header Upgrade $http_upgrade;`）开启 WebSocket；
- Laravel `bootstrap/app.php` 已配置 `trustProxies(at:'*')`。

### 10.5 上线安全检查清单

- [ ] `APP_DEBUG=false`、`APP_ENV=production`
- [ ] Redis 设密码，防火墙禁止外网 6379
- [ ] Nginx 透传真实 IP，`/api/health` 日志 IP 正确
- [ ] 上传目录禁止执行 PHP；DB 账号最小权限
- [ ] `JWT_SECRET`/DB/Redis 密码仅存 `.env`，未硬编码
- [ ] 中间件已注册：`ip.blacklist`、`api.composite.rate`、`lang`
- [ ] Supervisor 三进程 RUNNING；日志按天切割
- [ ] 初始 admin/admin123 已改密；MySQL 每日备份 + Redis 持久化

### 10.6 规模化要点（10 万 QPS / 千万级数据）

Nginx worker 调优 + 长连接、PHP-FPM `max_children`/OpCache（`validate_timestamps=0`）、Redis 持久化 + 多实例共享、MySQL 读写分离、`messages`/日志表按月 RANGE 分区、`user_favorites` 分表、keyset 深分页（`snow_id < cursor`）、CDN 静态资源分离、水平扩展无状态后端。详见 [docs/deploy.md](docs/deploy.md)。

## 11. 演示账号

| 端 | 账号 | 角色 | 说明 |
|---|---|---|---|
| 管理端 | admin / admin123 | super-admin | 超级管理员（**首次登录后立即改密**） |
| 管理端 | demo / demo123 | operator | 运营专员（受限菜单） |
| C 端 | user@example.com / password | 普通用户 | C 端登录 |

## 12. 测试与验证

- **自动化测试**：`vendor/bin/pest` → **66 passed / 310 assertions**（Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle）。
- **数据库**：`migrate:fresh --seed` → 18 迁移 + 8 种子全成功；18 张业务表物理注释生效。
- **浏览器实测**：管理端 22 模块 + C 端 PC 版（1280×900）+ H5 版（375×812）+ C 端 3 端机制全部通过（详见 TEST_REPORT §17）。

## 13. 相关文档

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — 系统级总结与结构说明（中文）
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — 系统级总结与结构说明（英文）
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — 技术方案 V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — 开发协调契约
- [DELIVERY.md](DELIVERY.md) — 交付说明
- [TEST_REPORT.md](TEST_REPORT.md) — 测试报告
- [docs/deploy.md](docs/deploy.md) — 部署与规模化
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — 开发文档
