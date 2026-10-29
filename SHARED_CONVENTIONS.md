# Laravel13-API-Skeleton 共享开发契约（SHARED CONVENTIONS）

> 本文件是全工程
>
> **唯一协调契约**
>
> ，所有 SubAgent 必须先完整 Read 本文件与 
>
> `PROJECT_SPEC.md`
>
> （二者同目录），再动手。
> 凡是本文件与 PROJECT_SPEC.md 冲突之处，以本文件为准（本文件是对规格的可执行细化）。
> 工程根：
>
> `D:\wwwroot\ju_test02\laravel13-api-skeleton\`
> 后端根：
>
> `D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13\`
>
> （Laravel 13.38 全新骨架，vendor 已含框架）



***

## 1. 运行环境与命令（所有 SubAgent 必须遵守）



| 项        | 值                                                                                                                                                                                                          |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP      | `D:\BtSoft\php\83\php.exe`（8.3.8，扩展 pdo\_mysql/redis/openssl/mbstring 齐全）。**一律用绝对路径**，裸 `php` 会被沙箱 PATH 拦截                                                                                                 |
| Composer | `D:\BtSoft\php\83\php.exe D:\BtSoft\panel\script\composer.phar`（不用裸 composer）                                                                                                                              |
| MySQL    | 127.0.0.1:3306，库 `ju_test`（**当前为空库**），user=ju\_test /pass=mH4ZY4WJAJAwMYh8，版本 5.7.38（迁移写法须兼容 5.7：utf8mb4、JSON 类型可用、避免 8.0 专属语法）                                                                            |
| Redis    | 127.0.0.1:6379，无密码，版本 5.0.10（phpredis 扩展可用；避免 5.0 不支持的更高级命令）                                                                                                                                               |
| Node/npm | Node v24.12.0、npm 11.6.2 可用（项目目录实测）；Element-Plus/Pinia/Vite 需选与 Node 24 兼容的版本（Vite 5/6、Element-Plus 2.x、Pinia 2.x 均可）。frontend-vue3 可真实 `npm install` + `npm run build` 验证（前端为配套模板，优先级低于后端，若耗时过长可只交付代码并说明） |

### 命令模板



```
# 语法检查（每个 PHP 文件必跑，退出码 0 且无输出即通过）
& "D:\BtSoft\php\83\php.exe" -l "D:\wwwroot\ju_test02\laravel13-api-skeleton\backend-laravel13\app\...\File.php"
# 批量语法检查（在 backend-laravel13 下）
Get-ChildItem -Recurse -Filter *.php -Path app,config,routes,database,lang,tests | ForEach-Object { & "D:\BtSoft\php\83\php.exe" -l $_.FullName | Select-String -NotMatch "No syntax errors" }
# artisan（仅允许下列安全命令；migrate/seed/route:list/pest 由 Organizer 集成阶段统一执行）
& "D:\BtSoft\php\83\php.exe" artisan config:clear
```

### 权限与职责边界（硬约束）



1. **Composer 操作只有「装配层 app-wiring」SubAgent 允许执行**（一次性真实安装 `php-open-source-saver/jwt-auth`、`fruitcake/laravel-cors`、`laravel/reverb`，更新 vendor 与 composer.lock）。**其余所有 SubAgent 禁止运行任何 composer 命令**，否则并发写 vendor/composer.lock 互相冲突。

2. `php artisan migrate`**&#x20;/&#x20;**`db:seed`**&#x20;/&#x20;**`route:list`**&#x20;/&#x20;**`vendor/bin/pest`**&#x20;禁止任何 SubAgent 执行**，由 Organizer 在全部代码完成后串行执行。

3. **lang 语言包（**`lang/zh-CN/api.php`**、**`lang/en/api.php`**）只允许「基础层 infra-core」SubAgent 写**。其他模块如发现缺 key，不得改文件，在自己最终回报中列出 "需要补充的 lang key + 文案（zh/en）"，由 Organizer 汇总后统一补。

4. 各 SubAgent **只写自己清单内的文件**，禁止修改其他 SubAgent 负责的文件（可 Read 他人文件）。

5. 文件一律用 Write/Edit 工具按**绝对路径**写入，UTF-8 无 BOM。

### 验证义务



* 每个产出的 PHP 文件必须过 `php -l`；在回报中给出 "通过数 / 失败数 / 失败文件与原因"。

* 无法真实验证的点（如 Reverb 服务未运行）如实说明，不得谎报。



***

## 2. 目录结构与命名空间（全量 FQCN 清单）

后端命名空间统一：C 端控制器 `App\Http\Controllers\Api\User\*`；管理员控制器 `App\Http\Controllers\Api\Admin\*`；公共控制器 `App\Http\Controllers\Api\*`。

C 端服务 `App\Services\User\*`；管理员服务 `App\Services\Admin\*`。

C 端请求 `App\Http\Requests\User\*`；管理员请求 `App\Http\Requests\Admin\*`。

模型全部平铺 `App\Models\*`（唯一例外：收藏管理员侧模型 `App\Models\AdminFavorite`，与 `App\Models\UserFavorite` 指向同一物理表 `user_favorites`）。

### 完整文件树（√= 必须存在；\[Sx] = 归属 shard，见第 9 节）



```
backend-laravel13/
├─ app/
│  ├─ Helpers/
│  │  ├─ ApiHelper.php            [S1] message()
│  │  └─ common.php               [S1] snow_id/client_ip/client_fingerprint/mask_text
│  ├─ Enums/                      [S1]
│  │  ├─ UserStatus.php AdminStatus.php ProductStatus.php DictStatus.php
│  │  ├─ MessageReceiverType.php MessageType.php
│  │  └─ OperationRisk.php ErrorCode.php
│  ├─ Support/Snowflake.php       [S1]
│  ├─ Exceptions/
│  │  ├─ ApiException.php         [S1]
│  │  └─ ApiExceptionHandler.php  [S1] static render()
│  ├─ Models/                     [S4]
│  │  ├─ Traits/HasSnowId.php
│  │  ├─ User.php Admin.php Role.php Permission.php AdminMenu.php
│  │  ├─ Product.php Dict.php Message.php
│  │  ├─ UserFavorite.php AdminFavorite.php
│  │  ├─ IpBlacklist.php AdminOperationLog.php
│  ├─ Http/
│  │  ├─ Controllers/Controller.php（骨架已有，不动）
│  │  ├─ Controllers/Api/
│  │  │  ├─ HealthController.php  [S14]
│  │  │  ├─ User/  [S6+S12+S13+S15]
│  │  │  │  ├─ AuthController.php [S6] UserController.php [S6] MessageController.php [S12]
│  │  │  │  ├─ ProductController.php [S13] DictController.php [S13] FavoriteController.php [S15]
│  │  │  └─ Admin/ [S6+S11+S12+S13+S14+S15]
│  │  │     ├─ AuthController.php [S6] AdminController.php [S11] UserController.php [S11]
│  │  │     ├─ RoleController.php [S11] PermissionController.php [S11] MenuController.php [S11]
│  │  │     ├─ ProductController.php [S13] DictController.php [S13] MessageController.php [S12]
│  │  │     ├─ FavoriteController.php [S15]
│  │  │     ├─ OperationLogController.php [S14] IpBlacklistController.php [S14] RecycleBinController.php [S14]
│  │  ├─ Middleware/              [S5]
│  │  │  ├─ LangSwitch.php CompositeRateLimit.php AdminPermission.php CheckUserOnline.php
│  │  │  ├─ IdempotencyRequest.php ResponseDesensitize.php IpBlacklist.php AdminOperationLog.php
│  │  └─ Requests/
│  │     ├─ User/  [S6+S13+S15] LoginRequest.php UpdateProfileRequest.php ChangePasswordRequest.php
│  │     │        ProductListRequest.php DictShowRequest.php FavoriteStoreRequest.php FavoriteListRequest.php
│  │     └─ Admin/ [S6+S11+S13+S14+S15] AdminLoginRequest.php AdminStoreRequest.php AdminUpdateRequest.php
│  │              ResetPasswordRequest.php AdminStatusRequest.php RoleStoreRequest.php RoleUpdateRequest.php
│  │              RoleAssignPermissionsRequest.php RoleAssignMenusRequest.php MenuStoreRequest.php MenuUpdateRequest.php
│  │              AdminUserStatusRequest.php ProductStoreRequest.php ProductUpdateRequest.php DictStoreRequest.php
│  │              DictUpdateRequest.php MessageSendRequest.php RecycleRestoreBatchRequest.php IpBlacklistStoreRequest.php
│  ├─ Services/
│  │  ├─ User/  [S6+S12+S13+S15]
│  │  │  ├─ AuthService.php MultiDeviceService.php UserService.php
│  │  │  ├─ ProductService.php DictService.php MessageService.php FavoriteService.php
│  │  └─ Admin/ [S6+S11+S12+S13+S14+S15]
│  │     ├─ AuthService.php AdminService.php UserService.php RoleService.php PermissionService.php MenuService.php
│  │     ├─ ProductService.php DictService.php MessageService.php FavoriteService.php
│  │     ├─ OperationLogService.php RecycleBinService.php IpBlacklistService.php
│  └─ Events/MessageSent.php      [S12] ShouldBroadcast
├─ database/
│  ├─ migrations/                 [S3] 见第 3 节清单
│  ├─ seeders/                    [S3] DatabaseSeeder AdminSeeder PermissionSeeder RoleSeeder MenuSeeder DemoSeeder
│  └─ factories/                  [S3] UserFactory AdminFactory ProductFactory DictFactory MessageFactory UserFavoriteFactory
├─ lang/zh-CN/api.php lang/en/api.php  [S1]
├─ openapi/openapi.yaml           [S8]
├─ routes/
│  ├─ api.php channels.php        [S16]
│  └─ console.php                 [S2]（含 schedule）
├─ config/                        [S2] auth.php jwt.php cors.php broadcasting.php reverb.php（其余骨架已有不动）
├─ bootstrap/app.php              [S2] 中间件/异常/路由注册
├─ bootstrap/providers.php        [S2]
├─ composer.json .env.example     [S2]
├─ app/Providers/AppServiceProvider.php [S2]
└─ tests/
   ├─ Pest.php                    [S17]
   └─ Feature/Api/*.php           [S17]
frontend-vue3/                    [S9]
frontend-uniapp/                  [S10]
docs/
├─ deploy.md dev-guide.md business-rule.md test-case.md extend-example-full-isolation.md  [S7]
└─ extensions/*.md                [S7]
```



***

## 3. 数据库：迁移清单与表结构（MySQL 5.7 兼容）

所有业务表统一追加列：`snow_id`（`$table->unsignedBigInteger('snow_id')->nullable()->unique();`）。**对外接口只暴露 snow\_id；自增&#x20;**`id`**&#x20;仅内部关联与 Redis key 使用，序列化时隐藏**（见第 5 节）。

所有表 `$table->timestamps()`；需软删除的表加 `$table->softDeletes()`。中间表不建外键，只建索引 / 联合主键。

迁移文件（在现有 0001\_01\_01\_000000/000001/000002 基础上，data shard 新建以下，全部真实手写）：



| 文件                                                             | 表                                        | 要点                                                                                                                                                                                                                         |
| -------------------------------------------------------------- | ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0001\_01\_01\_000010\_create\_admins\_table                    | admins                                   | 见规格 3.2 + snow\_id + softDeletes；username unique                                                                                                                                                                           |
| 0001\_01\_01\_000020\_create\_roles\_table                     | roles                                    | 3.3 + snow\_id + softDeletes；code unique                                                                                                                                                                                   |
| 0001\_01\_01\_000030\_create\_permissions\_table               | permissions                              | 3.4 + snow\_id；name/code/group；code unique；**加 action varchar (50) 与 type tinyint（动作级权限，见 6.6）：action 存 list/create/update/delete 或自定义（export/audit/kick/cancel 等），type 1=system 预置 2=custom 自定义；字段 English / 中文 comment** |
| 0001\_01\_01\_000040\_create\_admin\_role\_table               | admin\_role                              | admin\_id+role\_id 联合主键；snow\_id 不加（中间表）                                                                                                                                                                                   |
| 0001\_01\_01\_000050\_create\_role\_permission\_table          | role\_permission                         | 同上                                                                                                                                                                                                                         |
| 0001\_01\_01\_000060\_create\_role\_menu\_table                | role\_menu                               | 同上                                                                                                                                                                                                                         |
| 0001\_01\_01\_000070\_create\_admin\_menus\_table              | admin\_menus                             | 3.8 + snow\_id + softDeletes；parent\_id 默认 0；sort 默认 0；is\_show/is\_cache/is\_external 默认 1                                                                                                                                |
| 0001\_01\_01\_000080\_create\_products\_table                  | products                                 | 3.9 + snow\_id + softDeletes；price decimal (10,2)；status tinyint 默认 1                                                                                                                                                      |
| 0001\_01\_01\_000090\_create\_dicts\_table                     | dicts                                    | 3.10 + snow\_id；type+label+value；索引 (type,status)                                                                                                                                                                          |
| 0001\_01\_01\_000100\_create\_messages\_table                  | messages                                 | 3.11 + snow\_id + softDeletes；receiver\_type varchar、receiver\_id unsignedBigInteger nullable、extra json nullable、is\_read/is\_pop 默认 0；索引 (receiver\_type,receiver\_id,is\_read)                                          |
| 0001\_01\_01\_000110\_create\_admin\_operation\_logs\_table    | ~~admin\_operation\_logs~~ **不建表（设计变更）** | 审计日志不落 MySQL，改驱动化存储（默认文本 JSON 行、可选 Elasticsearch，见 13.4）；该迁移文件由 S3 删除                                                                                                                                                      |
| 0001\_01\_01\_000120\_create\_user\_favorites\_table           | user\_favorites                          | 3.13 + snow\_id + softDeletes；联合唯一索引 (user\_id,product\_id)                                                                                                                                                                |
| 0001\_01\_01\_000130\_create\_ip\_blacklist\_table             | ip\_blacklist                            | 3.14 + snow\_id；ip 唯一索引；expire\_at nullable                                                                                                                                                                                |
| 0001\_01\_01\_000140\_create\_rate\_limit\_rules\_table \[S19] | rate\_limit\_rules                       | 限流规则表（后台可配置，见 13.5）：id、rule\_key varchar unique（路由 / 分组标识，如 `login`/`api:global`/`product:list`）、max\_attempts int、decay\_seconds int、enabled tinyint 默认 1、remark、timestamps；字段全 English / 中文 comment                      |

**0001\_01\_01\_000000\_create\_users\_table.php 重写**：users 字段 = id /name/email (unique) /password/status tinyint 默认 1 /rememberToken/timestamps /softDeletes/snow\_id。**去掉 email\_verified\_at**；password\_reset\_tokens、sessions 两表保留骨架原样。

Seeder 要点：



* `AdminSeeder`：超级管理员 username=`admin`、password=`admin123`（bcrypt）、nickname = 超级管理员、status=1，并绑定 `super-admin` 角色；另建演示管理员 `demo`/`demo123`。

* `PermissionSeeder`：全量权限码（见第 6 节权限码清单），group 按模块。

* `RoleSeeder`：角色 `super-admin`（全部权限 + 全部菜单）、`operator`（商品 / 字典权限 + 对应菜单）。

* `MenuSeeder`：菜单树（见第 6 节菜单树）+ role\_menu 绑定。

* `DemoSeeder`：5 条商品、6 条字典演示数据。

* `DatabaseSeeder`：依次调 AdminSeeder→PermissionSeeder→RoleSeeder→MenuSeeder→DemoSeeder（顺序保证角色 / 权限 / 菜单先就位）。

Factories：UserFactory（name/email/password status=1）、AdminFactory、ProductFactory、DictFactory、MessageFactory、UserFavoriteFactory —— 供 Pest 用，真实可用。

**索引补充规范（用户性能硬约束，1000 万级数据量）**：凡被 where/join/orderBy/groupBy 使用的列必须建索引，字段 comment 中标注索引用途；索引名按 Laravel 规范（`table_column_index`）。在已有迁移基础上必须补建 / 确认：



* `messages`：`(receiver_type, receiver_id, is_read)`（已有）+ **补&#x20;**`(receiver_type, created_at)`**&#x20;联合索引**（支撑按用户查历史消息的时间范围）+ `(receiver_id, created_at)`（定向用户历史）；

* `admin_operation_logs`：**不建表**（审计改驱动化存储，见 13.4），删除该迁移的 `(admin_id, created_at)` 与 `(created_at)` 索引项；

* `user_favorites`：`(user_id, product_id)` 联合唯一（已有）+ **补&#x20;**`(user_id, created_at)`**&#x20;联合索引**（用户收藏列表分页排序）；

* `products`：`(status, created_at)` 联合索引（前台列表）+ `(status, price)`（价格排序场景）；

* `admin_menus`：`parent_id` 索引（children 递归查询）；

* `dicts`：`(type, status)`（已有）；

* `users/admins/roles/permissions/ip_blacklist`：唯一键已覆盖登录 / 查询路径，确认即可。

  以上索引全部在迁移中真实建出（含 comment 标注用途），大表归档 / 分区方案写入 docs（deploy.md 数据层章节）。



***

## 4. 中间件：别名、挂载与行为（全部 \[S5] 实现，注册由 \[S2]）



| 别名                                 | 类                   | 签名 / 参数                    | 行为要点                                                                                                                                                                                                                                                                     |
| ---------------------------------- | ------------------- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `lang`                             | LangSwitch          | 无                          | 读 `Accept-Language` 头或 `?lang=`；白名单 `zh-CN`/`en`，非法回退 zh-CN；`App::setLocale()`                                                                                                                                                                                           |
| `ip.blacklist`                     | IpBlacklist         | 无                          | `Cache::remember('ip_blacklist:active', 300, fn 查表 active)`；当前 IP 命中且未过期 → ApiException 403 `api.ip.blocked`；过期行视为放行（清理交给 schedule）                                                                                                                                      |
| `api.composite.rate`               | CompositeRateLimit  | 可选参数 `{max},{decay}`（代码覆盖） | **运行时动态解析规则（不写死）**：无参数时经 `RateLimitRuleService::resolve($routeName/$path)` 从 DB→Redis 缓存（`rate_limit_rules` 表，key `rate_limit:rules` 全量缓存 TTL 300，未命中读 DB 回写；规则变更清缓存）匹配当前路由；匹配 enabled 规则用其 max/decay，未匹配用默认 60/60；有参数时参数覆盖。key=\`composite\_rate:{path}:{ip}:{uid\_{id} |
| `idempotency`                      | IdempotencyRequest  | 无                          | 仅 POST/PUT/DELETE 生效；头 `X-Idempotency-Key`（缺失则直接放行）；key=\`idempotency:{method}:{path}:{uid\_{id}                                                                                                                                                                         |
| `response.desensitize`             | ResponseDesensitize | 无                          | 头 `Skip-Desensitize:1` 直接放行；否则递归脱敏响应 data 中 `config('app.desensitize_fields', ['mobile','id_card'])` 命名字段：mobile→`138****1234`（保留前 3 后 4）、id\_card→`110101********1234`（保留前 6 后 4）                                                                                       |
| `check.user.online`                | CheckUserOnline     | 无                          | 仅 C 端 api 守卫路由；`MultiDeviceService::isOnline(auth('api')->id(), 当前token)` false → 401 `api.account.kicked`                                                                                                                                                               |
| `admin.permission:{code}`          | AdminPermission     | 权限码                        | 权限缓存 `admin:permissions:{adminId}` TTL 600（Cache::remember，查 admins→roles→permissions code 集合）；角色 code=`super-admin` 直接放行；无权 → 403 `api.forbidden`                                                                                                                       |
| `admin.operation.log:{isHighRisk}` | AdminOperationLog   | 0\|1                       | 记录 url/method/ip/request\_params/response\_result/cost\_ms/is\_high\_risk/admin\_id → `App\Services\Admin\OperationLogService::record()`；请求体 JSON 截断 2000 字符；response 截断 2000；密码字段脱敏为 `***`；**输出走驱动化存储（默认文本 JSON 行，不写 MySQL），见 13.4**                                    |

bootstrap/app.php 注册（\[S2]）：全局 `prepend`：`HandleCors`（读 config/cors.php）、`IpBlacklist`、`LangSwitch`；别名表注册上述全部别名；`trustProxies(at: '*')`；`withExceptions` 中 `shouldRenderJsonWhen(api/*)` + `renderable → ApiExceptionHandler::render`。



***

## 5. 模型、JWT 与统一响应约定

### 5.1 模型通用约定（\[S4]）



* 全部业务模型 use `HasSnowId` trait：`creating` 事件里 `$model->snow_id = snow_id()`。

* `$hidden` 统一含自增 `id`（`User` 另有 `password`/`remember_token`）；对外 JSON 展示 `snow_id`。

* `protected function casts(): array` 经典数组写法（不用 13 的属性风格）。

* 软删除模型 use `SoftDeletes`。禁用 `Model::all()`；列表一律分页 + `with()` 预加载。

* 每模型约定：


  * User：fillable `name,email,password,status`；casts `password='hashed',status=UserStatus::class`；JWTSubject；无关联。

  * Admin：fillable `username,nickname,email,password,avatar,status`；casts `status=AdminStatus::class,password='hashed'`；JWTSubject；`roles()` 多对多 Role（pivot admin\_role）。

  * Role：fillable `name,code,remark`；`permissions()`（role\_permission）、`menus()`（role\_menu）、`admins()`。

  * Permission：fillable `name,code,group`。

  * AdminMenu：fillable `parent_id,title,path,component,icon,permission_code,sort,is_show,is_cache,is_external,external_url`；`children()` 自关联。

  * Product：fillable `name,desc,price,status`；casts `status=ProductStatus::class, price='decimal:2'`。

  * Dict：fillable `type,label,value,sort,status`；casts `status=DictStatus::class`。

  * Message：fillable `receiver_type,receiver_id,msg_type,title,content,extra,is_read,is_pop`；casts `extra='array',receiver_type=MessageReceiverType::class,msg_type=MessageType::class,is_read='boolean',is_pop='boolean'`。

  * UserFavorite：fillable `user_id,product_id`；`product()` 关联。

  * AdminFavorite：`$table='user_favorites'`；fillable `user_id,product_id`；`product()`、`user()` 关联（管理员视角查询）。

  * IpBlacklist：fillable `ip,remark,expire_at`；casts `expire_at='datetime'`。

  * ~~AdminOperationLog~~：**审计不落库（设计变更）**，模型仅作数据结构参考不再查表；审计读写走 `App\Services\Admin\OperationLogService` 驱动化存储（13.4）。

### 5.2 JWT（php-open-source-saver/jwt-auth）



* 守卫：`api`（provider users / model App\Models\User）、`admin-api`（provider admins /model App\Models\Admin），driver 均为 `jwt`；web 守卫保留（session）。

* User/Admin 实现 `PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject`：`getJWTIdentifier(): int` 返回自增 id；`getJWTCustomClaims(): array` 返回 `['guard' => 'api'|'admin-api']`。

* 签发：登录成功 `JWTAuth::claims(['guard'=>'api'])->fromUser($user)`（管理员为 admin-api）；`expires_in = config('jwt.ttl')`（秒）。

* 刷新：`JWTAuth::parseToken()->refresh()`（旧 token 自动进黑名单）。

* 登出：`JWTAuth::parseToken()->invalidate()`。

* 守卫隔离：路由用 `auth:api` / `auth:admin-api` 分开挂载；跨守卫访问自然 401。

* 多端登录（仅 C 端 api）：`App\Services\User\MultiDeviceService`，Cache 存 JSON 数组：


  * key `user:token:list:{userId}`，TTL=`config('jwt.refresh_ttl')*60`。

  * `register(int $userId,string $token)`：入列表；若 >3 个，剔除最旧 token 并 `JWTAuth::setToken(旧token)->invalidate()`（防并发用 try/catch）。

  * `remove(int $userId,string $token)`：登出时移除 + invalidate。

  * `isOnline(int $userId,string $token): bool`、`count(int $userId): int`、`listTokens(int $userId): array`、`kickAll(int $userId)`：遍历 invalidate + 清空列表。

### 5.3 统一响应 message ()

`app/Helpers/ApiHelper.php` 定义全局函数 `message(string $msg='api.success', int $code=200, mixed $data=null): Illuminate\Http\JsonResponse`



* 返回 `{success: code===200, code, msg, data}`；msg 先 `__($msg)`（多语言 key），非 key 原样返回。

* 业务失败码：400 业务错误、401 未认证 / 被踢、403 无权、404 不存在、409 冲突（如重复收藏）、422 校验失败（data = 错误明细）、429 限流、500 服务器错误。

* 错误码枚举 `App\Enums\ErrorCode`（BackedEnum: int）与上述一一对应，异常处统一使用。

* `common.php`：`snow_id(): int`（Snowflake::id()）、`client_ip(): string`（request ()->ip ()，信任代理已配）、`client_fingerprint(?Request): string`（X-Client-Fingerprint 头，默认 'unknown'）、`mask_text(string $s,int $keepHead,int $keepTail): string`。

* composer.json autoload `"files": ["app/Helpers/ApiHelper.php","app/Helpers/common.php"]`（\[S2] 写入）。

### 5.4 分页约定



* 入参 `page`（默认 1）、`page_size`（默认 15，**最大 100**，超限钳制）。

* 返回 data 结构：`{list: [...], total, page, page_size}`。

* 列表查询统一 `Model::query()->where(...)->with([...])->paginate($pageSize, ['*'], 'page', $page)` 再映射 snow\_id 输出。



***

## 6. 接口清单（路由契约，\[S16] 按此写 routes/api.php）

### 6.1 C 端 `/api/*`（组中间件：`ip.blacklist,lang` 已全局；组默认 `api.composite.rate:60,60`）



| #  | 方法     | URI                               | 控制器 @方法                                | 额外中间件                                             | 说明                        |
| -- | ------ | --------------------------------- | -------------------------------------- | ------------------------------------------------- | ------------------------- |
| 1  | GET    | /api/health                       | Api\HealthController@index             | 无                                                 | DB+Redis 连通检查             |
| 2  | POST   | /api/login                        | Api\User\AuthController@login          | `api.composite.rate:5,60`                         | 登录，data=token/expires\_in |
| 3  | POST   | /api/refresh                      | Api\User\AuthController@refresh        | `api.composite.rate:30,60`                        | 换发 token                  |
| 4  | POST   | /api/logout                       | Api\User\AuthController@logout         | `auth:api,check.user.online`                      | 登出                        |
| 5  | GET    | /api/me                           | Api\User\UserController@profile        | `auth:api,check.user.online,response.desensitize` | 个人信息                      |
| 6  | PUT    | /api/me                           | Api\User\UserController@update         | 同上                                                | 改资料                       |
| 7  | PUT    | /api/me/password                  | Api\User\UserController@changePassword | 同上 + `idempotency`                                | 改密码                       |
| 8  | GET    | /api/products                     | Api\User\ProductController@index       | 无                                                 | 分页只读                      |
| 9  | GET    | /api/products/{id}                | Api\User\ProductController@show        | 无                                                 | {id}=snow\_id             |
| 10 | GET    | /api/dicts/{type}                 | Api\User\DictController@show           | 无                                                 | 字典 Redis 缓存               |
| 11 | GET    | /api/messages                     | Api\User\MessageController@index       | `auth:api,check.user.online`                      | 我的消息分页                    |
| 12 | GET    | /api/messages/unread-count        | Api\User\MessageController@unreadCount | 同上                                                | 未读数                       |
| 13 | PUT    | /api/messages/read/{id}           | Api\User\MessageController@markRead    | 同上                                                | 标记已读                      |
| 14 | PUT    | /api/messages/read-all            | Api\User\MessageController@markAllRead | 同上                                                | 全部已读                      |
| 15 | POST   | /api/favorites                    | Api\User\FavoriteController@store      | `auth:api,check.user.online,idempotency`          | 收藏，重复→409                 |
| 16 | GET    | /api/favorites                    | Api\User\FavoriteController@index      | `auth:api,check.user.online`                      | 我的收藏                      |
| 17 | GET    | /api/favorites/status/{productId} | Api\User\FavoriteController@status     | 同上                                                | 是否已收藏                     |
| 18 | DELETE | /api/favorites/{id}               | Api\User\FavoriteController@destroy    | `auth:api,check.user.online`                      | 取消收藏（软删）                  |

### 6.2 管理员 `/api/admin/*`（组中间件：`ip.blacklist,lang` 已全局；组默认 `api.composite.rate`——**无参，规则后台可配置见 13.5**）



| #  | 方法     | URI                                             | 控制器 @方法                                     | 额外中间件                                                                                 | 说明                               |
| -- | ------ | ----------------------------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------- | -------------------------------- |
| 19 | POST   | /api/admin/login                                | Api\Admin\AuthController@login              | `api.composite.rate:5,60`                                                             | 管理员登录                            |
| 20 | POST   | /api/admin/refresh                              | Api\Admin\AuthController@refresh            | `api.composite.rate:30,60`                                                            | 刷新                               |
| 21 | POST   | /api/admin/logout                               | Api\Admin\AuthController@logout             | `auth:admin-api`                                                                      | 登出                               |
| 22 | GET    | /api/admin/me                                   | Api\Admin\AdminController@profile           | `auth:admin-api`                                                                      | 本人信息                             |
| 23 | PUT    | /api/admin/me/password                          | Api\Admin\AdminController@changePassword    | `auth:admin-api,admin.operation.log:1,idempotency`                                    | 高危                               |
| 24 | GET    | /api/admin/menu/my-tree                         | Api\Admin\MenuController@myTree             | `auth:admin-api`                                                                      | 菜单缓存读                            |
| 25 | GET    | /api/admin/menu/all-tree                        | Api\Admin\MenuController@allTree            | `auth:admin-api,admin.permission:admin:menu:list`                                     | 直读库                              |
| 26 | POST   | /api/admin/menu                                 | Api\Admin\MenuController@store              | `auth:admin-api,admin.permission:admin:menu:create,admin.operation.log:0,idempotency` | 清全部菜单缓存                          |
| 27 | PUT    | /api/admin/menu/{id}                            | Api\Admin\MenuController@update             | 同上（update）                                                                            | 清缓存                              |
| 28 | DELETE | /api/admin/menu/{id}                            | Api\Admin\MenuController@destroy            | 同上（delete）                                                                            | 清缓存                              |
| 29 | GET    | /api/admin/permissions                          | Api\Admin\PermissionController@index        | `auth:admin-api,admin.permission:admin:permission:list`                               | 权限全表                             |
| 30 | GET    | /api/admin/roles                                | Api\Admin\RoleController@index              | `auth:admin-api,admin.permission:admin:role:list`                                     | 分页                               |
| 31 | POST   | /api/admin/roles                                | Api\Admin\RoleController@store              | `...:admin:role:create,admin.operation.log:1,idempotency`                             | 高危                               |
| 32 | PUT    | /api/admin/roles/{id}                           | Api\Admin\RoleController@update             | `...:admin:role:update,admin.operation.log:1,idempotency`                             | 高危                               |
| 33 | DELETE | /api/admin/roles/{id}                           | Api\Admin\RoleController@destroy            | `...:admin:role:delete,admin.operation.log:1`                                         | 高危                               |
| 34 | PUT    | /api/admin/roles/{id}/permissions               | Api\Admin\RoleController@assignPermissions  | `...:admin:role:assign,admin.operation.log:1,idempotency`                             | 清该角色管理员缓存                        |
| 35 | PUT    | /api/admin/roles/{id}/menus                     | Api\Admin\RoleController@assignMenus        | 同上                                                                                    | 清该角色管理员菜单缓存                      |
| 36 | GET    | /api/admin/admins                               | Api\Admin\AdminController@index             | `auth:admin-api,admin.permission:admin:admin:list`                                    | 管理员分页                            |
| 37 | POST   | /api/admin/admins                               | Api\Admin\AdminController@store             | `...:admin:admin:create,admin.operation.log:1,idempotency`                            | 高危                               |
| 38 | PUT    | /api/admin/admins/{id}                          | Api\Admin\AdminController@update            | `...:admin:admin:update,admin.operation.log:1,idempotency`                            | 高危                               |
| 39 | PUT    | /api/admin/admins/{id}/password                 | Api\Admin\AdminController@resetPassword     | 同上                                                                                    | 高危                               |
| 40 | PUT    | /api/admin/admins/{id}/status                   | Api\Admin\AdminController@updateStatus      | `...:admin:admin:update,admin.operation.log:0`                                        | 启停                               |
| 41 | DELETE | /api/admin/admins/{id}                          | Api\Admin\AdminController@destroy           | `...:admin:admin:delete,admin.operation.log:1`                                        | 高危                               |
| 42 | GET    | /api/admin/users                                | Api\Admin\UserController@index              | `auth:admin-api,admin.permission:admin:user:list`                                     | 用户分页                             |
| 43 | GET    | /api/admin/users/{id}                           | Api\Admin\UserController@show               | 同上                                                                                    | 详情                               |
| 44 | PUT    | /api/admin/users/{id}/status                    | Api\Admin\UserController@updateStatus       | `...:admin:user:update,admin.operation.log:0`                                         | 启停                               |
| 45 | GET    | /api/admin/users/{id}/online                    | Api\Admin\UserController@online             | `...:admin:user:list`                                                                 | 在线终端数                            |
| 46 | POST   | /api/admin/users/{id}/kick                      | Api\Admin\UserController@kick               | `...:admin:user:kick,admin.operation.log:1,idempotency`                               | 高危，踢全部                           |
| 47 | GET    | /api/admin/products                             | Api\Admin\ProductController@index           | `...:admin:product:list`                                                              |                                  |
| 48 | POST   | /api/admin/products                             | Api\Admin\ProductController@store           | `...:admin:product:create,admin.operation.log:0,idempotency`                          |                                  |
| 49 | PUT    | /api/admin/products/{id}                        | Api\Admin\ProductController@update          | `...:admin:product:update,admin.operation.log:0,idempotency`                          |                                  |
| 50 | DELETE | /api/admin/products/{id}                        | Api\Admin\ProductController@destroy         | `...:admin:product:delete,admin.operation.log:0`                                      | 软删                               |
| 51 | GET    | /api/admin/dicts                                | Api\Admin\DictController@index              | `...:admin:dict:list`                                                                 | 分页可按 type 过滤                     |
| 52 | POST   | /api/admin/dicts                                | Api\Admin\DictController@store              | `...:admin:dict:create,admin.operation.log:0,idempotency`                             | 写后清字典缓存                          |
| 53 | PUT    | /api/admin/dicts/{id}                           | Api\Admin\DictController@update             | `...:admin:dict:update,admin.operation.log:0,idempotency`                             | 清缓存                              |
| 54 | DELETE | /api/admin/dicts/{id}                           | Api\Admin\DictController@destroy            | `...:admin:dict:delete,admin.operation.log:0`                                         | 物理删（dicts 无软删列）                  |
| 55 | GET    | /api/admin/messages                             | Api\Admin\MessageController@index           | `...:admin:message:list`                                                              | 消息分页（含筛选）                        |
| 56 | POST   | /api/admin/messages                             | Api\Admin\MessageController@send            | `...:admin:message:create,admin.operation.log:0,idempotency`                          | 定向 / 广播                          |
| 57 | GET    | /api/admin/favorites                            | Api\Admin\FavoriteController@index          | `...:admin:favorite:list`                                                             | 收藏列表（可按 user\_id/product\_id 过滤） |
| 58 | GET    | /api/admin/operation-logs                       | Api\Admin\OperationLogController@index      | `...:admin:log:list`                                                                  | 分页（读驱动化审计存储，13.4）                |
| 59 | GET    | /api/admin/ip-blacklist                         | Api\Admin\IpBlacklistController@index       | `...:admin:ip:list`                                                                   | 分页                               |
| 60 | POST   | /api/admin/ip-blacklist                         | Api\Admin\IpBlacklistController@store       | `...:admin:ip:create,admin.operation.log:0,idempotency`                               | 新增封禁                             |
| 61 | DELETE | /api/admin/ip-blacklist/{id}                    | Api\Admin\IpBlacklistController@destroy     | `...:admin:ip:delete,admin.operation.log:0`                                           | 解除                               |
| 62 | GET    | /api/admin/recycle-bin/{resource}               | Api\Admin\RecycleBinController@index        | `...:admin:recycle:list`                                                              | resource 白名单见下                   |
| 63 | PUT    | /api/admin/recycle-bin/{resource}/{id}/restore  | Api\Admin\RecycleBinController@restore      | `...:admin:recycle:restore,admin.operation.log:0,idempotency`                         | 单条恢复                             |
| 64 | PUT    | /api/admin/recycle-bin/{resource}/restore-batch | Api\Admin\RecycleBinController@restoreBatch | 同上                                                                                    | 批量恢复 ids=snow\_id 列表             |
| 65 | GET    | /api/admin/rate-limit-rules                     | Api\Admin\RateLimitRuleController@index     | `...:admin:rate:list`                                                                 | 限流规则分页（13.5）                     |
| 66 | POST   | /api/admin/rate-limit-rules                     | Api\Admin\RateLimitRuleController@store     | `...:admin:rate:create,admin.operation.log:0,idempotency`                             | 新增规则 + 清缓存                       |
| 67 | PUT    | /api/admin/rate-limit-rules/{id}                | Api\Admin\RateLimitRuleController@update    | `...:admin:rate:update,admin.operation.log:0,idempotency`                             | 修改 + 清缓存                         |
| 68 | DELETE | /api/admin/rate-limit-rules/{id}                | Api\Admin\RateLimitRuleController@destroy   | `...:admin:rate:delete,admin.operation.log:0`                                         | 删除 + 清缓存                         |
| 69 | POST   | /api/admin/permissions                          | Api\Admin\PermissionController@store        | `...:admin:permission:create,admin.operation.log:0,idempotency`                       | 新建权限（含自定义动作）                     |
| 70 | PUT    | /api/admin/permissions/{id}                     | Api\Admin\PermissionController@update       | `...:admin:permission:update,admin.operation.log:0,idempotency`                       | 修改权限（action/type/status 等）       |
| 71 | DELETE | /api/admin/permissions/{id}                     | Api\Admin\PermissionController@destroy      | `...:admin:permission:delete,admin.operation.log:0`                                   | 删除自定义权限（system 预置不可删）            |

回收站 resource 白名单（RecycleBinService 映射 资源名→模型类）：`products,users,admins,admin_menus,roles,messages,user_favorites`（对应软删除模型；dicts/ip\_blacklist 无软删不收录）。非法 resource → 400 `api.recycle.resource_invalid`。

### 6.3 权限码全表（PermissionSeeder 播种；super-admin 全量）

`admin:product:list/create/update/delete`、`admin:dict:list/create/update/delete`、`admin:user:list/update/kick`、`admin:admin:list/create/update/delete`、`admin:role:list/create/update/delete/assign`、`admin:permission:list`、`admin:permission:create/update/delete`**（权限管理 \[S11]，6.6）**、`admin:menu:list/create/update/delete`、`admin:message:list/create`、`admin:favorite:list`、`admin:recycle:list/restore`、`admin:log:list`、`admin:ip:list/create/delete`、`admin:rate:list/create/update/delete`**（限流规则管理 \[S19]）**（共 40 条）。自定义动作示例（Seeder 可播种）：`admin:product:export`、`admin:user:kick`（已含）等。

### 6.4 菜单树（MenuSeeder 播种）



1. 工作台 dashboard（path `/dashboard`, component `dashboard/index`, 无权限码）

2. 用户管理（`/user`, `user/index`, 权限 `admin:user:list`）

3. 业务管理（目录，children：商品管理 `/product` `admin:product:list`；字典管理 `/dict` `admin:dict:list`）

4. 系统管理（目录，children：管理员管理 `/admin` `admin:admin:list`；角色管理 `/role` `admin:role:list`；菜单管理 `/menu` `admin:menu:list`；权限管理 `/permission` `admin:permission:list`）

5. 消息中心（`/message`, `message/index`, `admin:message:list`）

6. 审计管理（目录，children：操作日志 `/operation-log` `admin:log:list`；IP 黑名单 `/ip-blacklist` `admin:ip:list`）

   6.1 限流规则（`/rate-limit`, `rate-limit/index`, `admin:rate:list`）\[S19]

7. 收藏管理（`/favorite`, `favorite/index`, `admin:favorite:list`）

8. 回收站（`/recycle-bin`, `recycle-bin/index`, `admin:recycle:list`）

### 6.5 菜单缓存清理规则（MenuService，\[S11]）



* key：`admin_menu:tree:{adminId}`，TTL 604800（7 天），`Cache::remember`。

* `myTree()`：super-admin → 全部菜单；否则 admin→roles→role\_menu→menus；组装 children 树（parent\_id 递归，sort 排序，过滤 is\_show=0 仅当需要）。

* 菜单增删改 → 遍历全部启用 admin 的 id，逐个 `Cache::forget`。

* 角色绑定菜单变更 → 该角色下全部 admin 的 key 清理。

* 管理员角色变更 → 仅该 admin 的 key 清理（同时清权限缓存 `admin:permissions:{adminId}`）。

### 6.6 动作级权限（用户新增设计：资源 × 动作 + 自定义动作）



1. **code 约定**：`{resource}:{action}`，四基本动作 `list/create/update/delete`（查 / 增 / 改 / 删），自定义动作不限（`export/audit/kick/cancel/approve/import` 等，后台可定义、数量不限）。

2. **permissions 表增强**（S3 迁移）：新增 `action varchar(50)`（list/create/update/delete 或自定义值）与 `type tinyint`（1=system 预置 / 2=custom 自定义），字段 English / 中文 comment；`group` 按资源 / 模块分组展示。

3. **接口防护**：AdminPermission 中间件按精确 `resource:action` code 校验（已实现）；"能查" 不代表能 "改 / 删"，一个接口对应一个精确权限 code。

4. **后台权限管理**（S11 扩展）：PermissionController 除 index（按 group 分组列出）外新增 store/update/destroy（新建自定义动作权限、启停 type/enabled 逻辑按 status 或删除 —— 以字段为准；新增 / 修改后清 `admin:permissions:{adminId}` 全量缓存）；接口 #69-72 见接口表；权限码：`admin:permission:create/update/delete`（新增 3 条，共 40 条）。

5. **菜单绑定**：菜单 `permission_code` 绑 list 权限作为侧边栏可见性代表；角色分配权限按动作勾选多选。

6. **超管**：拥有全部权限（含所有自定义动作）但走权限集合逻辑、可后台调整；权限变更实时生效（清 Redis 缓存）。

7. **生成器 / Seeder / 文档 / 测试联动**：make:module 生成四动作权限种子片段 + 预留自定义动作（S18）；PermissionSeeder 为系统模块播种 list/create/update/delete + 关键自定义动作（如 `admin:user:kick`、`admin:product:export`）；openapi/docs 同步；Pest 覆盖 "有查无改则改接口 403"。



***

## 7. lang 语言包（\[S1] 一次性写全，zh-CN 与 en 各一份；key → zh /en）

> **前缀约定（重要）**
>
> ：全工程 
>
> `__()`
>
> /
>
> `message()`
>
> /
>
> `ApiException`
>
>  调用统一带 
>
> `api.`
>
>  前缀（如 
>
> `__('api.success')`
>
> ）。lang 文件
>
> **平铺、无顶层包裹键**
>
> ：
>
> `api.php`
>
>  直接 
>
> `return ['success'=>..., 'login'=>[...], ...]`
>
> —— 文件名 
>
> `api.php`
>
>  自身提供 
>
> `api.`
>
>  前缀，
>
> `__('api.success')`
>
>  \= api.php 文件的 success 键。lang:sync 命令与 LocaleService 兼容平铺（若未来出现同名包裹 
>
> `['api'=>[...]]`
>
>  自动解包一层，最终 key 仍为 
>
> `api.xxx`
>
> ）。下表 key 省略 
>
> `api.`
>
>  前缀，实际调用为 
>
> `api.`
>
>  \+ 表值。



```
success 操作成功 / Operation success
fail 操作失败 / Operation failed
param_error 参数错误 / Invalid parameters
unauthorized 未登录或登录已过期 / Unauthenticated or token expired
forbidden 无权限访问 / Access forbidden
not_found 数据不存在 / Resource not found
rate_limited 请求过于频繁，请稍后再试 / Too many requests, please retry later
validation_error 输入校验失败 / Validation failed
server_error 服务器内部错误 / Internal server error
token_invalid 无效的访问令牌 / Invalid access token
token_expired 访问令牌已过期 / Access token expired
token_blacklisted 访问令牌已被拉黑 / Access token has been blacklisted
login.success 登录成功 / Login successful
login.invalid_credentials 账号或密码错误 / Invalid credentials
login.disabled 账号已被禁用 / Account disabled
logout.success 退出登录成功 / Logged out
refresh.success 令牌刷新成功 / Token refreshed
password.old_incorrect 原密码错误 / Original password incorrect
password.changed 密码修改成功 / Password changed
profile.updated 资料更新成功 / Profile updated
account.kicked 账号已在其他设备登录，请重新登录 / Account logged in elsewhere, please login again
device.limit_reached 登录设备数量已达上限 / Device limit reached
device.kicked_all 已强制下线该用户全部终端 / All devices kicked
user.not_found 用户不存在 / User not found
user.disabled 用户已禁用 / User disabled
admin.not_found 管理员不存在 / Admin not found
admin.disabled 管理员已禁用 / Admin disabled
menu.my_tree 菜单加载成功 / Menu loaded
menu.all_tree 全部菜单 / All menus
menu.not_found 菜单不存在 / Menu not found
menu.saved 菜单创建成功 / Menu created
menu.updated 菜单更新成功 / Menu updated
menu.deleted 菜单删除成功 / Menu deleted
menu.cache_cleared 菜单缓存已清理 / Menu cache cleared
role.not_found 角色不存在 / Role not found
role.code_exists 角色标识已存在 / Role code already exists
role.saved 角色创建成功 / Role created
role.updated 角色更新成功 / Role updated
role.deleted 角色删除成功 / Role deleted
role.assigned 分配成功 / Assigned successfully
permission.list 权限列表 / Permission list
product.not_found 商品不存在 / Product not found
product.saved 商品创建成功 / Product created
product.updated 商品更新成功 / Product updated
product.deleted 商品删除成功 / Product deleted
product.restored 商品已恢复 / Product restored
dict.not_found 字典项不存在 / Dict item not found
dict.saved 字典项创建成功 / Dict item created
dict.updated 字典项更新成功 / Dict item updated
dict.deleted 字典项删除成功 / Dict item deleted
dict.type_invalid 字典类型无效 / Invalid dict type
message.sent 消息发送成功 / Message sent
message.not_found 消息不存在 / Message not found
message.marked_read 已标记已读 / Marked as read
message.all_read 全部已读 / All marked as read
message.receiver_type_invalid 接收类型无效 / Invalid receiver type
favorite.added 收藏成功 / Favorited
favorite.removed 已取消收藏 / Favorite removed
favorite.duplicate 已收藏过该商品 / Already favorited
favorite.not_found 收藏记录不存在 / Favorite not found
recycle.list 回收站列表 / Recycle bin list
recycle.restored 恢复成功 / Restored
recycle.batch_restored 批量恢复成功 / Batch restored
recycle.resource_invalid 不支持的回收站资源 / Unsupported recycle resource
log.list 操作日志 / Operation logs
ip.blocked 您的 IP 已被封禁 / Your IP has been blocked
ip.added 封禁成功 / IP blocked
ip.removed 已解除封禁 / IP unblocked
ip.not_found 封禁记录不存在 / IP record not found
health.ok 服务正常 / Service healthy
idempotency.replayed 重复请求已拦截 / Duplicate request intercepted
common.biz_error 业务处理失败 / Business operation failed
```



***

## 8. composer.json 依赖与配置（\[S2] 执行）



* 新依赖（真实 `composer require`，**不带版本参数**，让 Composer 解析当前兼容最新版；成功后 composer.lock/vendor 自动更新）：


  * `php-open-source-saver/jwt-auth`

  * `fruitcake/laravel-cors`

  * `laravel/reverb`

* autoload 增加 `"files": ["app/Helpers/ApiHelper.php", "app/Helpers/common.php"]`（require 完成后用 Edit 改 composer.json 或 require 时一并处理；务必保证 composer.json 最终含该 files 条目，否则 message () 不可用）。

* require-dev 暂不动（Pest 由 Organizer 集成阶段 `composer require --dev pestphp/pest` 处理，并视情况 `--dev pestphp/pest-plugin-laravel`）。

* config/jwt.php 键位：`secret=env('JWT_SECRET')`、`ttl=env('JWT_TTL',60)`（分钟，3600 秒场景按分钟 = 60）、`refresh_ttl=env('JWT_REFRESH_TTL',20160)`、`algo=HS256`、`required_claims`、`persistent_claims=['guard']`、`lock_subject=true`、`leeway=0`、`blacklist_enabled=env('JWT_BLACKLIST_ENABLED',false)`、`blacklist_grace_period=0`、`blacklist_clear_after` 默认、`store=env('JWT_BLACKLIST_STORE','default')`（默认缓存库，生产 redis）。

* .env.example 同步 .env 全部新增键：`APP_LOCALE=zh-CN`、`APP_FALLBACK_LOCALE=zh-CN`、`APP_FAKER_LOCALE=zh_CN`、`JWT_SECRET=`、`JWT_TTL=60`、`JWT_REFRESH_TTL=20160`、`JWT_BLACKLIST_ENABLED=true`、`REVERB_*` 四项、`CORS_ALLOWED_ORIGINS=http://localhost:5173`、`SNOW_WORKER_ID=0`（并在 .env 补 `SNOW_WORKER_ID=0`）。

* config/app.php 追加：`'snow_worker_id' => (int) env('SNOW_WORKER_ID', 0)`、`'desensitize_fields' => ['mobile','id_card']`、`'idempotency_ttl' => 600`（\[S2] Edit 现有文件追加，不动其他键）。



***

## 9. Shard 分工与边界（Round 1 并行 10 个 / Round 2 并行 7 个）

### Round 1



| Shard          | 负责                                                                     | 关键文件（绝对路径前缀 = backend-laravel13\）                                                                                                                                                                                                                                                         |
| -------------- | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S1 基础层         | Helpers/Enums/Support/Exceptions/lang                                  | app\Helpers\ApiHelper.php, common.php；app\Enums{UserStatus,AdminStatus,ProductStatus,DictStatus,MessageReceiverType,MessageType,OperationRisk,ErrorCode}.php；app\Support\Snowflake.php；app\Exceptions{ApiException,ErrorCode,ApiExceptionHandler}.php；lang\zh-CN\api.php, lang\en\api.php |
| S2 装配层         | configs/bootstrap/composer/.env/console/Provider + **唯一 composer 执行者** | config\auth.php, jwt.php, cors.php, broadcasting.php, reverb.php；bootstrap\app.php, providers.php；composer.json；.env.example；routes\console.php；app\Providers\AppServiceProvider.php；config\app.php（追加键）                                                                                  |
| S3 数据层         | 迁移 / Seeder/Factory                                                    | database\migrations\（12 个新建 + 1 个重写）；database\seeders\（6 个）；database\factories\（6 个）                                                                                                                                                                                                      |
| S4 模型层         | 全部模型                                                                   | app\Models\（12 模型 + Traits\HasSnowId）                                                                                                                                                                                                                                                     |
| S5 中间件层        | 8 个中间件                                                                 | app\Http\Middleware\（8 文件）                                                                                                                                                                                                                                                                |
| S6 认证模块        | 双端认证 + 多端登录 + 个人中心                                                     | Api\User\AuthController, UserController；Api\Admin\AuthController；Services\User{AuthService,MultiDeviceService,UserService}；Services\Admin\AuthService；Requests\User{LoginRequest,UpdateProfileRequest,ChangePasswordRequest}；Requests\Admin\AdminLoginRequest                             |
| S7 文档          | docs 全量                                                                | docs\deploy.md, dev-guide.md, business-rule.md, test-case.md, extend-example-full-isolation.md；docs\extensions{object-storage,sms,captcha,async-export,chunk-import,feature-toggle,system-configs,docker-compose}.md                                                                      |
| S8 OpenAPI     | openapi.yaml                                                           | openapi\openapi.yaml（python yaml 校验）                                                                                                                                                                                                                                                      |
| S9 Vue3 前端     | 管理后台骨架                                                                 | frontend-vue3\（Vite+Pinia+Element-Plus 完整工程文件，含登录 / 动态菜单布局 / 各业务页面要点，axios 封装统一响应 + 401+lang+fingerprint + 幂等头）                                                                                                                                                                           |
| S10 uni-app 前端 | C 端模板                                                                  | frontend-uniapp\（pages.json/manifest/request 封装 / 登录 / 首页 / 商品 / 消息 / 收藏页要点 + 订阅 Reverb 频道示例）                                                                                                                                                                                             |

### Round 2



| Shard                | 负责                               | 关键文件                                                                                                                                                                                                                                                                                                                      |
| -------------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S11 RBAC + 菜单 + 用户管理 | 管理员 CRUD / 角色 / 权限 / 菜单 / 普通用户管理 | Api\Admin {AdminController,RoleController,PermissionController,MenuController,UserController}；Services\Admin {AdminService,RoleService,PermissionService,MenuService,UserService}；对应 Requests\Admin\*                                                                                                                     |
| S12 消息模块             | 站内消息 + 广播                        | Api\User\MessageController、Api\Admin\MessageController；Services\User\MessageService、Services\Admin\MessageService；App\Events\MessageSent.php；Requests\Admin\MessageSendRequest                                                                                                                                            |
| S13 商品 + 字典          | 只读 + 全量                          | Api\User{ProductController,DictController}；Api\Admin{ProductController,DictController}；Services\User{ProductService,DictService}；Services\Admin{ProductService,DictService}；Requests\User{ProductListRequest,DictShowRequest}、Requests\Admin{ProductStoreRequest,ProductUpdateRequest,DictStoreRequest,DictUpdateRequest} |
| S14 增强模块             | 健康 / 回收站 / 操作日志 / IP 黑名单         | Api\HealthController；Api\Admin{RecycleBinController,OperationLogController,IpBlacklistController}；Services\Admin{RecycleBinService,OperationLogService,IpBlacklistService}；Requests\Admin{RecycleRestoreBatchRequest,IpBlacklistStoreRequest}                                                                             |
| S15 收藏扩展示例           | 全层隔离模板                           | Api\User\FavoriteController、Api\Admin\FavoriteController；Services\User\FavoriteService、Services\Admin\FavoriteService；Requests\User {FavoriteStoreRequest,FavoriteListRequest}（模型 UserFavorite/AdminFavorite 属 S4，**S15 不得重写**）                                                                                           |
| S16 路由               | routes/api.php + channels.php    | routes\api.php, routes\channels.php（依据第 6 节接口清单 + 实际控制器文件核对）                                                                                                                                                                                                                                                              |
| S17 测试               | Pest 用例                          | tests\Pest.php；tests\Feature\Api\（AuthTest, GuardIsolationTest, RateLimitTest, MultiDeviceTest, RbacAndMenuTest, MessageTest, ProductDictTest, FavoriteTest, IdempotencyTest, IpBlacklistTest, DesensitizeTest, RecycleTest）                                                                                              |
| S18 生成器              | Base 公用层 + make:module 命令        | app\Base\（BaseController,BaseService,BaseRepository,BaseModel,BaseMiddleware）；app\Console\Commands\MakeModuleCommand.php；docs\codegen.md；真实运行生成演示模块 Notices（文件保留工程内）                                                                                                                                                      |



***

## 10. 编码硬约束（沿用规格 §20 + 细化）



1. 状态 / 类型一律 BackedEnum（`App\Enums\*`），禁止魔术数字裸用。

2. 禁止 `Model::all()`；列表分页（page/page\_size，page\_size ≤ 100）；禁止 N+1，`with()` 预加载。

3. 多表写操作 `DB::transaction()`。

4. 对外接口禁止暴露自增 `id`（模型 `$hidden` 已隐藏；控制器入参 `{id}` 一律按 snow\_id 用 `where('snow_id',$id)` 解析）。

5. 业务模型统一 SoftDeletes（dicts 表无软删列，可物理删）。

6. 提示文案一律 lang key（第 7 节表），禁止硬编码中文（验证异常 message 用 `api.validation_error` 或字段级 key）。

7. 控制器只做入参出参，业务在 Service；入参用 FormRequest。

8. Service 内身份 ID 由控制器传入（`$request->user()->id`），Service 不读 `auth()/request()` 单例（可测试性）。

9. 所有 Redis 交互一律走 **Cache facade（默认 store）**：生产 = redis、测试 = array，天然可测且多实例共享。**禁止&#x20;**`Redis::`**&#x20;门面 / 连接直用**（广播与队列连接例外：BROADCAST\_CONNECTION/QUEUE\_CONNECTION 由框架管理）。

10. 分页响应结构统一 `{list,total,page,page_size}`。

11. 密码字段写入日志一律 `***`。

12. Windows 文件写入 UTF-8 无 BOM；PHP 文件以 `<?php` 开头、`declare(strict_types=1);` 可选用（保持全工程统一：**用**）。

13. FormRequest 校验失败由全局处理器转 422（data=`$e->errors()`）。



***

## 11. 注释规范（用户强制要求，硬性验收项，覆盖全部后端 PHP 文件）

> 注释格式统一为
>
> **英文在前、**
>
> `/`
>
> **&#x20;分隔、中文在后**
>
> （English first, 
>
> `/`
>
>  then Chinese）。



1. **数据表字段注释**：所有迁移文件（migrations）每个字段都必须加 `->comment('English/中文')`，英文在前、`/`、中文在后，例如：



```
$table->string('name')->comment('User name/用户名');
$table->tinyInteger('status')->comment('Status 1=enabled 0=disabled/状态 1=启用 0=禁用');
```

覆盖全部表：users、admins、roles、permissions、admin\_role、role\_permission、role\_menu、admin\_menus、products、dicts、messages、user\_favorites、ip\_blacklist（及骨架既有表若改造；**admin\_operation\_logs 不建表，审计见 13.4**）。中间表每列也加。

2\. **方法中英双语注释**：一切类方法（Controller/Service/Model/Middleware/FormRequest/Seeder/Factory/Enum/Helper 函数 / Job 等）必须同时含英文 + 中文，**英文在前**，格式示例：



```
/**
 * User login/用户登录
 *
 * @param array $data login data/登录数据
 * @return array
 */
```

行内注释同样 `// English/中文`；每条方法必须两种语言齐备且英文在前。

3\. 注释须真实有信息量（作用 / 入参 / 返回 / 关键逻辑），禁止占位废话；字段注释给出业务含义。

4\. 此项为**硬性验收**：交付前抽查迁移字段 comment 是否均为 `English/中文` 格式、方法注释是否英文在前，不达标返工。



***

## 12. Base 公用层与 make:module 代码生成器（用户新增核心交付物，\[S18]）

### 12.1 Base 公用基类（backend-laravel13\app\Base\，一次性创建、全工程复用）



| 类                | 职责                                                                                                                                                                              |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `BaseController` | 统一响应 `message()` 便捷转发；分页参数解析（page/page\_size，page\_size≤100 钳制）；当前守卫用户获取 `guardUser(string $guard)`；FormRequest 自动校验（构造注入 request 已由框架完成，提供 `validatedData()` 便捷）               |
| `BaseService`    | `transaction(callable)` DB::transaction 封装；统一异常转抛 `ApiException`；通用 CRUD 骨架方法（index/show/store/update/destroy 委托 Repository）；业务逻辑模板注释                                           |
| `BaseRepository` | 统一 CRUD：find/findOrFail/paginate/create/update/delete (软删)/restore/forceDelete；分页返回 `{list,total,page,page_size}`；缓存钩子 `cacheKey(): ?string` / `forgetCache()`（可覆写，默认无缓存）；软删除支持 |
| `BaseModel`      | 引入 SoftDeletes（子类可关闭）；通用 Scope（如 `scopeActive()`）；`attributeComments(): array` 字段注释映射（与迁移 comment 呼应，供文档 / 生成器用）；公共关联占位                                                         |
| `BaseMiddleware` | 可选基类：handle 模板 + 守卫判定辅助 `guard(): ?string`                                                                                                                                      |

### 12.2 make:module 生成器（真实 artisan 命令）



* 命令类：`app\Console\Commands\MakeModuleCommand.php`，signature：`make:module {name} {--with-admin} {--soft-deletes}`，注册进 `routes/console.php` 无需手动（app/Console/Commands 自动发现，确认 Laravel 13 默认自动加载 Commands 目录）。

* 一次运行生成（全部真实可落地、方法中英双语注释、迁移字段全部 `->comment()`）：

1. 迁移：`database/migrations/{YYYY_MM_DD_HHMMSS}_create_{snake_plural}_table.php`——id/snow\_id/ 名称等示例字段（name/title/status/remark 模板 + 提示开发者补充），带 comment；

2. `app\Models\{Studly}.php`——extends `App\Base\BaseModel`，\$table/fillable/casts/ 关联 /scope 骨架；

3. `app\Repositories\{Studly}Repository.php`——extends `App\Base\BaseRepository`；

4. `app\Services\User\{Studly}Service.php`（C 端只读：index/show）与 `app\Services\Admin\{Studly}Service.php`（后台 CRUD）—— 均 extends `App\Base\BaseService`；

5. `app\Http\Controllers\Api\User\{Studly}Controller.php`（只读）与 `app\Http\Controllers\Api\Admin\{Studly}Controller.php`（CRUD）—— 均 extends `App\Base\BaseController`；

6. FormRequest：`app\Http\Requests\User\{Studly}IndexRequest.php`、`app\Http\Requests\Admin\{Studly}StoreRequest.php`/`UpdateRequest.php`；

7. lang：`lang/zh-CN/{module}.php` 与 `lang/en/{module}.php` 双语词条骨架（success/not\_found/saved/updated/deleted + module 名前缀约定 `module.{snake}.xxx`）；

8. 路由自动注册：生成器向 `routes/api.php` **文件末尾追加**两个分组注册块（C 端 `/api/{snake}` 只读 + 管理端 `/api/admin/{snake}` CRUD，中间件按契约 6 节模式：auth:api /auth:admin-api + admin.permission:{module}:{action}），并同步提示开发者去 PermissionSeeder 补权限码；

9. 输出生成文件清单与下一步提示（补迁移字段、补权限码、migrate）。

* 命名约定：name 入参 Studly（如 `UserFavorite`）→ 表名 `user_favorites`（snake+plural）、路由前缀 `/user-favorites` 或 `/user-favorites`（snake 复数）；写死规则并写入 `docs/codegen.md`。

* 验收：真实运行 `D:\BtSoft\php\83\php.exe artisan make:module Notices` 生成一个示例模块，生成的全部 PHP 文件 `php -l` 通过、extends 继承链正确、迁移字段带 comment、方法双语注释；示例模块文件保留在工程内作为演示（命名 `Notices`，迁移含 name/title/status/remark 字段；若与既有路由冲突则前缀使用 `/notices`）。

### 12.3 文档



* `docs/codegen.md`：生成器使用说明（命令参数、命名约定表、生成文件清单、路由 /lang 约定、二次开发提示、验收命令）。



***

## 13. 性能与规模化硬约束（用户新增非功能需求：1000 万级数据量 / 10 万级 QPS）

> 以下为硬性工程约束，须贯彻到迁移 / 代码 / 文档 / 生成器；凡骨架单点无法保证的（如 10 万 QPS 本身），必须在 docs 写清所需部署架构并明确 "骨架提供可水平扩展的架构与最佳实践，实测 10 万 QPS 需按该架构部署并压测验证"，不得声称骨架单点即达。

### 13.1 数据量（1000 万级）



1. 迁移索引规范见第 3 节 "索引补充规范"（where/join/orderBy/groupBy 列必须建索引，comment 标注用途）。

2. 大表可扩展方案写入 docs/deploy.md 数据层章节：messages 按月或按年**分区**建议（MySQL 5.7 RANGE 分区语法）、**归档**策略（schedule 已有归档任务：30 天前已读消息归档）；user\_favorites 按 user\_id 分表 / 分区建议；products 只保留在线数据。~~admin\_operation\_logs~~ 不占 MySQL（审计驱动化见 13.4）。

3. 全工程禁止 `Model::all()`；列表一律分页（page/page\_size ≤100 钳制）；**深分页用 keyset / 游标分页**（`where snow_id/id < cursor` + 倒序 LIMIT），普通 paginate 仅用于浅分页；契约 5.4 保留普通分页但文档说明 keyset 场景。

4. 禁 N+1：`with()` 预加载为默认写法（契约 10.2）。

5. 高频只读（dicts/admin\_menus/ 热门商品）走 Redis cache-aside（Cache::remember + 兜底 TTL），禁止大表直查。

6. 批量写用 `insert()`/`chunkById()`，禁止循环单条插入。

### 13.2 QPS（10 万级）



1. 热路径状态全部 Redis（复合限流 / JWT 黑名单 / 菜单缓存 / 多端登录 / 幂等 / IP 黑名单 / Reverb 广播），key 带 TTL，多实例共享同一 Redis（水平扩展前提）—— 已按 Cache facade 实现。

2. 缓存防击穿 / 雪崩 / 穿透：热点 key 用**互斥重建**（Cache::lock 或缓存空值）与**逻辑过期**（值内嵌过期时间 + 异步重建）写入 docs/dev-guide 缓存章节；字典 / 菜单等设兜底 TTL。

3. 写路径异步化：消息推送 / 导出 / 通知走 Redis 队列（QUEUE\_CONNECTION=redis 已配）；交易性写 DB::transaction。

4. docs/deploy.md 必须写明支撑 10 万级 QPS 的部署架构：Nginx（worker 调优 / 长连接）+ PHP-FPM 多进程池调优（pm.max\_children 等）+ OpCache（opcache.enable/validate\_timestamps=0）+ Redis 热数据 + MySQL 读写分离与从库（读多写少场景）+ CDN / 静态资源分离 + 多实例水平扩展共享同一 Redis + 连接池与长连接 + 监控告警（health 接口已规划）—— 并明示 "实测 10 万 QPS 需按此架构部署并压测"。

### 13.3 代码生成器联动



1. `make:module`（第 12 节）生成的 Repository/Service 默认含：分页（上限钳制）+ 缓存读钩子（cacheKey/forgetCache）+ 批量写 + 迁移带索引与字段 comment；大表默认 keyset 分页模板。

2. docs/codegen.md 写入大表 / 高并发下模块开发规范（索引先行、keyset、缓存钩子、批量写）。

### 13.4 审计日志驱动化存储（用户设计变更：审计不落 MySQL）

> 管理员操作日志（审计）为海量 append-only 数据，
>
> **不写入 MySQL**
>
> （不建 admin_operation_logs 表、不占业务库 IO），改驱动化存储：默认文本 JSON 行文件，可选 Elasticsearch。



1. **实现位置**：`App\Services\Admin\OperationLogService`（FQCN 与 `record(array $data)` 签名保持不变，S5 AdminOperationLog 中间件零改动），内部按驱动分发：

* `file`（默认，开箱即用）：写 `storage/logs/audit/audit-YYYY-MM-DD.log`，每条 JSON 行含 `log_id`（毫秒时间戳 + 随机）、admin\_id、url、method、ip、request\_params、response\_result、cost\_ms、is\_high\_risk、created\_at；

* `elasticsearch`（可选，完整代码、默认不启用）：Guzzle 调 `_bulk` API 写索引 `audit_logs-YYYY.MM.DD`，字段同上；切换配置 `AUDIT_LOG_DRIVER=file|elasticsearch`，未配置 / ES 不可达自动回退 file 并 Log::warning。

1. **配置**：新建 `config/audit.php`（driver/path/es\_host/es\_index\_prefix/retention\_days）；`.env`/`.env.example` 由集成阶段补 `AUDIT_LOG_DRIVER`。

2. **读取**：`OperationLogService::read(array $filters, int $page, int $pageSize)` 供 `OperationLogController@index`（分页、admin\_id / 日期过滤）、`show(string $logId)`（按 log\_id 查单条）；文件驱动解析当日与历史文件按 created\_at 倒序。

3. **清理策略**：文件驱动按 `retention_days`（默认 90）删除过期文件，由 schedule 每日任务执行（集成阶段补入 routes/console.php）；ES 按索引滚动删除（写 docs）。

4. **删除迁移**：S3 删除 `0001_01_01_000110_create_admin_operation_logs_table.php`；MySQL 不建审计表。

5. **文档 / OpenAPI 同步**：docs（deploy/business-rule/dev-guide）与 openapi.yaml 中 admin\_operation\_logs 落库描述改为驱动化存储（S7/S8 返工）。

6. **扩展**：若用户后续想落库，docs/extensions 提供可选迁移片段（默认不落库）。

### 13.5 限流规则后台可配置（用户设计变更：不写死限流参数）

> `api.composite.rate`
>
>  中间件参数不再写死在路由，规则存 
>
> `rate_limit_rules`
>
>  表，后台可增删改（次数 / 时长 / 启停），变更即时生效、多实例共享。



1. **表**：`rate_limit_rules`（迁移 \[S19]）：id、`rule_key` varchar unique（路由 / 分组标识：`login`、`admin:login`、`api:global`、`admin:global`、`product:list` 等）、`max_attempts` int、`decay_seconds` int、`enabled` tinyint 默认 1、`remark`、timestamps；字段全 English / 中文 comment。

2. **种子默认规则**（\[S19] Seeder，可后台改）：`api:global` 60/60、`admin:global` 120/60、`login` 5/60、`admin:login` 5/60。

3. **运行时解析**：`App\Services\Admin\RateLimitRuleService::resolve(?string $routeName, string $path)` → Cache::remember(`rate_limit:rules`, 300, 读全表 enabled 规则数组)；匹配优先 `rule_key==routeName` 再 `rule_key==path` 再 `api:global/admin:global`（按守卫），无匹配兜底 60/60；返回 \[max, decay]。中间件无参数时调用；有参数（`api.composite.rate:10,60`）时参数覆盖（保留代码覆盖能力，默认不用）。

4. **规则管理接口**：#65-68（\[S19] RateLimitRuleController/RateLimitRuleService/FormRequest×3，权限码 `admin:rate:list/create/update/delete`）；写操作后 `Cache::forget('rate_limit:rules')`。

5. **路由挂载**：契约 #2/#20 登录接口挂 `api.composite.rate`（无参）；管理端组默认挂 `api.composite.rate`（无参）；S16 路由实现按此。

6. **文档 / OpenAPI / 生成器同步**：docs（business-rule/dev-guide/deploy）说明后台配置路径；openapi 增加 #65-68；`make:module` 生成路由挂无参 `api.composite.rate` 并说明后台调整（集成阶段处理）。

### 13.6 统一状态码体系（用户设计定稿：HTTP 一律 200 + body.code，国内接口约定）

> 所有 API 响应 HTTP 恒 200；真实结果用响应体 
>
> `code`
>
>  表达。响应结构 
>
> `{code:int, msg:多语言, data:object|null}`
>
> 。



1. **码表**（`App\Enums\StatusCode` BackedEnum，值为 code）：0 成功、1 通用错误、1001 参数错误、1002 未认证 / 登录失效（Token 无效 / 过期 / 被踢）、1003 无权限（RBAC）、1004 资源不存在、1005 表单校验失败、1006 触发限流、1007 冲突 / 重复提交（幂等、重复收藏）、**1008 验证码错误 / 过期（登录接口 captcha_id/captcha_code 校验失败）**、5000 服务器内部错误；2xxxx 起业务自定义码（docs 登记）。

2. **映射**：`ApiExceptionHandler::render` 统一 HTTP 200 + code。ErrorCode（内部语义 401/403/404/409/422/429/400/500）→ StatusCode：401→1002、403→1003、404→1004、409→1007、422→1005、429→1006、400→1001、500→5000；ValidationException→1005、AuthenticationException→1002、AuthorizationException→1003、ModelNotFoundException/NotFoundHttpException→1004、HttpExceptionInterface 其余→5000、Throwable→5000（生产隐藏堆栈）。

3. **message () 成功**：code=0（全工程 `message('...', 200, ...)` 已批量改 0）；`success` 字段 `code === 0` 判定。

4. **中间件抛错**：一律 `ApiException`（ErrorCode 语义），handler 映射；中间件不再设置 HTTP 状态码。

5. **openapi/docs/Pest**：错误示例统一 HTTP 200 + code；测试断言查 `body.code`（成功 0、错误对应码）。基础设施 / 健康检查可保留真实 HTTP 状态。

6. **前端 axios/uni request 拦截器**：按 `body.code === 0` 判断成功，非 0 弹 msg（不依赖 HTTP status）。

### 13.7 语言包接口 + 后台多语言管理 + md5 版本校验（\[S1] 交付）

> 前端运行时从接口加载语言文案，后台改文案后清缓存、前端重拉即生效；本地缓存用 md5 校验省流量。



1. **C 端公开**：

* `GET /api/locale/{lang?}`：lang 解析顺序 路径 > `?lang=` > `config('app.locale')`；返回 `data:{lang, md5, items:{key:value}}`（全语言文件递归点号展开，key 如 `api.success`）；非法 lang / 目录穿越 → code 1004。缓存 `locale:pack:{lang}` TTL 86400。

* `GET /api/locale/{lang}/meta`：轻量校验端点，返回 `data:{lang, md5}`（不返回 items，前端 md5 比对用）。

1. **md5 稳定算法**：`ksort($items)` + `md5(json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))`；内容变更（后台编辑 / 清缓存重建）后 md5 随之变化。

2. **管理端**（RBAC 动作级权限，\[S1] PermissionSeeder 追加 3 条 system：`admin:i18n:list/update/refresh`）：

* `GET /api/admin/locale/languages`（i18n:list）、`GET /api/admin/locale/{lang}`（i18n:list）

* `PUT /api/admin/locale/{lang}`（i18n:update，body `{items:{key:value}}`，**只覆盖已存在 key**，未知 key 返 `data.skipped`；写回 lang PHP 文件 + 清 `locale:pack:{lang}` 缓存）

* `POST /api/admin/locale/refresh`（i18n:refresh，body 可选 `{lang}`，不带清全部语言缓存）

1. **前端**：vue3/uniapp 本地缓存 `{md5, items, updatedAt}`（localStorage/uni storage）；启动先本地渲染 → `meta` 比对 md5：一致用本地不发全量、不一致拉全量覆盖；触发时机：启动 / 回前台（visibilitychange/onShow）/ 刷新按钮（force 强拉）/ 轮询；刷新按钮 vue3 调 `POST /api/admin/locale/refresh` 清后端缓存后强拉。

2. **前端文案规范**：模板 / 组件内禁止硬编码中文，一律 i18n key；新增 key 同步补后端 `lang/{zh-CN,en}/` 平铺文件（`api.php` 提供 `api.` 前缀）与前端语言包，保证 "后台改文案→点刷新→前端生效" 闭环覆盖。



### 13.8 登录图形验证码 + 前端协作约定

1. **验证码端点**：`GET /api/captcha`（公开，限流 `api.composite.rate`）→ 返回 `{captcha_id: string, image: base64PNG}`；不依赖 session，前后端无状态。
2. **Redis 存储**：key = `captcha:{captcha_id}`，value = 正确字符，TTL 300 秒；**一次性消费**——无论校验成功或失败都 `Cache::forget`（错误也消费，防暴力试解）。
3. **登录必填**：`POST /api/login` 与 `POST /api/admin/login` body 必须带 `captcha_id` + `captcha_code`；校验失败 → `ApiException` code=1008 `api.captcha.invalid`；captcha_id 不存在/过期 → 同 1008。
4. **前端协作约定**：
   - **菜单 path 与前端路由 path 完全一致**：Seeder 菜单 `path` 字段（如 `/product`、`/user`、`/dict`）必须与 Vue Router 实际注册的路由 path 一字不差（**单数**，不用 `/products`）；改一侧必须同步另一侧，否则侧边栏点击 404。
   - **`admin:permissions:{adminId}` 缓存必须为纯数组**：`Cache::remember` 返回值必须是 `array`（如 `['admin:product:list', ...]`），**禁止直接 `->pluck('code')->all()` 之外的 Collection 序列化结果**——Redis 缓存 Collection 反序列化会崩溃；写入前 `array_values(array_unique(...))` 强转纯数组。



***

## 14. 交付与自检要求（每个 SubAgent 最终回报必须包含）



1. 产出文件绝对路径清单（全部）。

2. `php -l` 结果汇总（通过 / 失败数、失败文件 + 错误摘要）；无 PHP 文件的 shard 说明校验方式（如 openapi 用 python `yaml.safe_load`）。

3. 与契约不符的偏离项（如有）及原因。

4. 需要其他 shard 补充的 lang key（S1 之外如有缺 key）。

5. 未能验证的环境项如实说明。