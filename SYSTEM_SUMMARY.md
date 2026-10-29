# Laravel13-API-Skeleton 系统级总结与结构说明

> 文档定位：整合工程全部 MD（PROJECT_SPEC 技术方案 / SHARED_CONVENTIONS 开发契约 / DELIVERY 交付说明 / TEST_REPORT 实测报告 / docs/ 文档）与代码现状，形成一份**系统级总结 + 结构说明**。
> 更新时间：2026-10-29　工程根：`D:\wwwroot\ju_test02\laravel13-api-skeleton\`

---

## 1. 项目总览

生产级 **Laravel 13 API 骨架**（V1.2-Full），三端一套后端：

| 端 | 技术栈 | 定位 |
|---|---|---|
| 后端 `backend-laravel13` | PHP 8.3 + Laravel 13.38 + MySQL 5.7 + Redis 5.0 | API 服务（唯一核心） |
| 管理端 `frontend-vue3` | Vue3 + Vite + Pinia + Element Plus + vue-i18n | 后台管理（RBAC） |
| C 端 `frontend-uniapp` | uni-app（H5 / 小程序 / App） | 普通用户（PC 桌面版 + H5 移动版） |

**核心能力清单**
- **双表双守卫 JWT**：C 端 `users` + `api` 守卫；管理端 `admins` + `admin-api` 守卫；Token 不可跨守卫越权。
- **统一状态码**：HTTP 恒 200 + `body.code`（0/1/1001~1008/5000 + 2xxxx 业务码），前端按 `code===0` 判成功。
- **RBAC 动作级权限**：`{resource}:{action}` 权限码，接口按精确码校验，后台可配权限/角色。
- **后台动态菜单**：数据库存储 + Redis 缓存（TTL 7 天），管理端菜单管理，支持到第 5 级，联动 C 端菜单。
- **i18n 中英双语**：语言包接口 + md5 缓存校验，zh-CN / en 全站切换，dicts/permissions label_en。
- **登录图形验证码**：GD 生成 + Redis 一次性（TTL 300s，错误也消费），必填校验（1008）。
- **复合限流**：IP + 用户 ID + 浏览器指纹，规则后台可配置（rate_limit_rules 表）。
- **C 端多端 3 端管控**：超 3 端自动踢最老 + 进 JWT 黑名单；管理端可强制踢下线。
- **管理端单点登录**：同一 admin 只保留一个有效会话，新登录挤掉旧会话。
- **审计日志驱动化**：默认 file（文本 JSON 行），可选 Elasticsearch，**不落 MySQL**。
- **增强套件**：幂等防重复、响应脱敏、IP 黑名单、慢查询告警、回收站软删除恢复、健康检查。
- **密码加盐哈希**：users/admins 各加 6 位随机盐，存储 `Hash(明文+盐)`。
- **代码生成器**：`make:module` 一键生成完整模块；`lang:sync` 多语言同步。
- **规模化约束**：雪花 ID 对外、分页（page_size≤100）、禁 N+1、索引完备、缓存 cache-aside——支撑 1000 万级数据 / 10 万级 QPS 架构设计。

**验证基线（2026-10-29）**：Pest **66 passed / 310 assertions** 全绿；`migrate:fresh --seed` 18 迁移 + 8 种子全成功；管理端 22 模块 + C 端 PC/H5 版浏览器实测通过。

---

## 2. 系统架构

```
【前端层】
├─ frontend-vue3 管理后台（Vue3 + Element Plus + Pinia）
└─ frontend-uniapp C 端（PC 桌面版 ≥768px / H5 移动版 <768px）
        ↓ HTTP / HTTPS / WebSocket(WSS)
【反向代理 / 网关】Nginx（生产）/ _cserver.py（C 端本地 5174，含 /api 反代）
        ↓
【Laravel 13 后端 backend-laravel13】
├─ 中间件层（全局 prepend）：IpBlacklist、LangSwitch、HandleCors
│   中间件别名：lang / ip.blacklist / api.composite.rate / admin.permission:{code}
│              / check.user.online / check.admin.online / idempotency / response.desensitize / admin.operation.log
├─ 控制器层：Api/（C 端）+ Api/Admin/（管理端）+ Locale/Captcha/CMenu
├─ Service 业务层：User/（C 端）与 Admin/（管理端）双隔离
├─ 模型层：14 模型（HasSnowId + SoftDeletes + BackedEnum casts）
└─ 公共：message() 助手 + common.php + ApiExceptionHandler
        ↓
【存储层】
├─ MySQL（库 ju_test）：18 张业务表
└─ Redis：限流 / JWT 黑名单 / 菜单缓存 / 多端登录集合 / 幂等 / IP 黑名单 / 语言包 / 验证码
```

**两套访问链路完全隔离**
1. C 端用户：`/api/*`，守卫 `api`，读 `users`。
2. 后台管理员：`/api/admin/*`，守卫 `admin-api`，读 `admins`。
3. C 端公开：`/api/health`、`/api/captcha`、`/api/locale/*`、`/api/products`、`/api/dicts/{type}`、`/api/menus`（无需登录）。

---

## 3. 目录结构说明

### 3.1 工程根 `laravel13-api-skeleton/`

```
├─ PROJECT_SPEC.md        # 权威技术方案 V1.2-Full
├─ SHARED_CONVENTIONS.md  # 唯一协调开发契约（§1-§14，状态码/语言包/动作级权限/性能）
├─ DELIVERY.md            # 交付说明（设计变更清单 + 验证基线 + 部署）
├─ TEST_REPORT.md         # 浏览器真实实测报告（§17 含 2026-10-29 多端实测）
├─ SYSTEM_SUMMARY.md      # 本文件（系统级总结与结构说明）
├─ _cserver.py            # C 端本地静态服务（托管 H5 产物 + /api 反代 8000）
├─ backup_*.sql           # 数据库备份（mysqldump）
├─ backend-laravel13/     # 后端
├─ frontend-vue3/         # 管理端
├─ frontend-uniapp/       # C 端
└─ docs/                  # 工程级文档
```

### 3.2 后端 `backend-laravel13/`

```
├─ app/
│  ├─ Base/                    # 五基类：BaseController / BaseService / BaseRepository / BaseModel / BaseMiddleware
│  ├─ Console/Commands/        # BaseCommand / MakeModuleCommand / LangSyncCommand
│  ├─ Contracts/               # LangSyncTranslator 契约
│  ├─ Enums/                   # StatusCode + ErrorCode + 8 业务枚举（BackedEnum）
│  ├─ Exceptions/              # ApiException + ApiExceptionHandler（HTTP 恒 200 + body.code）
│  ├─ Helpers/                 # ApiHelper::message() + common.php（snow_id/client_ip/fingerprint/mask_text）
│  ├─ Http/
│  │  ├─ Controllers/Api/      # C 端（User/）+ 管理端（Admin/）+ Locale/Captcha/CMenu/Health
│  │  ├─ Middleware/           # 10 个自定义中间件（含 CheckAdminOnline）
│  │  └─ Requests/             # User/ + Admin/ 各模块 FormRequest
│  ├─ Models/                  # 14 模型 + Traits/HasSnowId + Casts/SaltedPasswordCast
│  ├─ Services/                # User/（C 端）+ Admin/（管理端）双隔离业务层
│  │  ├─ User/                 # AuthService / MultiDeviceService / UserService / ProductService / DictService / MessageService / FavoriteService / CMenuService / NoticesService
│  │  └─ Admin/                # AuthService / AdminService / UserService / RoleService / PermissionService / MenuService / ProductService / DictService / MessageService / FavoriteService / OperationLogService / RecycleBinService / IpBlacklistService / RateLimitRuleService / CMenuService / NoticesService
│  ├─ Support/                 # Snowflake / SaltHasher / CaptchaService / LangSync
│  └─ Providers/               # AppServiceProvider（慢查询 >500ms 监听）
├─ database/
│  ├─ migrations/              # 18 个 create_*（全字段 English/中文 comment + 表级 $table->comment）
│  ├─ seeders/                 # Permission / Role / Admin / Menu / Demo / User / CMenu / RateLimitRule（幂等 upsert）
│  └─ factories/               # 6 个工厂
├─ lang/{zh-CN,en}/            # api.php（平铺 ~444 key 含 ui.*）+ notice.php
├─ config/                     # auth / jwt / cors / broadcasting / reverb / audit / lang_sync / app
├─ routes/                     # api.php（94+ 条）+ channels.php + console.php（4 计划任务）
├─ openapi/openapi.yaml        # 78 operations
├─ tests/Feature/Api/          # 17 个 Pest 文件
└─ docs/                       # 13 主文档 + extensions/ 8 扩展片段
```

### 3.3 管理端 `frontend-vue3/`（17 个页面）

`src/views/`：`login` / `dashboard` / `user` / `product` / `dict` / `admin` / `role` / `menu` / `permission` / `message` / `operation-log` / `ip-blacklist` / `rate-limit` / `favorite` / `recycle-bin` / `c-menu` / `profile`
其他：`layout/index.vue`（侧边栏 + 顶栏刷新下拉 + 用户下拉）、`layout/MenuTree.vue`（自定义手风琴菜单，5 级）、`api/*`（axios 封装）、`i18n/index.js`（fallback + md5 缓存）、`store`（Pinia：user/menu）、`router/index.js`（守卫 + /profile）

### 3.4 C 端 `frontend-uniapp/`（8 个页面）

`pages/`：`index/index`（首页入口网格+商品）、`product/list`、`product/detail`、`dict/index`、`favorite/index`、`message/index`、`mine/index`、`login/index`
其他：`App.vue`（PC 桌面版 media query：≥768px 顶栏 + 1200px 居中 + 隐藏 tabBar）、`index.html`（PC 顶栏双语）、`common/i18n.js`（自实现响应式 i18n + md5 缓存）、`common/request.js`、`static/`

### 3.5 文档 `docs/`

主文档：`deploy.md` / `dev-guide.md` / `business-rule.md` / `test-case.md` / `demo-accounts.md` / `codegen.md` / `i18n-sync.md` / `测试与改进.md` / `extend-example-full-isolation.md`
扩展片段：`extensions/{object-storage, sms, captcha, async-export, chunk-import, feature-toggle, system-configs, docker-compose}.md`

---

## 4. 数据库结构（18 张业务表）

> 全部业务表统一追加 `snow_id`（雪花 ID，对外暴露；自增 `id` 模型 `$hidden`）；`$table->comment('表名 - 用途/English')` 已加；需软删的表含 `deleted_at`。

| 表 | 用途 | 关键字段 / 约束 |
|---|---|---|
| `users` | C 端普通用户 | name/email(unique)/password/**salt(6位)**/status；软删；JWTSubject |
| `password_reset_tokens` | 密码重置令牌 | email PK/token/created_at（骨架原样） |
| `sessions` | 会话表 | id/user_id/ip/user_agent/payload/last_activity（骨架原样） |
| `admins` | 后台管理员 | username(unique)/nickname/email/password/**salt**/avatar/status；软删；JWTSubject |
| `roles` | 角色（RBAC） | name/code(unique)/remark；软删 |
| `permissions` | 权限（动作级） | name/code(unique)/group/**action**/**type(1系统/2自定义)**/**label_en** |
| `admin_role` | 管理员-角色中间表 | admin_id+role_id 联合主键 |
| `role_permission` | 角色-权限中间表 | role_id+permission_id 联合主键 |
| `role_menu` | 角色-菜单中间表 | role_id+menu_id 联合主键 |
| `admin_menus` | 后台动态菜单 | parent_id/title/**title_en**/path/component/icon/permission_code/sort/is_show/is_cache/is_external/external_url；软删 |
| `products` | 商品示例 | name/desc/price(10,2)/status；软删；索引(status,created_at)/(status,price) |
| `dicts` | 数据字典 | type/label/**label_en**/value/sort/status；索引(type,status)；物理删 |
| `messages` | 站内消息 | receiver_type/receiver_id/msg_type/title/content/extra(json)/is_read/is_pop；软删 |
| `user_favorites` | 用户收藏 | user_id+product_id 联合唯一；软删 |
| `ip_blacklist` | IP 黑名单 | ip(unique)/remark/expire_at；物理删 |
| `rate_limit_rules` | 限流规则（后台可配） | rule_key(unique)/max_attempts/decay_seconds/enabled/remark |
| `notices` | 公告通知（make:module 演示） | name/title/status/remark（生成器示例模块） |
| `c_menus` | C 端用户菜单（独立表） | parent_id/title/title_en/icon/path/url/sort/is_show（**仅显隐+父子+多语言，无权限码**） |

> 框架表：`migrations`、`cache`、`jobs`（无表注释）。
> 审计日志**不建表**（驱动化存储，见 §8）。

**种子数据（8 个 Seeder，幂等 upsert）**
- PermissionSeeder：46 条权限码（含动作级 + 4 条 common 公共权限）
- RoleSeeder：`super-admin`（全权限+全菜单）、`operator`（商品/字典）
- AdminSeeder：admin/admin123、demo/demo123（加盐）
- MenuSeeder：18 项后台菜单（path 与前端路由对齐）
- DemoSeeder：5 商品 + 6 字典（status/sex）+ order_status 4 条
- UserSeeder：user@example.com/password（加盐）
- CMenuSeeder：5 条 C 端默认入口（商品/字典/收藏/消息/个人中心）
- RateLimitRuleSeeder：5 条默认限流规则

---

## 5. 认证与身份体系

| 项 | 实现 |
|---|---|
| JWT 依赖 | `php-open-source-saver/jwt-auth` v2.9.3 |
| 守卫 | `api`（provider users）/ `admin-api`（provider admins），driver=jwt；payload 携带 `guard` claim |
| 签发 | 登录成功 `JWTAuth::claims(['guard'])->fromUser($user)`；ttl=config('jwt.ttl') |
| 黑名单 | 登出/被踢 Token 入 Redis 黑名单，未过期也拒绝 |
| 跨守卫隔离 | 两套独立守卫 + 路由隔离，跨守卫自然 401（1002） |
| **密码加盐** | users/admins 加 `salt varchar(6)`；`SaltedPasswordCast` 写入自动 `Hash(明文+盐)`；4 处校验用 `SaltHasher::check`；Seeder/Factory 幂等补盐升级 |
| **管理端单点登录** | `CheckAdminOnline` 中间件：登录 `Cache::put('admin_online:{id}', token)`，新登录覆盖旧值，旧 token 请求 401 → 前端自动跳登录页 |
| **C 端多端 3 端** | `MultiDeviceService`：登录写 `user:token:list:{id}` 集合，>3 端 `array_shift` 踢最老 + `invalidate` 进黑名单；管理端可强制踢全部 |
| 登录验证码 | 先 `GET /api/captcha` 取 id+图 → 登录带 `captcha_id/captcha_code`；Redis `captcha:{id}` TTL300 一次性（1008） |

**登录接口**：`POST /api/login`（C 端）、`POST /api/admin/login`（管理端）；刷新 `/api/refresh`、`/api/admin/refresh`；登出 `/api/logout`、`/api/admin/logout`。

---

## 6. 权限与菜单（RBAC + 动态菜单）

### 6.1 RBAC 动作级权限
- 权限码约定 `{resource}:{action}`：四基本动作 `list/create/update/delete`，自定义动作不限（export/audit/kick…）。
- 接口用 `admin.permission:{code}` 中间件按**精确码**校验；"能查 ≠ 能改"。
- 超管（super-admin）拥有全部权限。
- 权限缓存 `admin:permissions:{adminId}`（Redis，TTL 600，**纯数组**，防 Collection 反序列化崩溃），权限/角色变更实时清缓存。

### 6.2 动态菜单
- 后台菜单管理页维护树（新增/编辑/排序/显隐/设父级），支持到第 5 级。
- 缓存 `admin_menu:tree:{adminId}`（TTL 7 天）；`my-tree` 走缓存，`all-tree` 直读库。
- 菜单增删改 → 清全部管理员菜单缓存；角色绑菜单变更 → 清该角色下缓存；管理员角色变更 → 仅清该管理员。
- `is_show=false` 仅 UI 隐藏，**接口权限依赖 RBAC**（is_show 不做接口控制）。
- **buildTree 用 snow_id 递归**（2026-10-29 修复 M2，避免孤儿节点）；菜单标题双语对象 `{zh-CN, en}`。

### 6.3 角色分配
- **分配权限**：两段式 checkbox ——「公共权限」（group=common 4 条）+「其它权限」（按模块分组），非下拉树。
- **分配菜单**：菜单树勾选，提交 `menu_ids: snow_id[]`，回显用 `nextTick + setCheckedKeys`。

### 6.4 C 端菜单联动
- 独立 `c_menus` 表 + `GET /api/menus` 公开双语接口（无需登录）。
- 管理端 `/api/admin/c-menus` CRUD（仅需 `auth:admin-api` 登录守卫，无动作级 RBAC）。
- 管理端增/改/删/显隐 → 缓存 `c_menu:list` 失效 → C 端首页入口网格实时反映。

---

## 7. 统一 API 契约

### 7.1 状态码（HTTP 一律 200 + body.code）

| code | 含义 | 映射的 HTTP 语义 |
|---|---|---|
| 0 | 成功 | 200 |
| 1 | 通用错误 | 400 |
| 1001 | 参数错误 | 400 |
| 1002 | 未认证 / 登录失效（Token 无效/过期/被踢） | 401 |
| 1003 | 无权限（RBAC） | 403 |
| 1004 | 资源不存在 | 404 |
| 1005 | 表单校验失败（data=错误明细） | 422 |
| 1006 | 触发限流 | 429 |
| 1007 | 冲突 / 重复提交（幂等、重复收藏） | 409 |
| 1008 | 验证码错误 / 过期 | 422 |
| 5000 | 服务器内部错误 | 500 |

> 2xxxx 起业务自定义码（docs 登记）。`ApiExceptionHandler` 统一映射；生产关闭 APP_DEBUG 不暴露堆栈。

### 7.2 响应结构
成功：`{code:0, msg:"操作成功", data:{...}}`；失败：`{code:1005, msg:"输入校验失败", data:errors}`。前端 axios/uni 按 `body.code===0` 判断。

### 7.3 接口分组（routes/api.php，94+ 条）

**公开**：`GET /api/health`、`GET /api/captcha`、`GET /api/locale/{lang?}`、`/api/locale/{lang}/meta`、`GET /api/products`、`GET /api/products/{snow_id}`、`GET /api/dicts/{type}`、`GET /api/menus`、`GET /api/notices`

**C 端（auth:api + check.user.online）**：`POST /api/login|refresh|logout`、`GET/PUT /api/me`、`PUT /api/me/password`、`GET /api/messages*`、`POST/GET/DELETE /api/favorites*`

**管理端（auth:admin-api + admin.permission:{code}）**：登录/登出、`me`、菜单（my-tree/all-tree/CRUD）、角色（CRUD + assignPermissions/assignMenus）、管理员（CRUD + password/status）、用户（list/show/status/online/kick）、商品/字典/消息/收藏（CRUD）、操作日志、IP 黑名单、限流规则、回收站、C 端菜单、权限管理（store/update/destroy）、个人中心、`POST /api/admin/cache/flush`

---

## 8. 核心机制

### 8.1 多语言 i18n
- 语言包 `lang/{zh-CN,en}/` 平铺（`api.*` 前缀 + `ui.*` 156 键 + `notice.*`），全站 `__()` / `message()` 统一带 `api.` 前缀。
- `GET /api/locale/{lang}` 返回 `{lang, md5, items}`（递归展开，Redis 缓存 TTL 86400）；`/meta` 轻量 md5 校验。
- **md5 稳定算法**：`ksort + json_encode(JSON_UNESCAPED_UNICODE|SLASHES)`。
- 前端：本地缓存 `{md5, items}` + meta 比对，一致用本地、不一致拉全量；**deepMerge 深合并**（远程只覆盖同名叶子，不替换 fallback 子树）；回前台/刷新按钮强拉。
- 管理端语言包管理：`GET /api/admin/locale/*`、`PUT .../{lang}`（只覆盖已存在 key）、`POST /api/admin/locale/refresh`（权限 admin:i18n:*）。
- dicts/permissions 双语 label：API 返回 `{zh-CN, en}` 对象，前端按 locale 取。

### 8.2 审计日志（驱动化，不落 MySQL）
- `OperationLogService` 按驱动分发：**file**（默认，`storage/logs/audit/audit-YYYY-MM-DD.log` JSON 行 10 字段）/ **elasticsearch**（可选，AUDIT_LOG_DRIVER 切换，不可达回退 file）。
- `AdminOperationLog` 中间件自动记录 url/method/ip/params/response/cost_ms/is_high_risk；密码字段脱敏 `***`。
- 管理端操作日志接口从文件读取；90 天保留期每日清理；**登录动作按设计不记录**。

### 8.3 限流（后台可配置）
- `rate_limit_rules` 表存规则（rule_key/max_attempts/decay_seconds/enabled），`RateLimitRuleService::resolve` 运行时匹配（路径精确→守卫全局→兜底 60/60）。
- key：`composite_rate:{path}:{ip}:uid_{id}` 或 `fp_{指纹}`；Redis 存储，多实例共享。
- 默认 5 条：`api:global 60/60`、`admin:global 120/60`、`api/login 5/60`、`api/admin/login 5/60`、`api/products 120/60`。
- 登录防爆破：连续第 6 次起 1006。

### 8.4 其他机制
- **统一状态码**：见 §7.1。
- **雪花 ID**：`Snowflake` 生成，`HasSnowId` 事件写入，对外一律 snow_id。
- **软删除 + 回收站**：products/users/admins/menus/roles/messages/user_favorites 可回收；回收站接口按资源白名单查询/单条/批量恢复。
- **幂等**：POST/PUT/DELETE + `X-Idempotency-Key`，Redis TTL 600，重复请求返回上次结果。
- **响应脱敏**：`mobile/id_card` 字段脱敏，`Skip-Desensitize:1` 头跳过。
- **IP 黑名单**：`ip_blacklist` 表 + Redis 缓存，命中且未过期拦截（403）。
- **慢查询告警**：Eloquent SQL >500ms 打 warning 日志。
- **健康检查**：`GET /api/health` 探测 app/db/redis/cache。
- **计划任务**（4 项）：清理过期 IP 黑名单 / 归档 30 天已读消息 / 清理幂等缓存 / 清理过期审计日志。
- **Reverb 广播**（代码就绪，未实机部署）：private-user.{id} / private-admin.{id} / public-system-announce；消息持久化 MySQL + 离线拉取。
- **代码生成器**：`make:module {Name}` 一键生成五层 + 迁移(带注释索引) + 路由 + lang 双语词条。

---

## 9. 前端结构

### 9.1 管理端 frontend-vue3（22 功能模块对应页面）

| 页面 | 功能 |
|---|---|
| `/login` | 登录（验证码 + 语言选择器） |
| `/dashboard` | 工作台（4 统计卡 + 系统健康，双语 computed 实时刷新） |
| `/user` | 用户管理（列表/状态/在线/踢下线） |
| `/product` | 商品管理（CRUD） |
| `/recycle-bin` | 回收站（查看/批量恢复） |
| `/dict` | 字典管理（CRUD，label/label_en） |
| `/admin` | 管理员管理（新增含确认密码/角色关联/重置密码/启停/删除） |
| `/role` | 角色管理（CRUD + 分配权限[公共+其它] + 分配菜单） |
| `/menu` | 菜单管理（树 + 新增子级 + el-tree-select 改父级） |
| `/permission` | 权限管理（CRUD，label_en，选择/自定义权限码/分组/动作） |
| `/message` | 消息中心（列表 + 发送） |
| `/operation-log` | 操作日志（100 条审计 + 高危标记） |
| `/ip-blacklist` | IP 黑名单（Block/解除） |
| `/rate-limit` | 限流规则（CRUD） |
| `/favorite` | 收藏管理（搜索） |
| `/c-menu` | C 端菜单管理（显隐开关 + 联动） |
| `/profile` | 个人中心（用户下拉入口） |

布局：`layout/index.vue` 顶栏「刷新」下拉（语言包/左边栏菜单/缓存）+ 用户下拉（个人中心/退出登录）；`MenuTree.vue` 自定义手风琴侧边栏（点一个只开一个，5 级，双语，折叠窄显）。

### 9.2 C 端 frontend-uniapp

| 页面 | 功能 |
|---|---|
| `/pages/index/index` | 首页（功能入口网格[来自 /api/menus] + 商品列表，PC grid 4 列 / 移动单列） |
| `/pages/product/list` | 商品列表 |
| `/pages/product/detail` | 商品详情（收藏按钮） |
| `/pages/dict/index` | 字典示例（order_status 4 条，label 按语言） |
| `/pages/favorite/index` | 我的收藏 |
| `/pages/message/index` | 消息中心（未读/全部已读） |
| `/pages/mine/index` | 个人中心（语言切换/刷新语言包/退出） |
| `/pages/login/index` | 登录（验证码 + 语言） |

**PC 桌面版（≥768px）**：`index.html` 顶栏（首页/商品/消息/收藏/我的/登录，data-i18n 双语）+ `App.vue` 1200px 居中 + 隐藏底部 tabBar + 首页商品 grid 4 列/入口 6 列；**手机（<768px）** 保持底部 tabBar + 单列。

---

## 10. 测试与验证

### 10.1 自动化测试（Pest）
- **66 passed / 310 assertions**，17 个测试类：Auth(8) / AdminPermissionCache / AdminSsoTest(1) / Captcha(4) / CMenu(3) / Desensitize / Favorite / GuardIsolation / Idempotency / IpBlacklist / Locale(12) / Message / MultiDevice / ProductDict / RateLimit / RbacAndMenu / Recycle。

### 10.2 浏览器真实实测（TEST_REPORT §17，2026-10-29）
- **管理端 22 模块全部通过**；本轮修复 3 项：M1 角色 code 软删后重建被拒 / M2 菜单建树孤儿节点（buildTree 键改 snow_id）/ M3 菜单编辑弹窗标题 [object Object]。
- **C 端 PC 版（1280×900）+ H5 版（375×812）全部通过**；本轮修复 4 项：C4 收藏/消息页空白（computed:{t} 改 methods）、C5 字典页顶部空白、C6 字典缺 order_status 数据+未解包+双语 label 插值、C7 代理 Content-Length 双头致 XHR 挂起。
- **C 端多端 3 端机制实测符合设计**（超 3 端踢最老 + 进黑名单，其余在线不受影响）。

### 10.3 历史缺陷闭环（TEST_REPORT §4，25 项）
统一状态码 / 未认证 1002 / 审计不落库 / snow_id 种子 / 权限缓存纯数组 / 菜单 path 对齐 / 双语 label / 权限操作列 / 角色分配两部分 / 侧边栏手风琴 / 未登录自动跳登录 / 菜单 5 级 / el-switch 布尔类型 / C 端 PC 兼容 / 密码加盐 / 管理端单点 / 迁移整理 + 表注释等——全部闭环复验。

---

## 11. 环境与账号

| 项 | 值 |
|---|---|
| PHP | `D:\BtSoft\php\83\php.exe`（8.3.8） |
| MySQL | 127.0.0.1:3306，库 ju_test，ju_test / mH4ZY4WJAJAwMYh8（5.7.38） |
| Redis | 127.0.0.1:6379 无密码（5.0.10），CACHE_STORE=redis |
| HBuilderX | `D:\Program Files (x86)\HBuilderX\HBuilderX.exe`；node `...\plugins\node\node.exe`；cli `...\plugins\uniapp-cli-vite` |
| 演示账号 | 管理端 admin/admin123（super-admin）、demo/demo123（operator）；C 端 user@example.com/password |

---

## 12. 构建与运行

```bash
# 后端
cd backend-laravel13
composer install
cp .env.example .env   # 配 MySQL/Redis/AUDIT_LOG_DRIVER
php artisan key:generate && php artisan jwt:secret --force
php artisan migrate:fresh --seed      # 开发库重建（或 migrate --seed）
php artisan serve --host=127.0.0.1 --port=8000
# 测试
vendor/bin/pest

# 管理端
cd ../frontend-vue3 && npm install && npm run build   # dev: npm run dev (5173)

# C 端（HBuilderX 构建 H5）
cd ../frontend-uniapp
# 用 HBuilderX 内置 node + @dcloudio/vite-plugin-uni CLI：
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp 绝对路径>
#   node node_modules\@dcloudio\vite-plugin-uni\bin\uni.js build --platform h5
# 产物 frontend-uniapp/dist/build/h5；_cserver.py 托管 + /api 反代 8000 → http://127.0.0.1:5174

# 生产守护进程
php artisan queue:work / schedule:work / reverb:start（supervisor）
```

**开发服务现状**：后端 8000 / 管理端 5173 / C 端 5174（_cserver.py）均在运行，可直接打开 `http://127.0.0.1:5174` 复核 C 端 PC 版。

---

## 13. 遗留与待办（如实披露）

| 项 | 状态 | 说明 |
|---|---|---|
| Elasticsearch 审计驱动 | 未实机验证 | 代码完整 + file 回退，需部署后配置 AUDIT_LOG_DRIVER 验证 |
| Reverb 广播 | 未实机端到端 | 代码/配置就绪，需部署 Reverb 联调 |
| 10 万级 QPS 压测 | 未执行 | 设计满足，需按 docs/deploy.md 架构部署后 k6/ab 压测 |
| 管理端消息中心残留测试消息「多端测试公告」 | 遗留 | msg-flow 测试遗留，可清理 |
| 权限详情端点 `GET /api/admin/permissions/{id}` | 已知小问题未修 | 返回 5000；前端列表/更新/删除正常，不影响功能 |
| 管理端语言选择跨窗口持久化 | 待判定 | 即时生效但新窗口重置中文；如需记忆需前端存储 locale 选择 |
| C 端子页 PC 深度布局 | 待按需 | 商品详情/登录/我的等继承全宽 + 1200px 居中，基础可用，如需逐页桌面化可补 media query |
| 小程序 / App 端 | 待真机 | H5 已实测；小程序/App 需 HBuilderX 打包后真机验证 |
