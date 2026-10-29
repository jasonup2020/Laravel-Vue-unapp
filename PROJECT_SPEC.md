# Laravel13-API-Skeleton V1.2-Full 完整技术方案（并入新增全部增强能力）

> 版本：V1.2-Full
> 更新：增加**生产必选内置中间件/能力**、可选扩展模块、运维检查清单、编码约束、新增风险项；区分骨架内置与按需扩展，避免骨架过度臃肿。
> 技术栈：PHP8.3+ | Laravel13 | MySQL8.0 | Redis6.0+
> 前端配套：Vue3(Vite+Pinia+Element-Plus)、uni-app(H5/小程序/App)

## 目录
1. [项目概述](#1-项目概述)
2. [总体系统架构](#2-总体系统架构)
3. [数据库设计](#3-数据库设计)
4. [认证与身份体系](#4-认证与身份体系)
5. [统一API返回规范](#5-统一api返回规范)
6. [接口限流方案](#6-接口限流方案)
7. [多语言i18n方案](#7-多语言i18n方案)
8. [RBAC管理员权限体系](#8-rbac管理员权限体系)
9. [后台动态可配置菜单（Redis缓存）](#9-后台动态可配置菜单redis缓存)
10. [普通用户多端登录管控（最多3端，后台可踢下线）](#10-普通用户多端登录管控最多3端后台可踢下线)
11. [Reverb WebSocket站内即时消息](#11-reverb-websocket站内即时消息)
12. [安全防护完整设计](#12-安全防护完整设计)
13. [核心业务模块（商品、数据字典）](#13-核心业务模块商品数据字典)
14. [【扩展示例】C端/管理员全层完全隔离开发模板](#14-扩展示例c端管理员全层完全隔离开发模板)
15. [🟢骨架内置增强能力](#15-🟢骨架内置增强能力)
16. [🟡可选扩展模块（文档+片段，按需复制）](#16-🟡可选扩展模块文档片段按需复制)
17. [队列、定时任务、日志体系](#17-队列定时任务日志体系)
18. [目录结构](#18-目录结构)
19. [部署方案&Docker编排&上线检查清单](#19-部署方案docker编排上线检查清单)
20. [二开开发规范](#20-二开开发规范)
21. [测试方案](#21-测试方案)
22. [风险点与规避措施](#22-风险点与规避措施)
23. [OpenAPI接口文档说明](#23-openapi接口文档说明)

## 1 项目概述

### 1.1 项目目标
提供生产级Laravel13 API骨架，实现能力清单：
1. 用户身份隔离：C端普通用户`users`、后台管理员`admins`**两张独立数据表**，两套JWT守卫；Token不可跨守卫越权访问。
2. 统一响应`message()`助手函数，支持多语言翻译、附加扩展字段。
3. **接口维度复合限流**：IP + 登录用户ID / 游客浏览器指纹，Redis存储，多实例计数统一。
4. i18n多语言：`zh-CN` / `en`，中间件自动解析语言头。
5. 管理员RBAC角色-权限体系，接口权限中间件校验。
6. **后台动态配置页面菜单**，数据库存储，优先读取Redis，修改菜单/角色自动清理缓存；Vue前端渲染侧边栏。
7. **C端普通用户多端登录管控：最多允许3个终端同时在线；第4台登录自动踢最早设备；后台管理员可强制将指定普通用户全部设备踢下线**。
8. Laravel Reverb WebSocket站内即时消息，支持定向用户、定向管理员、系统公告；消息持久化MySQL，离线可拉取历史消息。
9. 内置商品、通用数据字典业务示例模块。
10. 全局异常处理器，全部异常统一message格式输出，生产环境不暴露堆栈。
11. 🟢**内置生产增强：幂等防重复提交、响应脱敏、IP黑名单、慢查询告警、回收站软删除恢复、增强审计日志**。
12. 管理员操作日志记录关键行为；Redis队列、计划任务；健康检查监控接口。
13. 配套Vue3、uni-app前端模板；OpenAPI3文档；Pest自动化测试；完整部署、二开、手工测试用例。
14. 提供**全层完全隔离扩展示例**：C端、管理员 `Controller/Service/Model/Request`全部不复用，仅共享物理数据表迁移。
15. 🟡提供可选扩展片段：对象存储、短信、验证码、异步导出、系统配置表等，按需复制，不污染骨架。

### 1.2 约束说明
1. API使用JWT-Bearer认证，**不启用CSRF中间件**，CSRF仅针对Cookie-Session表单场景。
2. 浏览器指纹仅限流辅助，可被篡改，**不能用于鉴权**；高危接口叠加验证码。
3. 多实例部署必须使用Redis；file缓存仅用于本地开发。
4. 多端登录限制**仅作用于C端普通用户(api守卫)**；管理员`admin-api`守卫不受3端限制。

## 2 总体系统架构

```
【前端层】
├─ Vue3(Vite + Pinia + Element-Plus) 管理后台
└- uni-app（H5 / 微信小程序 / APP）C端应用
        ↓ HTTP / HTTPS / WebSocket(WSS)
【Nginx反向代理层】
    SSL证书、真实IP透传、CORS、WebSocket代理、请求大小限制
        ↓
【Laravel13后端】
├-中间件层：多语言、复合限流、JWT认证、权限校验、在线设备校验、操作日志
│  新增内置中间件：幂等防重复提交、响应脱敏、IP黑名单
├-控制器层：认证、管理员、普通用户、商品、字典、消息、回收站
├-Service业务层：业务逻辑下沉，控制器只处理入参出参
├-模型层：User、Admin、Role、Permission、AdminMenu、Message
└-公共助手：message()、通用工具函数库common.php
        ↓
【存储层】
├ MySQL8.0：全部业务数据表
└ Redis：限流计数、JWT黑名单、菜单缓存、用户登录Token集合、幂等缓存、IP黑名单
```

> 两套访问链路完全隔离
1. C端普通用户：路由`/api/*`，守卫`api`，读取`users`表。
2. B端管理员：路由`/api/admin/*`，守卫`admin-api`，读取`admins`表。

## 3 数据库设计

> 全部迁移文件可执行`php artisan migrate`；提供Seeder初始化超级管理员。

### 3.1 users 普通用户表
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|name|varchar|用户名|
|email|varchar|登录邮箱，唯一|
|password|varchar|密码bcrypt哈希|
|status|tinyint|1启用 0禁用|
|remember_token|varchar||
|created_at/updated_at|datetime||
|deleted_at|datetime|软删除|

### 3.2 admins 管理员表
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|username|varchar|管理员登录账号，唯一|
|nickname|varchar|昵称|
|email|varchar|邮箱|
|password|varchar|密码哈希|
|avatar|varchar|头像地址|
|status|tinyint|1启用 0禁用|
|remember_token|varchar||
|created_at/updated_at|datetime||
|deleted_at|datetime|软删除|

### 3.3 roles 角色表（管理员RBAC）
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|name|varchar|角色名称|
|code|varchar|角色唯一标识|
|remark|text|备注|
|created_at/updated_at|||
|deleted_at|datetime|软删除|

### 3.4 permissions 权限表（接口按钮权限）
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|name|varchar|权限名称|
|code|varchar|权限标识如`admin:product:list`|
|group|varchar|权限分组|
|created_at/updated_at|||

### 3.5 admin_role 中间表：管理员-角色多对多
`admin_id`、`role_id`联合主键

### 3.6 role_permission 中间表：角色-权限多对多
`role_id`、`permission_id`联合主键

### 3.7 role_menu 中间表：角色-菜单多对多
`role_id`、`menu_id`联合主键

### 3.8 admin_menus 动态菜单表
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|parent_id|bigint|父菜单ID，0根节点|
|title|varchar|菜单显示标题|
|path|varchar|前端vue路由path|
|component|varchar|null|前端组件路径|
|icon|varchar|null|图标|
|permission_code|varchar|null|绑定权限code|
|sort|int|排序，数字越小越靠前|
|is_show|tinyint|是否侧边栏显示；false仅路由可访问|
|is_cache|tinyint|是否keep-alive缓存页面|
|is_external|tinyint|是否外部链接|
|external_url|varchar|null|外部链接地址|
|created_at/updated_at|||
|deleted_at|datetime|软删除|

### 3.9 products 商品业务示例表
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|name|varchar|商品名称|
|desc|text|商品描述|
|price|decimal(10,2)|价格|
|status|tinyint|上下架状态|
|created_at/updated_at|||
|deleted_at|datetime|软删除|

### 3.10 dicts 通用数据字典表
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|type|varchar|字典类型标识|
|label|varchar|显示文本|
|value|varchar|存储值|
|sort|int|排序|
|status|tinyint|启用状态|
|created_at/updated_at|||

### 3.11 messages 站内消息表（WebSocket）
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|receiver_type|varchar|user/admin/all_user/all_admin|
|receiver_id|bigint|null|接收人ID，广播为null|
|msg_type|varchar|announce公告/biz业务通知/warn管理员告警|
|title|varchar|消息标题|
|content|text|消息内容|
|extra|json|null|扩展参数：跳转url、业务id|
|is_read|tinyint|是否已读|
|is_pop|tinyint|是否客户端弹窗提示|
|created_at/updated_at|||
|deleted_at|datetime|软删除|

### 3.12 admin_operation_logs 管理员操作日志表【增强】
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|admin_id|bigint|null|操作管理员ID|
|url|varchar|请求url|
|method|varchar|请求方法|
|ip|varchar|客户端IP|
|request_params|longtext|null|请求参数json|
|response_result|longtext|null|返回结果json|
|cost_ms|int|null|请求耗时ms|
|is_high_risk|tinyint|0普通 1高危操作：改密码、改角色、踢用户下线|
|created_at|datetime||

### 3.13 user_favorites 用户收藏表（扩展示例物理表）
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|user_id|bigint|普通用户ID|
|product_id|bigint|商品ID|
|created_at/updated_at|||
|deleted_at|datetime|软删除|
> 联合唯一索引 `(user_id,product_id)`

### 3.14 ip_blacklist IP黑名单表【骨架内置】
|字段|类型|说明|
|---|---|---|
|id|bigint|主键|
|ip|varchar|封禁IP地址|
|remark|varchar|null|封禁备注原因|
|expire_at|datetime|null|过期时间，null永久封禁|
|created_at/updated_at|||

## 4 认证与身份体系

### 4.1 JWT依赖包
`php-open-source-saver/jwt-auth`社区维护版本。

### 4.2 auth守卫配置 `config/auth.php`
1. `api`守卫：provider=users，面向C端普通用户。
2. `admin-api`守卫：provider=admins，面向后台管理员。

> User、Admin模型均实现`JWTSubject`接口；JWT payload携带`guard`字段区分身份来源。

### 4.3 JWT黑名单
- 存储介质：Redis Cache；`.env`开启`JWT_BLACKLIST_ENABLED=true`
- 登出、被踢下线的Token存入黑名单，未过期也直接拒绝访问。

### 4.4 关键接口清单
|接口地址|说明|守卫|
|---|---|---|
|`POST /api/login`|普通用户登录|无|
|`POST /api/refresh`|刷新普通用户token|无|
|`POST /api/logout`|普通用户登出|auth:api|
|`POST /api/admin/login`|管理员登录|无|
|`POST /api/admin/refresh`|刷新管理员token|无|
|`POST /api/admin/logout`|管理员登出|auth:admin-api|

> 安全约束：普通用户token不能访问`/api/admin/*`；管理员token不能访问C端受鉴权接口。

## 5 统一API返回规范

### 5.1 message()助手函数
文件 `app/Helpers/ApiHelper.php`
- 支持传入普通文本 / 多语言翻译key；
- `$code`支持数字业务码，也支持数组作为附加返回字段（token、expires_in）；
- 直接返回`JsonResponse`对象，控制器return即可。

**成功示例**
```json
{
    "success": true,
    "code": 200,
    "msg": "操作成功",
    "data": {
        "token":"xxx",
        "expires_in":3600
    }
}
```
**失败示例**
```json
{
    "success": false,
    "code": 429,
    "msg": "请求过于频繁，请稍后再试",
    "data": null
}
```
> 表单校验、限流、鉴权异常、服务内部异常全部统一该格式输出。

## 6 接口限流方案
中间件别名：`api.composite.rate:maxAttempts,decaySeconds`

### 6.1 Key生成规则
1. **已登录用户（普通用户/管理员）**
`composite_rate:{api_path}:{ip}:uid_{user_id}`
> 根据当前守卫自动识别登录ID；管理员与普通用户计数隔离。

2. **游客未登录**
`composite_rate:{api_path}:{ip}:fp_{浏览器指纹}`
> 前端生成指纹放Header `X-Client-Fingerprint`；指纹缺失降级仅用IP。

3. **接口维度隔离**：不同路由独立计数器；A接口超限不影响B接口。

### 6.2 使用示例
```php
// 全局默认：60次/60秒
->middleware('api.composite.rate:60,60')
// 登录防爆破：5次/60秒
->middleware('api.composite.rate:5,60')
```

### 6.3 存储：Redis，key自带TTL自动过期；多实例部署计数全局统一。

## 7 多语言i18n方案
1. 中间件`LangSwitch`读取请求头`Accept-Language`或者query参数`lang`。
2. 允许语言列表 `['zh-CN','en']`，非法输入自动回退中文。
3. 语言文件存放目录 `lang/zh-CN/api.php`、`lang/en/api.php`。
4. `message()`内部调用`__()`完成翻译；前端axios自动携带语言请求头。

## 8 RBAC管理员权限体系
> **RBAC仅用于管理员admin；C端普通用户不引入RBAC，保持轻量化**。
1. 角色表`roles`；权限表`permissions`；多对多中间表`admin_role`、`role_permission`。
2. 中间件`AdminPermission`挂载在`/api/admin/*`路由组；校验当前管理员是否拥有接口权限code。
3. 一个管理员可以分配多个角色，权限取并集。
4. 菜单表`admin_menus.permission_code`与权限表code一一对应。

## 9 后台动态可配置菜单（Redis缓存）

### 9.1 业务能力
1. 后台管理页面维护菜单树：新增、编辑、排序、显示/隐藏、设置父级。
2. 角色分配菜单；登录管理员获取自己有权限的树形菜单返回Vue前端渲染侧边栏。
3. **缓存策略：优先读Redis，缓存未命中查询MySQL，回写Redis；TTL7天兜底过期。**
    - 缓存key：`admin_menu:tree:{adminId}`
    - 接口`my-tree`：给前端渲染侧边栏，走缓存。
    - 接口`all-tree`：后台菜单配置页面，直接读库，不走缓存。
4. 缓存清理触发条件：
    - 新增/编辑/删除菜单：清空全部管理员菜单缓存。
    - 修改角色绑定菜单：清除该角色下全部管理员缓存。
    - 修改管理员所属角色：仅清除该管理员缓存。
5. 注意：`is_show=false`只是UI隐藏菜单，**不能做接口权限控制；接口访问必须依赖RBAC权限中间件校验**。

### 9.2 关键接口
|接口|说明|
|---|---|
|`GET /api/admin/menu/my-tree`|获取当前管理员可用菜单树（前端登录后调用，优先Redis）|
|`GET /api/admin/menu/all-tree`|后台菜单管理获取全部菜单树（直接读库）|
|`POST/PUT/DELETE /api/admin/menu`|菜单CRUD管理接口|

> 前端：登录成功调用`my-tree`，存入Pinia，循环渲染侧边栏el-menu；菜单保存成功需要重新调用接口刷新菜单数据。

## 10 普通用户多端登录管控（最多3端，后台可踢下线）
> 仅作用C端普通用户`api`守卫；管理员账号不受限制。
1. Redis存储用户有效Token集合 `user:token:list:{userId}`。
2. 业务逻辑：
    - 用户登录成功，将新Token写入Redis集合；如果集合内token数量>3，自动剔除最早登录token，并打入JWT黑名单。
    - 用户主动logout：移除当前token，加入黑名单。
    - Token刷新：旧token失效，新token加入集合。
3. 后台管理员接口：
    - `GET /api/admin/user/{id}/online`：查看普通用户在线终端数量。
    - `POST /api/admin/user/{id}/kick`：强制踢出该普通用户**全部终端**；清空Redis集合，全部token打入黑名单。
4. 中间件`check.user.online`挂载C端鉴权路由；用户被踢下线后，即使JWT未过期，访问接口直接返回401提示被强制下线。

## 11 Reverb WebSocket站内即时消息
1. 使用Laravel官方Reverb做WebSocket服务，Redis广播驱动；supervisor守护进程。
2. 频道设计：
    - `private-user.{userId}`：普通用户私有频道。
    - `private-admin.{adminId}`：管理员私有频道。
    - `public-system-announce`：系统公共公告频道。
3. 能力：定向给单个用户/管理员发消息；系统全员公告；消息持久化MySQL；未读统计；标记已读；离线上线拉取历史消息。
4. 前端使用`laravel-echo + pusher-js`订阅频道；小程序必须WSS域名。
5. 消息数据量大，生产建议定时归档历史消息。

## 12 安全防护完整设计
|安全项|实现方案|
|---|---|
|SQL注入|Eloquent/QueryBuilder参数绑定；禁止原生SQL字符串拼接|
|XSS攻击|FormRequest输入校验过滤；JSON自动转义；前端框架输出转义|
|CORS跨域|fruitcake/laravel-cors配置域名白名单|
|暴力请求|IP+用户ID+浏览器指纹复合限流，429统一message返回|
|JWT安全|Redis黑名单；payload携带guard标识；禁止跨守卫访问接口|
|越权访问|两套独立auth守卫；管理员RBAC权限中间件校验接口|
|异常信息泄露|全局异常处理器；生产关闭APP_DEBUG，不输出堆栈|
|文件上传安全|校验真实文件MIME，禁止可执行脚本；上传目录Nginx禁止执行PHP|
|HTTP安全头|X-XSS-Protection、X-Content-Type-Options、Referrer-Policy|
|真实客户端IP|Nginx配置`X-Forwarded-For`；Laravel配置信任代理，限流获取真实IP|
|密码安全|bcrypt哈希；管理员密码复杂度校验；登录错误次数限制|
|防DOS攻击|Nginx+PHP限制请求体最大大小；分页接口限制page_size最大值|
|恶意IP扫描|🟢IP黑名单中间件，Redis+数据表双重存储|
|重复提交|🟢幂等防重复提交中间件，`X-Idempotency-Key`请求头|

> 建议增强：管理员后台接口可配置IP白名单中间件，限制后台访问来源IP。

## 13 核心业务模块（商品、数据字典）
1. **商品模块**：C端仅开放只读列表、详情；管理员拥有完整增删改查。
2. **数据字典模块**：C端根据type获取字典选项；管理员完整维护字典。
3. 字典高频读取支持Redis缓存，降低数据库查询压力。

## 14 【扩展示例】C端/管理员全层完全隔离开发模板
> 文档路径：`docs/extend-example-full-isolation.md`

### 隔离规则（最高隔离模式）
1. **Controller、Service、Model、FormRequest 请求校验类全部不复用**；
2. 仅共享数据库物理表与迁移脚本；两套Model使用`$table`指向同一张物理数据表，各自定义scope、fillable、关联；
3. Service禁止读取`auth()/request()`，身份ID全部由控制器传入，支持单元测试；
4. 路由分组、守卫、中间件物理隔离；禁止在同一个类内部`if-else`判断身份分支执行业务；
5. 适用场景：C端业务、后台管理业务未来迭代方向差异巨大，需要最大限度解耦，修改一端完全不污染另一端。

## 15 🟢骨架内置增强能力
> 直接写入项目代码，默认注册中间件别名。

### 15.1 幂等防重复提交中间件 `IdempotencyRequest`
- 生效范围：POST/PUT/DELETE写操作；GET查询直接跳过。
- 前端传递请求头`X-Idempotency-Key`（UUID/v4）。
- Redis存储幂等记录，设置TTL；相同key短时间重复请求，直接返回上次结果，不重复执行业务。
- 中间件别名：`idempotency`。

### 15.2 响应字段脱敏中间件 `ResponseDesensitize`
- 配置需要脱敏字段：`mobile、id_card`。
- 输出JSON自动脱敏；**数据库存储原始明文**；管理员导出接口可以设置`$request->header('Skip-Desensitize:1')`跳过脱敏。
- 中间件别名：`response.desensitize`。

### 15.3 IP黑名单中间件 `IpBlacklist`
1. 读取`ip_blacklist`表 + Redis缓存；支持永久封禁/过期时间封禁。
2. 请求优先拦截黑名单IP，直接返回403。
3. 后台提供接口：新增、解除IP封禁。
- 中间件别名：`ip.blacklist`，放在中间件最外层优先执行。

### 15.4 慢查询日志监听
监听Eloquent查询，SQL执行超过**500ms**记录warning日志；开发环境提示，生产仅打日志不阻断业务，提早发现缺少索引、全表扫描问题。

### 15.5 软删除回收站 & 恢复接口模板
管理员统一回收站接口：查询已软删除数据、单条恢复、批量恢复；C端无权访问回收站。

### 15.6 增强审计日志
`admin_operation_logs`增加`cost_ms`请求耗时、`is_high_risk`高危标记；高危操作（改密码、角色变更、踢下线）自动标记。

### 15.7 编码约束补充
1. 使用PHP BackedEnum枚举，代替魔术数字/字符串（消息类型、状态码）。
2. 禁止`Model::all()`，大表必须分页。
3. 禁止N+1查询，强制`with()`预加载。
4. 多表写操作必须使用`DB::transaction()`数据库事务。
5. **对外接口禁止直接暴露自增ID**；业务对外使用雪花/snow_id。

## 16 🟡可选扩展模块（文档+代码片段，按需复制，不默认集成）
> 存放路径：`docs/extensions/*.md`
1. **对象存储文件服务**：本地/OSS/COS统一FileService，文件记录表，图片处理。
2. **短信验证码模块**：验证码限流、过期校验。
3. **图形滑块验证码**：高危接口开关控制。
4. **Excel异步队列导出**：task_id轮询查询任务状态，禁止同步大导出。
5. **Excel分块导入**，队列处理大文件。
6. **Feature-Toggle灰度开关**：功能灰度放量。
7. **system_configs系统配置表**：业务配置数据库存储，Redis缓存；`.env`只存环境信息。
8. **docker-compose.yml开发编排模板**。

## 17 队列、定时任务、日志体系
1. Redis驱动队列：处理耗时任务，消息推送、Excel导出、异步通知；supervisor守护worker进程。
2. 计划任务：清理临时文件、归档历史消息、清理过期Redis数据、清理过期IP黑名单。
3. **管理员操作日志**：中间件自动记录管理员增删改操作；记录操作人、IP、请求参数、耗时、高危标记。
4. 健康检查接口：`GET /api/health`，监控探测数据库、Redis连通状态，用于监控告警。
5. 日志按天切割，敏感密码信息禁止写入日志。

## 18 目录结构
```
laravel13-api-skeleton/
├── backend-laravel13/                 # Laravel13后端
│   ├── app/
│   │   ├── Helpers/                   # message()、common.php公共工具
│   │   ├── Http/
│   │   │   ├── Controllers/Api/       # API控制器集合（User/Admin子目录）
│   │   │   ├── Middleware/            # 全部自定义中间件（新增：幂等、脱敏、IP黑名单）
│   │   │   └── Requests/             # FormRequest表单校验类（User/Admin子目录）
│   │   ├── Models/                    # 业务模型（User/Admin子目录）
│   │   └── Services/                  # 业务服务层（User/Admin子目录）
│   ├── database/
│   │   ├── migrations/                # 全部数据表迁移（含ip_blacklist）
│   │   └── seeders/                   # 初始化种子(超级管理员)
│   ├── lang/                          # 多语言包 zh-CN / en
│   ├── openapi/                       # openapi.yaml接口文档
│   ├── routes/
│   │   ├── api.php                    # API路由
│   │   ├── channels.php               # Reverb WebSocket频道鉴权
│   │   └── console.php
│   ├── tests/Feature/Api/             # Pest自动化测试用例
│   ├── config/                        # auth/jwt/database/cors配置
│   └── .env.example
├── frontend-vue3/                     # Vue3管理后台前端模板
├── frontend-uniapp/                   # uni-app C端多端模板
└── docs/
    ├── deploy.md                      # 部署文档
    ├── dev-guide.md                   # 二开开发规范
    ├── business-rule.md               # 业务规则说明
    ├── test-case.md                   # 手工测试用例
    ├── extend-example-full-isolation.md # C端管理员全隔离扩展示例
    └── extensions/                    # 🟡可选扩展片段目录
```

## 19 部署方案&Docker编排&上线检查清单
1. 环境准备：PHP8.3、MySQL8.0、Redis6.0+，安装`php-redis`扩展。
2. 拉取代码，执行`composer install`。
3. 复制`.env.example`为`.env`，配置数据库、Redis、JWT密钥。
4. 执行：
```bash
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed --class=AdminSeeder
php artisan config:clear
```
5. 进程托管（Supervisor）
    - Reverb WebSocket服务：`php artisan reverb:start`
    - 队列Worker：`php artisan queue:work`
    - 计划任务：`php artisan schedule:work`
6. Nginx配置：反向代理、SSL、WebSocket代理、透传真实客户端IP、限制请求体大小。
7. 生产设置`APP_DEBUG=false`；Redis设置密码，禁止公网暴露6379端口。
8. 多实例部署：**全部实例指向同一个Redis服务**，限流、JWT黑名单、菜单缓存、多端登录状态全局生效。
9. 备份策略：MySQL定时备份；Redis开启RDB/AOF持久化；备份异地存储。

### 📋上线安全检查清单（生产上线必核对）
- [ ] `APP_DEBUG=false`
- [ ] Redis设置密码，防火墙禁止外网访问6379
- [ ] Nginx配置`X-Forwarded-For`，Laravel信任代理，拿到真实客户端IP
- [ ] 上传目录禁止PHP脚本执行权限
- [ ] 数据库账号最小权限，不使用root账号业务访问
- [ ] JWT_SECRET、数据库密码、Redis密码禁止硬编码代码，全部放.env
- [ ] 关键中间件已注册：`ip.blacklist`、`api.composite.rate`、`lang`
- [ ] Supervisor全部后台进程正常运行（reverb、queue、schedule）
- [ ] 日志按天切割，磁盘告警配置

> docker-compose模板：`docs/extensions/docker-compose.md`（可选扩展）

## 20 二开开发规范
> 完整规范查看 `docs/dev-guide.md`
1. 控制器仅处理入参、调用Service、返回`message()`；业务逻辑全部下沉Service层；
2. 所有请求入参使用FormRequest表单验证；
3. 新增写接口必须评估是否挂载`idempotency`幂等中间件；
4. 新增接口必须挂载限流、多语言中间件；
5. 提示文案优先使用多语言key，禁止硬编码中文；
6. 管理员接口统一放在`/api/admin/*`分组，使用守卫`auth:admin-api`；
7. 数据表变更必须编写迁移文件，禁止直接修改数据库；
8. 修改菜单、角色权限业务，必须调用对应缓存清理方法；
9. 列表接口统一`page、page_size`，限制最大page_size，防止超大分页；
10. 业务模型统一使用软删除`SoftDeletes`，尽量避免物理删除；
11. 多表写操作必须使用数据库事务；
12. 禁止`Model::all()`；禁止N+1查询，使用`with()`预加载；
13. 对外接口**禁止直接暴露自增ID**，使用业务雪花ID；
14. 状态、类型使用BackedEnum枚举，杜绝魔术数字；
15. 重要接口补充Pest测试，同步更新openapi.yaml；
16. 可选最高隔离模式：新增业务模块，**Controller/Service/Model/Request全部分两套不复用，仅共享迁移脚本**。

## 21 测试方案

### 21.1 Pest自动化测试覆盖场景
- 普通用户、管理员登录成功/失败。
- Token越权访问测试（普通用户访问管理员接口返回401）。
- 接口限流触发返回429。
- 未登录访问鉴权接口返回401。
- 普通用户多端登录、超过3端自动踢设备；后台强制踢用户下线。
- RBAC权限越权测试；动态菜单缓存读写与清理。
- WebSocket消息发送接收；商品、字典接口正常返回。
- 扩展示例收藏模块：越权访问、管理员操作。
- 🟢新增：幂等重复提交、IP黑名单拦截、脱敏输出、回收站恢复。

执行命令
```bash
./vendor/bin/pest
```

### 21.2 手工测试用例 docs/test-case.md
登录登出、token刷新、多语言切换、限流、XSS输入、黑名单token失效、菜单缓存、多端登录踢下线、消息推送、文件上传安全、幂等提交、IP黑名单。

### 21.3 OpenAPI文档测试
将`openapi.yaml`导入Apifox / Swagger-UI进行接口调试。

## 22 风险点与规避措施
|风险点|规避措施|
|---|---|
|浏览器指纹被篡改绕过限流|指纹仅辅助；高危接口叠加验证码；登录接口严格限流|
|多实例部署限流/缓存计数错乱|生产强制Redis；禁止file缓存|
|Token跨守卫越权访问|两套独立守卫，路由中间件严格隔离；JWT payload携带guard标识|
|Redis公网暴露被攻击|设置Redis密码；防火墙限制外网访问6379|
|Nginx拿不到真实客户端IP，限流统计网关IP|Nginx配置`X-Forwarded-For`；Laravel配置trusted_proxies|
|JWT登出之后仍然可用|开启Redis黑名单；合理配置grace_period|
|生产泄露异常堆栈|`.env`设置`APP_DEBUG=false`，全局异常处理器统一message返回|
|菜单is_show隐藏之后可直接URL访问接口|is_show仅UI控制；接口权限依赖RBAC中间件校验|
|普通用户多端被踢之后旧token仍然访问接口|挂载`check.user.online`中间件校验token是否在线集合|
|大量管理员场景，清空全部菜单key性能差|改用版本号策略，避免Redis scan批量删除key|
|C端/管理员业务逻辑互相污染|需要高解耦业务，使用【全层完全隔离开发模板】|
|前端重复提交产生重复业务数据|写接口开启`idempotency`幂等中间件，传递`X-Idempotency-Key`|
|数据库自增ID对外暴露，被爬虫枚举遍历|对外接口使用雪花业务ID，禁止直接暴露自增主键id|
|大表单提交DOS攻击|Nginx+PHP限制请求体最大大小，超大请求返回413|
|大表查询缺少索引造成慢SQL|开启慢查询日志告警，Pest增加大表场景测试|
|大量同步导出Excel导致PHP超时内存溢出|使用🟡可选扩展异步队列导出|
|恶意IP持续扫描攻击|启用`ip.blacklist`IP黑名单中间件|

## 23 OpenAPI接口文档说明
项目内置`openapi/openapi.yaml`，定义：
1. 统一ApiResponse返回结构体；
2. Bearer JWT安全认证组件；
3. X-Client-Fingerprint限流请求头参数；
4. X-Idempotency-Key幂等请求头；
5. 全部业务接口（含收藏扩展示例、黑名单IP管理）；
6. 429、401、403、413等错误响应示例。

---

# V1.2-Full完整模块汇总
✅ 双表JWT认证（普通用户users /管理员admins）
✅ 统一message返回助手、i18n多语言
✅ Redis复合接口限流（IP+用户ID+浏览器指纹）
✅ RBAC管理员角色-权限系统
✅ 后台动态可配置菜单 + Redis菜单缓存机制
✅ ✨普通用户最多3端同时在线，后台管理员可强制踢下线
✅ Reverb WebSocket站内即时消息系统
✅ 商品、通用数据字典业务示例
✅ ✨【扩展示例】C端/管理员**Controller+Service+Model+Request全层完全隔离模板**
✅ 🟢【骨架内置增强】幂等防重复提交、响应脱敏、IP黑名单、慢查询告警、回收站软删除恢复、增强审计日志
✅ 全局异常处理器、管理员操作日志
✅ Redis队列、计划任务、健康检查监控接口
✅ Vue3管理后台模板、uni-app C端模板
✅ OpenAPI3接口文档
✅ Pest自动化测试 + 手工测试用例
✅ 完整部署文档、上线安全检查清单、运维风险清单
✅ 🟡【可选扩展】对象存储、短信验证码、异步导出导入、系统配置表、docker编排（仅文档片段）
