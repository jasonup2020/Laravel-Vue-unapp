# 业务规则说明（business-rule.md）

> 本文汇总骨架的核心业务规则，作为前后端联调、测试、运维的口径基线。所有规则与 `SHARED_CONVENTIONS` 第 4/6 节、`PROJECT_SPEC` 第 4/8/9/10/11/12/15 节一致。

---

## 1. 双守卫隔离

| 项 | C 端普通用户 | B 端管理员 |
|---|---|---|
| 路由前缀 | `/api/*` | `/api/admin/*` |
| 守卫名 | `api` | `admin-api` |
| Provider / 模型 | `users` / `App\Models\User` | `admins` / `App\Models\Admin` |
| 数据表 | `users` | `admins` |
| 中间件 | `auth:api`（+ `check.user.online`） | `auth:admin-api` |
| JWT payload 标识 | `guard=api` | `guard=admin-api` |

隔离规则：

1. 两套 JWT 互相不通：普通用户 token 访问 `/api/admin/*` → HTTP 200 + body.code=1002；管理员 token 访问 C 端受鉴权接口 → 同样 code=1002。
2. 多端登录限制**只作用于 C 端 `api` 守卫**；管理员不受 3 端限制。
3. RBAC 权限体系**只用于管理员**；C 端普通用户不引入角色权限，保持轻量。
4. 不启用 CSRF 中间件（纯 API + Bearer Token）；CSRF 仅 Cookie-Session 表单场景需要。

---

## 2. RBAC 规则（管理员，动作级权限）

1. 实体：`roles`（角色）、`permissions`（权限）、`admin_role`（管理员↔角色）、`role_permission`（角色↔权限）、`role_menu`（角色↔菜单）。
2. **权限 code 约定：`{resource}:{action}`**（资源:动作）。
   - 四基本动作：`list`（查）/ `create`（增）/ `update`（改）/ `delete`（删）；
   - 自定义动作不限：`export`（导出）/ `audit`（审计查看）/ `kick`（踢人）/ `cancel` / `approve` / `import` 等，**后台可定义、数量不限**。
3. **permissions 表增强**：`action varchar(50)` 存动作标识、`type tinyint`（1=system 系统预置 / 2=custom 自定义）、`group` 按资源分组展示。
4. 一个管理员可挂多个角色，权限取**并集**；**"能查"不代表能"改/删"**——一个接口对应一个精确 `resource:action` code。
5. 权限中间件 `admin.permission:{code}` 按精确 code 校验当前管理员权限集合。
6. 权限缓存：`admin:permissions:{adminId}`，TTL 600 秒；权限/角色变更时清该管理员缓存。
7. **超管（super-admin）**：拥有全部权限（含所有自定义动作），走权限集合逻辑、**可后台调整**；权限变更实时生效（清 Redis 缓存）。
8. **菜单绑定**：菜单 `admin_menus.permission_code` 绑 **list 权限**作为侧边栏可见性代表；角色分配权限时按动作多选勾选。
9. **后台权限管理**：`GET/POST/PUT/DELETE /api/admin/permissions` 支持新建自定义动作权限、修改、删除（system 预置不可删）；操作后清 `admin:permissions:{adminId}` 全量缓存。
10. `is_show=false` 只是前端 UI 隐藏，**不能做接口权限控制**——接口访问必须靠 `admin.permission` 中间件。
11. 权限码全表（40 条）：`admin:product:{list,create,update,delete}`、`admin:dict:{list,create,update,delete}`、`admin:user:{list,update,kick}`、`admin:admin:{list,create,update,delete}`、`admin:role:{list,create,update,delete,assign}`、`admin:permission:{list,create,update,delete}`、`admin:menu:{list,create,update,delete}`、`admin:message:{list,create}`、`admin:favorite:list`、`admin:recycle:{list,restore}`、`admin:log:list`、`admin:ip:{list,create,delete}`、`admin:rate:{list,create,update,delete}`；自定义动作示例：`admin:product:export`、`admin:user:kick`。

---

## 3. 多端登录规则（仅 C 端普通用户）

1. 有效 token 集合存 Redis：key = `user:token:list:{userId}`，值为 JSON 数组，TTL = `config('jwt.refresh_ttl')*60`。
2. **最多 3 端同时在线**：
   - 登录成功把新 token 写入集合；
   - 若集合内 token 数量 > 3，**自动剔除最早登录的 token** 并打入 JWT 黑名单（`JWTAuth::setToken(旧)->invalidate()`）；
   - 被踢的旧设备下次请求即返回 body.code=1002。
3. 主动 logout：从集合移除当前 token 并 invalidate。
4. 刷新 token：旧 token 自动进黑名单，新 token 加入集合（集合大小不膨胀）。
5. 后台强制踢下线：
   - `POST /api/admin/user/{id}/kick`：清空该用户 Redis 集合，遍历全部 token 打入黑名单；
   - 该用户所有设备即使 JWT 未过期，下次访问 C 端鉴权接口即被 `check.user.online` 中间件拦下 → HTTP 200 + body.code=1002 `api.account.kicked`。
6. 在线查询：`GET /api/admin/user/{id}/online` 返回当前在线终端数量。

> 管理员账号（`admin-api` 守卫）不受 3 端限制，也无 `check.user.online` 校验。

---

## 4. 菜单缓存规则

| 项 | 值 |
|---|---|
| 缓存 key | `admin_menu:tree:{adminId}` |
| TTL | **604800 秒（7 天）**兜底 |
| 读取策略 | `Cache::remember`：先读 Redis，未命中查 MySQL（admin→roles→role_menu→menus），组装成 children 树（按 `sort` 排序、`parent_id` 递归）后回写 |
| `my-tree` 接口 | `GET /api/admin/menu/my-tree`，走缓存，供前端登录后渲染侧边栏 |
| `all-tree` 接口 | `GET /api/admin/menu/all-tree`，**直接读库不走缓存**，供后台菜单配置页 |
| super-admin | `my-tree` 直接返回全部菜单，不做角色过滤 |

**三类缓存清理触发（必须严格对齐）：**

1. **新增 / 编辑 / 删除菜单**（`POST/PUT/DELETE /api/admin/menu`）：遍历全部启用管理员 id，逐个 `Cache::forget("admin_menu:tree:{adminId}")`。
2. **修改角色绑定菜单**（`PUT /api/admin/roles/{id}/menus`）：清除该角色下全部管理员的菜单 key。
3. **修改管理员所属角色**：仅清除该管理员的菜单 key，同时清其权限缓存 `admin:permissions:{adminId}`。

> 规模提示：管理员数量很大时，触发①用「版本号策略」（`INCR admin_menu:version`，读缓存时拼版本号）代替遍历 forget，避免 `SCAN` 批量删 key。

---

## 5. 消息规则（Reverb WebSocket）

### 5.1 receiver_type 四类

| receiver_type | 含义 | receiver_id |
|---|---|---|
| `user` | 定向单个普通用户 | 用户 id |
| `admin` | 定向单个管理员 | 管理员 id |
| `all_user` | 全员普通用户广播 | null |
| `all_admin` | 全员管理员广播 | null |

频道：
- `private-user.{userId}`：普通用户私有频道
- `private-admin.{adminId}`：管理员私有频道
- `public-system-announce`：系统公共公告频道

### 5.2 消息状态

- `is_read`：0 未读 / 1 已读；`is_pop`：是否客户端弹窗。
- 未读数：`GET /api/messages/unread-count`。
- 单条已读：`PUT /api/messages/read/{id}`；全部已读：`PUT /api/messages/read-all`。
- 消息**持久化 MySQL**（`messages` 表，软删除）；客户端离线后再上线可通过 `GET /api/messages` 分页拉取历史。
- 生产建议定时归档历史消息（计划任务清理 90 天前已读消息）。

---

## 6. 回收站（软删除恢复）

1. 统一回收站接口（仅管理员）：
   - `GET /api/admin/recycle-bin/{resource}`：列出某资源的已软删数据；
   - `PUT /api/admin/recycle-bin/{resource}/{id}/restore`：单条恢复（id 传 snow_id）；
   - `PUT /api/admin/recycle-bin/{resource}/restore-batch`：批量恢复（ids=snow_id 列表）。
2. **resource 白名单**：`products, users, admins, admin_menus, roles, messages, user_favorites`。
3. 非法 resource → HTTP 200 + body.code=1001 `api.recycle.resource_invalid`。
4. `dicts`、`ip_blacklist` 表**无软删列**，不进回收站（字典物理删除）。
5. C 端无权访问回收站任何接口。

---

## 7. 其他内置规则

### 7.1 幂等防重复提交

- 中间件 `idempotency`，仅 POST/PUT/DELETE 生效（GET 直接跳过）。
- 前端请求头 `X-Idempotency-Key`（UUID v4）；缺失则直接放行。
- Redis key = `idempotency:{method}:{path}:{uid|ip}:{key}`，命中即原样缓存返回上次结果（status+body），不重复执行业务；TTL = `config('app.idempotency_ttl',600)`。

### 7.2 响应脱敏

- 中间件 `response.desensitize`，对响应 data 中 `config('app.desensitize_fields', ['mobile','id_card'])` 命名字段递归脱敏：
  - mobile → `138****1234`（前 3 后 4）
  - id_card → `110101********1234`（前 6 后 4）
- **数据库存原始明文**；管理员导出类接口加请求头 `Skip-Desensitize:1` 可跳过脱敏。

### 7.3 IP 黑名单

- 中间件 `ip.blacklist` 放在全局最外层；`Cache::remember('ip_blacklist:active', 300, ...)` 读 `ip_blacklist` 表。
- 当前 IP 命中且未过期 → 直接返回 HTTP 200 + body.code=1003 `api.ip.blocked`；过期行视为放行（由计划任务清理）。
- 后台接口：`POST /api/admin/ip-blacklist` 新增封禁（支持 `expire_at` 永久/定时）、`DELETE /api/admin/ip-blacklist/{id}` 解除。

### 7.4 数据字典缓存

- C 端 `GET /api/dicts/{type}` 读 Redis key `dict:{type}`（TTL 建议 3600）。
- 管理员写字典（增/改/删）后 `Cache::forget('dict:'.$type)`。

### 7.5 限流规则（后台可配置）

- 中间件 `api.composite.rate`（**路由上不写死参数**，运行时动态解析规则）。
- **规则存 `rate_limit_rules` 表**（后台 CRUD，即时生效）：
  - 字段：`rule_key`（路由/分组标识，如 `login` / `admin:login` / `api:global` / `admin:global` / `product:list`）、`max_attempts`、`decay_seconds`、`enabled`、`remark`；
  - 默认种子规则：`api:global` 60/60、`admin:global` 120/60、`login` 5/60、`admin:login` 5/60。
- **运行时解析顺序**（`RateLimitRuleService::resolve($routeName, $path)`）：
  1. `rule_key == routeName`（精确路由名）；
  2. `rule_key == path`；
  3. 按守卫取 `api:global` / `admin:global`；
  4. 无匹配兜底 **60/60**。
- 规则全量缓存 Redis key = `rate_limit:rules`，TTL 300 秒；后台写操作（增/改/删）后 `Cache::forget('rate_limit:rules')`，多实例共享即时生效。
- 计数 key = `composite_rate:{path}:{ip}:{uid_{id}|fp_{fingerprint}}`；登录用户按当前守卫取 uid（管理员与普通用户计数隔离）；游客用 `X-Client-Fingerprint` 头（缺失降级仅 IP）。
- 接口维度隔离：A 接口超限不影响 B 接口；超限返回 HTTP 200 + body.code=1006 `api.rate_limited`，响应头带 `X-RateLimit-Limit / Remaining`。
- 代码覆盖：路由仍可显式写 `api.composite.rate:10,60` 临时覆盖（保留能力，默认不用）。
- 管理接口：`GET/POST/PUT/DELETE /api/admin/rate-limit-rules`（权限码 `admin:rate:list/create/update/delete`）。

### 7.6 审计日志（驱动化存储，不落 MySQL）

- **设计变更**：审计日志为海量 append-only 数据，**不建 `admin_operation_logs` 表、不占业务库 IO**；改驱动化存储。
- 中间件 `admin.operation.log:{0|1}` 仍自动记录 url/method/ip/request_params/response_result/cost_ms/is_high_risk/admin_id，由 `App\Services\Admin\OperationLogService::record()` 按驱动分发。
- **驱动**（`AUDIT_LOG_DRIVER` 切换）：
  - `file`（默认，开箱即用）：写 `storage/logs/audit/audit-YYYY-MM-DD.log`，每条一行 JSON，含 `log_id`（毫秒时间戳+随机）、admin_id、url、method、ip、request_params、response_result、cost_ms、is_high_risk、created_at；
  - `elasticsearch`（可选，默认不启用）：Guzzle `_bulk` API 写索引 `audit_logs-YYYY.MM.DD`；ES 不可达自动回退 file 并 `Log::warning`。
- 请求体/响应 JSON 截断 2000 字符；密码字段打码 `***`。
- `is_high_risk=1` 的操作：改密码、角色变更、踢用户下线等。
- **读取**：`OperationLogService::read($filters, $page, $pageSize)` 供 `GET /api/admin/operation-logs` 分页（按 admin_id/日期过滤）；文件驱动按 created_at 倒序解析当日与历史文件。
- **清理**：文件驱动按 `AUDIT_RETENTION_DAYS`（默认 90 天）由 schedule 每日删除过期文件；ES 按索引滚动删除。

---

## 8. 统一响应状态码约定

> **定稿规则**：所有业务接口 **HTTP 状态码一律 200**，真实结果由响应体 `code` 表达；前端 axios 拦截器按 `body.code` 判断（0=成功），非 0 弹 `msg`，**不依赖 HTTP status**。
> 例外：基础设施/健康检查（`GET /api/health`、Nginx/网关层错误如 413/502）可保留真实 HTTP 状态。

### 8.1 响应结构

```json
{ "code": 0, "msg": "操作成功", "data": { } }
```

- `code:int`：0 成功，非 0 失败；
- `msg:string`：多语言翻译文案；
- `data:object|null`：业务数据；校验失败时为字段错误明细。

### 8.2 系统状态码表

| code | 含义 |
|---|---|
| 0 | 成功 |
| 1 | 通用失败 |
| 1001 | 参数错误（含非法 resource 等业务语义错） |
| 1002 | 未认证 / token 失效 / 被踢下线 / 跨守卫访问 |
| 1003 | 无权限（RBAC 不通过 / IP 被封） |
| 1004 | 资源不存在 |
| 1005 | 表单校验失败（data=字段错误） |
| 1006 | 限流 |
| 1007 | 冲突 / 重复提交（含幂等拦截） |
| 1008 | 验证码错误 / 过期（登录接口 captcha_id/captcha_code） |
| 5000 | 服务器内部错误（生产不暴露堆栈） |
| 2xxxx | 业务自定义码（在 dev-guide 业务码登记表登记） |

### 8.3 登录图形验证码流程

1. 前端先 `GET /api/captcha` → 返回 `{captcha_id, image: base64PNG}`。
2. `POST /api/login` 与 `POST /api/admin/login` body 必填 `captcha_id` + `captcha_code`。
3. Redis key = `captcha:{captcha_id}`，TTL 300 秒；**一次性消费**——无论对错都删除，防暴力试解。
4. 校验失败 / captcha_id 不存在 → HTTP 200 + body.code=1008 `api.captcha.invalid`。

### 8.4 前端协作约定

- **菜单 path 与 Vue Router path 完全一致**：Seeder 菜单 `path`（如 `/product`、`/user`、`/dict`）与前端路由 path 一字不差（**单数**，不用 `/products`）；改一侧必须同步另一侧。
- **`admin:permissions:{adminId}` 缓存必须为纯数组**：禁止缓存 Collection（Redis 反序列化崩溃）；写入前 `array_values(array_unique(...))` 强转纯数组。

### 8.5 前端拦截器约定

```js
axios.interceptors.response.use((res) => {
  const body = res.data;
  if (body.code === 0) return body.data;
  if (body.code === 1002) { /* 跳登录/刷新 token */ }
  Message.error(body.msg);
  return Promise.reject(body);
});
```

> 本文档其他章节出现的"401/403/429/400/404"等表述，均指 **HTTP 200 + body.code=对应值**（1002/1003/1006/1001/1004）。
