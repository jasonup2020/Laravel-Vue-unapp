# 手工测试用例（test-case.md）

> 环境：后端 `backend-laravel13\`，本地 `php artisan serve --port=8000`，Redis/MySQL 已起。
> 通用约定：所有响应均为 `{success, code, msg, data}`；未特别说明时 Authorization 头为 `Bearer <token>`。

---

## TC-01 C 端登录成功

- **前置条件**：`users` 表有一条 status=1 的账号 `user01@test.com` / `Pass1234`。
- **操作**：`POST /api/login` body `{"email":"user01@test.com","password":"Pass1234"}`，带 `X-Client-Fingerprint: fp-001`。
- **预期**：200，`success=true`，`data.token` 非空，`data.expires_in=3600`；Redis 存在 `user:token:list:{userId}` 且包含该 token。

## TC-02 C 端登录失败（密码错）

- **前置条件**：同 TC-01。
- **操作**：`POST /api/login` body 密码故意错。
- **预期**：HTTP 200 + body.code=1002，`msg` 对应 `login.invalid_credentials`（"账号或密码错误"），不返回 token。

## TC-03 C 端账号被禁用

- **前置条件**：把某 users 行 status=0。
- **操作**：`POST /api/login` 用该账号正确密码。
- **预期**：HTTP 200 + body.code=1003，`msg` 对应 `login.disabled`（"账号已被禁用"）。

## TC-04 管理员登录成功

- **前置条件**：种子超管 `admin/admin123`。
- **操作**：`POST /api/admin/login` body `{"username":"admin","password":"admin123"}`。
- **预期**：200，返回 admin token；`GET /api/admin/me` 用该 token 可返回本人信息。

---

## TC-05 跨守卫越权（C 端 token 访问管理员接口）

- **前置条件**：持有 C 端 token。
- **操作**：`GET /api/admin/admins`，Authorization 用 C 端 token。
- **预期**：HTTP 200 + body.code=1002，不能进入业务逻辑；同理管理员 token 访问 `GET /api/me` 也 code=1002。

---

## TC-06 限流触发（body.code=1006）

- **前置条件**：登录接口 `api.composite.rate:5,60`。
- **操作**：60 秒内连续 `POST /api/login` 6 次（同一 IP + 同一 fingerprint）。
- **预期**：前 5 次 code=0，第 6 次起 HTTP 200 + body.code=1006，`msg=api.rate_limited`；响应头含 `X-RateLimit-Limit/Remaining`；60 秒后自动恢复。

## TC-07 不同接口计数隔离

- **前置条件**：同 TC-06。
- **操作**：`POST /api/login` 打满 5 次触发限流后，立即 `POST /api/admin/login`。
- **预期**：管理员登录不受 C 端登录限流影响（不同 path + 不同计数），仍可正常响应。

---

## TC-08 多端登录——第 4 端踢最早

- **前置条件**：同一 C 端账号。
- **操作**：依次在 4 个不同 fingerprint（fp-a/b/c/d）下登录成功。
- **预期**：4 次都返回 token；但 `user:token:list:{uid}` 内只保留 3 个 token，最早的 fp-a 对应 token 已被剔除并打入 JWT 黑名单；用 fp-a 的旧 token 访问 `GET /api/me` → body.code=1002。

## TC-09 后台踢用户下线

- **前置条件**：某用户在线 2~3 端；持有管理员 token（权限 `admin:user:kick`）。
- **操作**：`POST /api/admin/user/{userId}/kick`。
- **预期**：body.code=0 `device.kicked_all`；该用户全部 token 进黑名单；再用任一旧 token 请求 `GET /api/me` → body.code=1002 `api.account.kicked`（"账号已在其他设备登录"）。

---

## TC-10 RBAC 越权

- **前置条件**：`demo/demo123` 账号只挂 `operator` 角色（无 `admin:admin:list` 权限）。
- **操作**：`GET /api/admin/admins` 用 demo token。
- **预期**：body.code=1003 `api.forbidden`；而 `GET /api/admin/products`（operator 有 `admin:product:list`）正常 code=0。
- **旁路核对**：超管 admin 访问任意 admin 接口均 code=0（super-admin 旁路）。

---

## TC-11 菜单缓存命中与清理

- **前置条件**：管理员 A 登录后调 `GET /api/admin/menu/my-tree`；用 `redis-cli` 确认 `admin_menu:tree:{A的id}` 存在。
- **操作 1**：再调一次 `my-tree`，在 SQL 日志观察是否未查 DB（命中缓存）。
- **操作 2**：用另一管理员 B 调 `POST /api/admin/menu` 新增一个菜单。
- **操作 3**：A 再调 `my-tree`。
- **预期**：操作 1 命中缓存（无 DB 查询）；操作 2 后所有启用管理员的 `admin_menu:tree:*` 被清空；操作 3 重新查库并包含新增菜单。

## TC-12 角色菜单变更清理

- **前置条件**：角色 R 下有管理员 C、D。
- **操作**：`PUT /api/admin/roles/{R}/menus` 改绑菜单。
- **预期**：仅 C、D 的 `admin_menu:tree:*` 被清；其他管理员不受影响。

## TC-13 管理员角色变更清理

- **前置条件**：管理员 E。
- **操作**：`PUT /api/admin/admins/{E}/roles`（或改其角色绑定）。
- **预期**：仅 E 的菜单 key 与权限 key `admin:permissions:{E}` 被清。

---

## TC-14 消息收发

- **前置条件**：管理员持有 token；某普通用户 U 已登录并连上 Reverb 私有频道 `private-user.{uid}`。
- **操作 1**：`POST /api/admin/messages` body `{"receiver_type":"user","receiver_id":U的snow_id,"title":"测试","content":"hello","msg_type":"biz"}`。
- **操作 2**：U 端 `GET /api/messages`。
- **操作 3**：U 端 `GET /api/messages/unread-count` → `PUT /api/messages/read-all`。
- **预期**：操作 1 200 `message.sent`；U 端 WebSocket 实时收到推送；操作 2 列表含该条；未读数先 ≥1，全部已读后为 0。

## TC-15 离线消息拉取

- **前置条件**：U 离线时被管理员发了一条消息。
- **操作**：U 登录后 `GET /api/messages`。
- **预期**：历史消息仍在 MySQL 中可分页拉到，不因离线丢失。

---

## TC-16 幂等重复提交

- **前置条件**：某写接口（如 `POST /api/favorites`）挂 `idempotency`。
- **操作**：同一 `X-Idempotency-Key: uuid-123` 5 秒内连续 POST 两次，body 相同。
- **预期**：第一次 201/200 创建数据；第二次直接返回第一次的响应体（不重复创建）；DB 中收藏记录仅 1 条。

---

## TC-17 IP 黑名单（body.code=1003）

- **前置条件**：管理员调 `POST /api/admin/ip-blacklist` 把本机出口 IP（或 `client_ip()` 取到的 IP）加入，`expire_at=null`。
- **操作**：本机任意请求（如 `GET /api/health`）。
- **预期**：立即返回 body.code=1003 `api.ip.blocked`；`DELETE /api/admin/ip-blacklist/{id}` 解除后恢复。
- **过期用例**：`expire_at` 设为 1 分钟后，等待过期后请求应放行（缓存 300 秒刷新内可能仍命中，等缓存过期或手动 `Cache::forget('ip_blacklist:active')`）。

---

## TC-18 脱敏输出

- **前置条件**：用户资料里 mobile=`13812345678`。
- **操作**：`GET /api/me`（挂 `response.desensitize`）。
- **预期**：返回 mobile 为 `138****5678`；带请求头 `Skip-Desensitize:1` 再请求 → 返回明文 `13812345678`。DB 中始终是明文。

---

## TC-19 回收站恢复

- **前置条件**：某商品被软删除（`DELETE /api/admin/products/{id}`）。
- **操作 1**：`GET /api/admin/recycle-bin/products`。
- **操作 2**：`PUT /api/admin/recycle-bin/products/{snowId}/restore`。
- **操作 3**：`GET /api/products`。
- **预期**：操作 1 列表含该软删商品；操作 2 200 `recycle.restored`；操作 3 C 端商品列表重新出现该商品。
- **非法 resource**：`GET /api/admin/recycle-bin/foo` → body.code=1001 `api.recycle.resource_invalid`。

---

## TC-20 字典缓存

- **前置条件**：C 端 `GET /api/dicts/order_status` 调用后，Redis 存在 `dict:order_status`。
- **操作 1**：再次调用，DB 无查询。
- **操作 2**：管理员 `POST /api/admin/dicts` 新增一条 type=order_status。
- **操作 3**：C 端再次 `GET /api/dicts/order_status`。
- **预期**：操作 1 命中缓存；操作 2 后 `dict:order_status` 被清；操作 3 重新查库，包含新增项。

---

## TC-21 健康检查

- **操作**：`GET /api/health`。
- **预期**：body.code=0，`msg=api.health.ok`，data 含 db/redis 连通状态；停掉 Redis 后再调 → HTTP 500（健康检查例外，保留真实 HTTP 状态）或 body.code=5000。

---

## TC-22 对外 ID 隐藏

- **操作**：`GET /api/products`。
- **预期**：列表项字段含 `snow_id`，**不含自增 `id`**；`GET /api/products/{snowId}` 用 snow_id 可访问（code=0）；用自增 id 访问应 body.code=1004（路由按 snow_id 查询）。

---

## TC-23 动作级权限——有 list 无 update

- **前置条件**：角色 R 只分配 `admin:product:list`（未分配 `update`）；管理员 A 挂角色 R。
- **操作 1**：A 调 `GET /api/admin/products`。
- **操作 2**：A 调 `PUT /api/admin/products/{snowId}`（改商品）。
- **预期**：操作 1 code=0（能查）；操作 2 **body.code=1003 `api.forbidden`**（不能改）——"能查"不代表"能改"。

---

## TC-24 动作级权限——自定义动作分配后生效

- **前置条件**：后台「权限管理」新建自定义动作 `admin:product:export`（type=custom）；角色 R 当前未含该权限。
- **操作 1**：管理员 A（挂 R）调导出接口 `POST /api/admin/products/export`。
- **操作 2**：后台把 `admin:product:export` 分配给 R，保存（清 `admin:permissions:{A}` 缓存）。
- **操作 3**：A 再调导出接口。
- **预期**：操作 1 body.code=1003；操作 2 后缓存清；操作 3 code=0（自定义动作即时生效）。

---

## TC-25 限流规则后台改参数即时生效

- **前置条件**：默认 `login` 规则 5/60；Redis 存在 `rate_limit:rules` 缓存。
- **操作 1**：本机连续 `POST /api/login` 5 次后第 6 次应 429。
- **操作 2**：管理员 `PUT /api/admin/rate-limit-rules/{id}` 把 `login` 改为 `max_attempts=10,decay_seconds=60`，保存。
- **操作 3**：等 60 秒后，本机连续 `POST /api/login` 到第 8 次。
- **预期**：操作 1 第 6 次 body.code=1006；操作 2 后 `rate_limit:rules` 缓存被清；操作 3 第 8 次仍 code=0（新规则 10 次内放行），第 11 次才 body.code=1006。

---

## TC-26 审计日志文件生成

- **前置条件**：`AUDIT_LOG_DRIVER=file`；`storage/logs/audit/` 可写。
- **操作 1**：管理员调一次高危操作 `PUT /api/admin/me/password`（挂 `admin.operation.log:1`）。
- **操作 2**：查看 `storage/logs/audit/audit-YYYY-MM-DD.log` 当日文件。
- **操作 3**：管理员 `GET /api/admin/operation-logs` 分页查询。
- **预期**：操作 1 200；操作 2 文件新增一行 JSON，含 admin_id/url/method/ip/cost_ms/is_high_risk=1，密码字段为 `***`；操作 3 分页能读到该条记录；**MySQL 无 `admin_operation_logs` 表**（不落库）。

---

## TC-27 登录图形验证码

- **前置条件**：`GET /api/captcha` 可正常返回 `{captcha_id, image: base64PNG}`；Redis 存在 `captcha:{captcha_id}`。
- **操作 1**：直接 `POST /api/login`（不带 captcha_id/captcha_code）。
- **操作 2**：先 `GET /api/captcha` 拿 captcha_id，故意填错验证码 `captcha_code=0000` 登录。
- **操作 3**：用同一 captcha_id 再登录一次（无论对错）。
- **操作 4**：重新 `GET /api/captcha` 拿新 captcha_id，填正确验证码登录。
- **预期**：操作 1 body.code=1001（缺参数）；操作 2 body.code=1008 `api.captcha.invalid`；操作 3 仍 code=1008（一次性消费，旧 captcha_id 已失效）；操作 4 code=0 成功返回 token。
