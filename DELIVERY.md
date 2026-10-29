# DELIVERY.md — Laravel 13 API 骨架交付说明

> 工程根：`D:\wwwroot\ju_test02\laravel13-api-skeleton\`　后端：`backend-laravel13\`（Laravel 13.38.0 全新骨架就地开发）
> 权威规格：`PROJECT_SPEC.md`（V1.2-Full）　协调契约：`SHARED_CONVENTIONS.md`（§1–§14，含状态码体系、语言包、动作级权限、性能约束）

---

## 1. 交付总览

生产级 Laravel 13 API 骨架，双表双守卫 JWT（users/admins + api/admin-api），覆盖：RBAC 动作级权限、动态菜单、Redis 复合限流（后台可配置）、多端登录管控、Reverb 站内消息、审计日志驱动化（不落 MySQL）、统一状态码（HTTP 一律 200 + body.code）、i18n 中英双语（语言包接口 + md5 缓存校验）、登录图形验证码（GD/一次性/1008 码）、前端多语言选择器 + PC/手机响应式、代码生成器（make:module）、多语言同步（lang:sync）、回收站/幂等/IP 黑名单/脱敏/慢查询/健康检查增强套件、1000 万级数据/10 万级 QPS 工程约束落实。

**验证基线**：Pest 功能测试 **65 passed / 307 assertions 全绿**（17 个测试类，含验证码、语言包旧缓存兜底、权限缓存冷热、浏览器 Accept 未认证、C端菜单联动）；MySQL ju_test 真库 `migrate:fresh --seed` 全部建表+种子成功（7 表 snow_id 无空）；94 条路由注册。

## 2. 工程目录结构

```
laravel13-api-skeleton/
├── PROJECT_SPEC.md               # 权威规格 V1.2-Full
├── SHARED_CONVENTIONS.md         # 唯一协调契约（§1-§14）
├── DELIVERY.md                   # 本文件
├── backend-laravel13/            # Laravel 13 后端（核心交付）
│   ├── app/
│   │   ├── Base/                 # 五基类：BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
│   │   ├── Console/Commands/     # BaseCommand(showFullHelp) + MakeModuleCommand + LangSyncCommand
│   │   ├── Contracts/            # LangSyncTranslator 契约
│   │   ├── Enums/                # StatusCode(0/1/1001-1008/5000) + ErrorCode(内部映射) + 8 业务枚举
│   │   ├── Exceptions/           # ApiException + ApiExceptionHandler（HTTP 恒 200 + body.code）
│   │   ├── Helpers/              # ApiHelper::message() + common.php + client_ip/fingerprint/snow_id
│   │   ├── Http/
│   │   │   ├── Controllers/      # Api/（C 端）+ Api/Admin/（管理端）+ Locale
│   │   │   ├── Middleware/       # 8 个自定义中间件（全部 extends BaseMiddleware）
│   │   │   └── Requests/         # 各模块 FormRequest
│   │   ├── Models/               # 14 模型（含 HasSnowId、软删除、BackedEnum casts）
│   │   ├── Services/             # 双端 Auth(含验证码)/RBAC/消息/商品字典/限流规则/多端/审计(驱动化)/语言包/收藏
│   │   ├── Support/              # Snowflake + CaptchaService + LangSync 默认翻译器
│   │   └── Providers/            # AppServiceProvider（慢查询>500ms 监听）
│   ├── database/
│   │   ├── migrations/           # 16 个迁移（全字段 English/中文 comment + 索引）
│   │   ├── seeders/              # Permission(46 条含动作级)/Role/Admin/Menu(16 项 path 对齐前端)/Demo/User/RateLimitRule(5 条)
│   │   └── factories/            # 6 个工厂
│   ├── lang/{zh-CN,en}/          # api.php(平铺,267 叶子 key 含 ui.*) + notice.php
│   ├── config/                   # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
│   ├── routes/                   # api.php(94 条含 captcha + C端菜单) + channels.php + console.php(4 计划任务)
│   ├── openapi/openapi.yaml      # 78 operations（HTTP 200 + code 体系，含验证码）
│   ├── tests/Feature/Api/        # 15 个 Pest 文件 61 用例
│   └── docs/                     # 13 主文档 + extensions/ 8 扩展片段
├── frontend-vue3/                # 管理后台（ElementPlus+Pinia+Vue3，vue-i18n + 语言包接口 + md5 缓存）
├── frontend-uniapp/              # C 端（H5/小程序/App，uni i18n + 语言包接口 + md5 缓存）
└── docs/                         # 工程级文档（见 backend docs 清单）
```

## 3. 设计变更落实清单（用户追加需求全部落地）

| # | 需求 | 落实 |
|---|---|---|
| 1 | 中英双语注释（英文在前/中文在后） | 迁移字段 `->comment('English/中文')`；所有方法双语 docblock/行内注释；抽查达标 |
| 2 | Base 五基类 + make:module 生成器 | app/Base 五基类；`php artisan make:module {Name}` 一键生成 Controller/Service/Repository/Model/FormRequest/迁移(带注释+索引)/路由追加/lang 双语词条，各层自动 extends 正确基类；docs/codegen.md |
| 3 | 1000 万数据/10 万 QPS | 见 §5 性能落实表 |
| 4 | 审计日志不落 MySQL | 驱动化存储：file 默认（storage/logs/audit/audit-YYYY-MM-DD.log JSON 行 10 字段，真实写入验证）+ elasticsearch 驱动（完整代码，默认不启用，AUDIT_LOG_DRIVER 切换）；不建 admin_operation_logs 表；每日清理任务 clean-expired-audit-logs |
| 5 | 限流后台可配置 | rate_limit_rules 表 + 后台 CRUD（admin:rate:*）+ CompositeRateLimit 无参挂载运行时 resolve（路径精确/前缀→守卫全局→兜底 60/60，缓存 rate_limit:rules TTL300 写后清）；路由零写死参数 |
| 6 | 动作级权限 | permissions 表 action/type 字段；`{resource}:{action}` code 约定；权限后台 CRUD（system 预置不可删）；一个接口一个精确 code；超管通配符旁路 |
| 7 | lang:sync 多语言同步 | `php artisan lang:sync --source=zh-CN --targets=en [--translate] [--dry-run] [--overwrite]`；LangSyncTranslator 契约 + 默认 CopyWithMarkerTranslator（无翻译 API 也可跑）；docs/i18n-sync.md |
| 8 | showFullHelp | BaseCommand 抽象基类（重写 run() 拦截 --show-full-help，getNativeDefinition 动态生成双语完整帮助）；make:module/lang:sync 均已接入并实跑 |
| 9 | 统一状态码（国内约定） | HTTP 一律 200 + body.code：0 成功/1 通用/1001 参数/1002 未认证/1003 无权限/1004 不存在/1005 校验/1006 限流/1007 冲突/5000 服务器/2xxxx 业务码；StatusCode 枚举 + handler 全异常映射 + message() 成功 code=0 + openapi/docs/Pest 同步 |
| 10 | 语言包接口 + 后台多语言 + 刷新缓存 | GET /api/locale/{lang?}（data:{lang,md5,items}，Redis 缓存 locale:pack:{lang} TTL86400）+ GET /api/locale/{lang}/meta（轻量 md5）+ admin 4 接口（languages/show/update/refresh，权限 admin:i18n:list/update/refresh）；编辑只覆盖已存在 key，写回 lang 文件 + 清缓存；md5 = ksort + json_encode 稳定算法 |
| 11 | 前端多语言 + 硬编码中文清理 | vue3：vue-i18n 9.14.4 启动拉取/切换/刷新/md5 本地缓存/visibilitychange 回前台校验/axios 按 body.code；uniapp：自实现响应式 i18n 同款闭环；frontend-vue3/src/views 16 文件全覆盖 i18n（ui.* key 后端 lang 已补齐 ~120）；vue3 build 通过 |
| 12 | 登录验证码 | GET /api/captcha（公开）→ {code:0,data:{captcha_id,image:data:image/png;base64}}；CaptchaService GD 生成（4 位去 0/O/1/I、噪点+3~5 干扰线+imagettftext 随机旋转、缺字体退 imagestring）；缓存 captcha:{id} TTL300 一次性（validate 先 forget 再比对，错误也消费）；登录/管理登录必填 captcha_id/captcha_code，顺序 限流→FormRequest(1005)→验证码(1008)→认证；StatusCode::CAPTCHA=1008 + ErrorCode::CAPTCHA_INVALID=460 映射 + api.captcha.* 双语词条 |
| 13 | 登录页语言选择器 | vue3 登录页 + 布局、uniapp 登录页 + mine 页均有 zh-CN/en 下拉；点选调 GET /api/locale/{lang} 拉包更新 vue-i18n/uniapp 即时生效；本地持久化（localStorage/uni.setStorageSync）；登录页初始按已存语言渲染 |
| 14 | PC/手机响应式 | vue3 管理后台：≤768px 侧边栏收缩/顶栏精简/表格横向滚动兜底/登录页卡片居中（验证码小屏可点、语言选择器可用），Element-Plus 断点 + media query；uniapp：rpx 响应式 + 安全区 + H5 PC/手机不溢出；README 补响应式章节与适配清单 |
| 15 | BaseModel 软删除重构 + 模型继承恢复 | BaseModel 移除无条件 `use SoftDeletes`（trait 全局 scope 对无 deleted_at 表 SQL 报错的设计缺陷），软删除改由子类显式 use；12 业务模型恢复 extends BaseModel（HasSnowId/hidden id 继承），User/Admin 因 Authenticatable 单继承保持例外，RateLimitRule 内部表（无 snow_id 列）直接 extends Model |
| 16 | 菜单标题双语 | admin_menus 加 title_en 列（追加迁移 + MenuSeeder 17 项补英文）；菜单树接口 title 返回对象 `{'zh-CN':.., en:..}`（en 缺省回退 zh-CN）；前端 MenuItem/面包屑按 locale 取值（对象或字符串 key 双兼容） |
| 17 | 前端 fallback 补齐 + locale 请求收敛 | src/i18n/index.js fallbackMessages 补 common.*/ui.rate.*/ui.recycle.* 等缺 key（zh/en）；applyItems 改 **deepMerge 递归深合并**（远程包不再整体替换 fallback ui 子树，未覆盖键永不丢失）；lang/{zh-CN,en}/ui.php 重写为与前端 fallback 键集完全一致（**156 ui.* key**，30 顶层 + 15 模块组）→ 语言包接口下发全量 ui.* 段（总 **444 key** 含 md5）；locale 拉取改应用级单例（启动 1 次 + 回前台 1 次 + 手动刷新强拉，正常会话 3-5 次请求，去掉每页重复 sync） |
| 18 | 前端 i18n 补充修复（浏览器实测 4 项） | ① applyItems 深合并约定（后端语言包**只覆盖同名叶子**，不得覆盖兜底其余 key）；② 面包屑改按 path 查菜单树取 `title[locale]`（回退 route.meta.title）；③ Element Plus 组件文案响应式切换（App.vue `el-config-provider :locale` 绑定，分页"共 N 条→Total N"）；④ zh 语言包纯中文清理（ui.recycle.products 等"商品 products"→"商品"）；docs/测试与改进.md 第六节多语言专项复测结论 |
| 19 | dicts/permissions 双语 label | 追加迁移 ×2 加 `label_en` 列（dicts/permissions，双语 comment）；DemoSeeder 6 条（Enabled/Disabled/Pending/Male/Female/Unknown）+ PermissionSeeder 46 条 label_en 英文翻译；DictService（管理端 + C 端 /api/dicts/{type}）label 输出双语对象 `{zh-CN, en}`（en 回退）；PermissionService 保留 name 字符串 + 新增 `label` 双语对象；前端 dict/permission 页按 locale 取；2 Seeder 改幂等 upsert（type,value / code 去重，snow_id 仅 insert 时生成） |
| 20 | C端菜单独立表 c_menus + 公开双语接口 | c_menus 独立迁移（与 admin_menus 物理隔离，不共用表）；字段：snow_id/parent_id/title/title_en/icon/path/url/sort/is_show（**仅显隐开关 + 父子层级 + 多语言**，无 is_external 列——追加迁移已 drop）；`GET /api/menus` 公开接口（无需登录）返回启用+显示的 C 端入口，title 双语对象 `{zh-CN,en}`；管理端 CRUD（`auth:admin-api` 登录守卫即可，**无动作级 RBAC**）；admin_menus 第 18 项"C端菜单管理"入口（/c-menu）；CMenuSeeder 5 条默认入口：商品列表(/products)、字典示例(/dicts Dictionary)、我的收藏(/favorites)、消息中心(/messages)、个人中心(/mine Mine)；缓存 key `c_menu:list` TTL 600，写后失效；统一 code=0 成功约定 |

## 4. 真实验证结果（本机 PHP 8.3.8 + MySQL 5.7.38 + Redis 5.0.10）

| 项 | 结果 |
|---|---|
| PHP 语法 php -l | 全部通过（各轮交付逐文件验证，最终 0 失败） |
| Composer 依赖 | jwt-auth v2.9.3、laravel/reverb v1.12.0 真装成功；pestphp/pest v4.7.8（PHP 8.3 兼容，pest v5 需 8.4 不可用）；fruitcake/laravel-cors 与 Laravel 13 不兼容拒装 → 用框架内置 HandleCors |
| `migrate:fresh --seed`（MySQL ju_test 真库） | 16 迁移全部建表成功；Permission(46 条)/Role/Admin/Menu(16 项)/Demo/User/RateLimitRule(5 条) 全 DONE；**products/dicts/admins/users/roles/permissions/admin_menus 7 表 snow_id 空值计数全部 0** |
| Pest 全量 | **65 passed / 307 assertions**（17 个测试类：Auth(8)/AdminPermissionCache(2)/Captcha(4)/CMenu(3)/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale(12 含 ui.* 段断言)/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle） |
| 路由 | route:list 94 条（含 GET /api/captcha + C端菜单 6 条 + 管理端 c-menu CRUD 4 条） |
| 计划任务 | schedule:list 4 项：clean-expired-ip-blacklist(每分钟)/archive-read-messages(每小时)/sweep-idempotency-cache(每10分钟)/clean-expired-audit-logs(每日) |
| 审计 file 驱动 | storage/logs/audit/audit-2026-10-27.log 真实写入（JSON 行含 log_id/admin_id/url/method/ip/cost_ms/is_high_risk 等 10 字段） |
| lang:sync | 真实运行验证（dry-run 不写盘/同步幂等/嵌套结构对齐），临时验证文件已清理 |
| make:module | 真实生成 Notices 演示模块（各层 extends 正确基类、迁移带注释索引、路由自动追加）；--show-full-help 输出完整双语帮助 |
| 语言包 | GET /api/locale/{lang} zh-CN/en 各展开 444 叶子 key（含 ui.* 156 段，与前端 fallback 键集完全一致）；`__('api.success')` 真实解析为"操作成功/Operation success"（lang 平铺化修复后） |
| 前端 | frontend-vue3 npm install + build 通过（vue-i18n 9.14.4，Node 24 兼容；登录页验证码+语言选择器+响应式）；frontend-uniapp 代码按规范就绪 |
| 验证码 | 真机 HTTP 验证：GET /api/captcha → code 0 + data:image/png;base64；错码登录 → 1008；未认证浏览器 Accept → 1002；/api/notices → success:true + code:0；登录带正确验证码 → 认证通过（CaptchaTest 4 用例覆盖一次性/缺参/错码/成功） |
| 限流后台 | /api/admin/rate-limit-rules 真机带 token → code 0 + 5 条（api:global 60/60、admin:global 120/60、api/login 5/60、api/admin/login 5/60、api/products 120/60）；resolve() 运行时 6 场景（路径/守卫/兜底）全部命中正确 |
| 管理端 RBAC | AdminPermission 缓存改纯数组后真机/Pest 冷热两路径 /api/admin/products → code 0 + 5 条种子商品（修复 Collection 反序列化 5000） |
| .env/.env.example | APP_KEY/JWT_SECRET 已生成；JWT_TTL=60；AUDIT_LOG_DRIVER/AUDIT_ES_HOST/AUDIT_ES_INDEX_PREFIX/AUDIT_RETENTION_DAYS/SNOW_WORKER_ID 齐全 |
| C端菜单 c_menus | migrate 真建表成功；CMenuSeeder 5 条默认入口；`GET /api/menus`（公开）→ code=0 + 5 条（title 双语对象）；联动实测：POST 创建"限时活动"→ 6 条；PUT is_show=0 → 5 条；DELETE → 5 条；缓存 `c_menu:list` 写后即时失效；管理端 `/api/admin/c-menus` CRUD 全 code=0（权限 admin:c-menu:*） |

## 5. 性能与规模设计落实（1000 万级数据 / 10 万级 QPS）

**已实现的工程内决策：**
- 索引：所有 where/join/orderBy/groupBy 列建索引（联合/覆盖索引，comment 标注用途）；messages 按 receiver_type+created_at 联合索引支撑按用户查历史
- 禁 Model::all()、禁 N+1（with() 预加载）、深分页 keyset（where id < cursor）、page_size 上限（默认 ≤100）、批量写 chunkById/insert
- Redis cache-aside：dicts/菜单/商品热门/语言包/限流规则/权限集合等热读数据全走 Redis + TTL；复合限流/JWT 黑名单/多端集合/幂等/IP 黑名单/Reverb 广播 key 带 TTL，多实例共享同一 Redis（水平扩展前提）
- 写路径异步化：消息推送/通知走队列（QUEUE_CONNECTION=redis）；交易性写走事务
- 消息归档计划任务（30 天已读 → messages_archive）；审计日志不占业务 MySQL
- docs/deploy.md 写明 10 万 QPS 部署架构：Nginx + PHP-FPM 多进程池调优、OpCache、Redis 热数据、DB 读写分离与从库、CDN/静态分离、多实例水平扩展共享同一 Redis、连接池/长连接、监控告警（健康检查 GET /api/health）

**依赖部署架构兑现（非骨架单点）**：10 万 QPS 需按 docs/deploy.md 架构部署并压测；骨架提供可水平扩展的架构与最佳实践，**不承诺单点 10 万 QPS**。

## 6. 未验证 / 受限项（如实披露）

1. **Elasticsearch 审计驱动**：代码完整提供、file 驱动回退逻辑已实现，但本机无 ES，未实机写入验证（部署后配置 AUDIT_LOG_DRIVER=elasticsearch 验证）。
2. **Reverb 广播**：BROADCAST_CONNECTION=reverb 配置就绪、MessageSent 事件/频道 private-user.{id} 等代码完整；本机未跑 Reverb 服务，广播链路未实机端到端验证（测试中以队列 sync/日志替代）。channels.php 授权逻辑已就位。
3. **frontend-uniapp 构建**：需 HBuilderX/uni-cli 工具链，本机无；代码按 uni-app 规范写好（含 md5 缓存/i18n/验证码/响应式），未执行 npm build。
4. **前端与后端语言包/验证码接口实机联调**：前端按契约写好（接口就绪前降级兜底，现已就绪），未做 php artisan serve + 浏览器端到端联调（需部署后由浏览器实测确认）。
5. **Swagger-UI/Apifox 渲染**：openapi.yaml 仅 pyyaml 语法+结构校验，未实际渲染。
6. **压测**：10 万 QPS 未压测（需按部署架构部署后执行）。
7. **限流规则前端页面**：已实现（S9 创建 src/views/rate-limit/index.vue + 路由 /rate-limit，调 /api/admin/rate-limit-rules CRUD）；后端 5 条默认规则开箱可用；菜单已加回（17 项）。未做浏览器端到端点击验证（待部署后实测）。

## 7. 部署步骤

```bash
# 1. 依赖（本机已验证）
cd backend-laravel13
composer install --no-dev --optimize-autoloader   # 生产去 dev
php artisan key:generate
php artisan jwt:secret --force

# 2. 环境配置
cp .env.example .env   # 配置 MySQL/Redis/AUDIT_LOG_DRIVER/Reverb

# 3. 数据库
php artisan migrate --seed      # 或 migrate:fresh --seed 重建开发库

# 4. 前端构建
cd ../frontend-vue3 && npm install && npm run build   # 产物 dist 部署 Nginx
cd ../frontend-uniapp          # HBuilderX 发行 H5/小程序/App

# 5. 队列 / 调度 / 广播
php artisan queue:work          # Redis 队列（supervisor 守护）
php artisan schedule:work       # 或 crontab: * * * * * php artisan schedule:run
php artisan reverb:start        # Reverb WebSocket（生产用 supervisor）

# 6. 验证
php artisan route:list
php artisan schedule:list
curl http://localhost/api/health        # 健康检查
curl http://localhost/api/captcha      # 验证码（登录前置）
vendor/bin/pest                         # 测试套件（61 用例）
```

## 8. 常用命令速查

| 命令 | 作用 |
|---|---|
| `php artisan make:module Notices [--soft-deletes] [--no-with-admin]` | 一键生成完整模块（各层继承 Base 基类） |
| `php artisan make:module Notices --show-full-help` | 完整双语帮助 |
| `php artisan lang:sync --source=zh-CN --targets=en [--translate] [--dry-run] [--overwrite]` | 多语言 key 同步/翻译 |
| `php artisan schedule:list` | 查看计划任务 |
| `php artisan migrate:fresh --seed` | 重建库 + 种子 |
| `GET /api/locale/{lang}` / `GET /api/locale/{lang}/meta` | 语言包 / md5 校验 |
| `POST /api/admin/locale/refresh` | 手动刷新语言缓存（权限 admin:i18n:refresh） |

## 9. 已知约定速记

- 守卫：api（C 端 User）/ admin-api（后台 Admin）；Token 不可跨守卫（双表 provider 隔离 + guard claim）
- 中间件别名 8 个：lang / ip.blacklist / api.composite.rate(无参) / admin.permission:{code} / check.user.online / idempotency / response.desensitize / admin.operation.log:{0|1}
- 对外一律 snow_id（自增 id 模型 $hidden）；分页 page/page_size（上限 100）；软删除；文案多语言 key（api./ui./notice. 前缀）
- 登录：必须先 GET /api/captcha 取 captcha_id + 图，POST /api/login（或 /api/admin/login）携带 captcha_id/captcha_code；验证码一次性（Redis captcha:{id} TTL 300，错误也消费）
- **i18n 深合并约定**：前端 applyItems 用 deepMerge——后端语言包只覆盖同名叶子 key，不得整体替换前端 fallback 的 ui 子树；前端 fallback 是兜底全集（离线可用），后端 /api/locale 下发同名键优先
- 菜单：seeder 菜单 path 必须与前端路由 path 完全一致（如 /product 单数）；菜单树缓存 admin_menu:tree:{adminId} TTL 7 天，改菜单后需清缓存；菜单 title 双语对象 {zh-CN, en}（title_en 缺省回退 zh-CN）
- **C端菜单**：独立 c_menus 表（不与 admin_menus 共用）；字段 snow_id/parent_id/title/title_en/icon/path/url/sort/is_show（仅显隐+层级+多语言，无 is_external、无动作级 RBAC）；`GET /api/menus` 公开双语接口；管理端 `/api/admin/c-menus` CRUD（仅需 `auth:admin-api` 登录守卫，无 admin:c-menu:* 权限码）；缓存 `c_menu:list` TTL 600；5 条默认入口：商品列表/字典示例/我的收藏/消息中心/个人中心；lang `cmenu.*` 组（title/create/title_en/path/is_show/delete_confirm/create_child）
- 权限缓存：admin:permissions:{adminId} 缓存**纯数组**（勿存 Collection，防 __PHP_Incomplete_Class 反序列化崩溃），TTL 600
- 演示账号：C 端 user@example.com / password（UserSeeder）；管理端 admin/admin123（super-admin 全权限+全菜单）、demo/demo123（operator）——见 docs/demo-accounts.md
- 审计：默认 file 驱动（storage/logs/audit/），AUDIT_LOG_DRIVER=elasticsearch 切换，90 天保留期自动清理
