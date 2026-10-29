# Laravel13-API-Skeleton — System-Level Summary & Structure Guide

> Purpose: consolidate all project MDs (PROJECT_SPEC technical spec / SHARED_CONVENTIONS dev contract / DELIVERY handover / TEST_REPORT test report / docs/) with the current codebase into a single **system-level summary + structure guide**.
> Updated: 2026-10-29　Project root: `D:\wwwroot\ju_test02\laravel13-api-skeleton\`

---

## 1. Project Overview

A production-grade **Laravel 13 API skeleton** (V1.2-Full), one backend serving three clients:

| Client | Stack | Role |
|---|---|---|
| Backend `backend-laravel13` | PHP 8.3 + Laravel 13.38 + MySQL 5.7 + Redis 5.0 | API service (the single core) |
| Admin `frontend-vue3` | Vue3 + Vite + Pinia + Element Plus + vue-i18n | Admin console (RBAC) |
| C-end `frontend-uniapp` | uni-app (H5 / mini-program / App) | Regular users (PC desktop + H5 mobile) |

**Core capabilities**
- **Two-table two-guard JWT**: C-end `users` + `api` guard; admin `admins` + `admin-api` guard; tokens cannot cross guards.
- **Unified status codes**: HTTP always 200 + `body.code` (0/1/1001–1008/5000 + 2xxxx business codes); frontend judges success by `code===0`.
- **Action-level RBAC**: `{resource}:{action}` permission codes; each endpoint validated by exact code; admin-configurable permissions/roles.
- **Dynamic admin menu**: DB-stored + Redis-cached (TTL 7 days), admin menu management up to level 5, links to C-end menus.
- **i18n bilingual**: language-pack API + md5 cache validation, full zh-CN / en switching, dicts/permissions label_en.
- **Login image captcha**: GD-generated + Redis one-time (TTL 300s, consumed even on error), mandatory (1008).
- **Composite rate limiting**: IP + user ID + browser fingerprint, rules configurable in backend (`rate_limit_rules` table).
- **C-end multi-device (max 3)**: over 3 devices kicks oldest + adds to JWT blacklist; admin can force-kick all.
- **Admin single sign-on**: one valid session per admin; a new login kicks the old one.
- **Audit log driver-based**: default file (text JSON lines), optional Elasticsearch, **not stored in MySQL**.
- **Enhanced toolkit**: idempotency, response desensitization, IP blacklist, slow-query warning, recycle-bin soft-delete restore, health check.
- **Salted password hashing**: `users`/`admins` each add a 6-char random salt; stored as `Hash(plaintext+salt)`.
- **Code generator**: `make:module` one-click full module; `lang:sync` language sync.
- **Scale constraints**: snowflake ID exposed, pagination (page_size≤100), no N+1, complete indexes, cache-aside — architecture designed for 10M rows / 100k QPS.

**Validation baseline (2026-10-29)**: Pest **66 passed / 310 assertions**; `migrate:fresh --seed` 18 migrations + 8 seeders all OK; admin 22 modules + C-end PC/H5 browser-tested passed.

---

## 2. System Architecture

```
【Frontend layer】
├─ frontend-vue3 admin console (Vue3 + Element Plus + Pinia)
└─ frontend-uniapp C-end (PC desktop ≥768px / H5 mobile <768px)
        ↓ HTTP / HTTPS / WebSocket(WSS)
【Reverse proxy / gateway】Nginx (prod) / _cserver.py (C-end local 5174, incl. /api proxy)
        ↓
【Laravel 13 backend backend-laravel13】
├─ Middleware (global prepend): IpBlacklist, LangSwitch, HandleCors
│   Aliases: lang / ip.blacklist / api.composite.rate / admin.permission:{code}
│            / check.user.online / check.admin.online / idempotency / response.desensitize / admin.operation.log
├─ Controllers: Api/ (C-end) + Api/Admin/ (admin) + Locale/Captcha/CMenu
├─ Services: User/ (C-end) and Admin/ (admin), fully isolated
├─ Models: 14 models (HasSnowId + SoftDeletes + BackedEnum casts)
└─ Commons: message() helper + common.php + ApiExceptionHandler
        ↓
【Storage layer】
├─ MySQL (db ju_test): 18 business tables
└─ Redis: rate-limit / JWT blacklist / menu cache / multi-device sets / idempotency / IP blacklist / locale pack / captcha
```

**Two fully isolated access chains**
1. C-end user: `/api/*`, guard `api`, reads `users`.
2. Admin: `/api/admin/*`, guard `admin-api`, reads `admins`.
3. C-end public: `/api/health`, `/api/captcha`, `/api/locale/*`, `/api/products`, `/api/dicts/{type}`, `/api/menus` (no login).

---

## 3. Directory Structure

### 3.1 Project root `laravel13-api-skeleton/`

```
├─ PROJECT_SPEC.md        # Authoritative technical spec V1.2-Full
├─ SHARED_CONVENTIONS.md  # Single coordination contract (§1–§14)
├─ DELIVERY.md            # Handover notes (design changes + verification baseline + deploy)
├─ TEST_REPORT.md         # Browser real-world test report (§17 incl. 2026-10-29 multi-end tests)
├─ SYSTEM_SUMMARY.md      # This document (Chinese)
├─ SYSTEM_SUMMARY_EN.md   # This document (English)
├─ _cserver.py            # C-end local static server (hosts H5 build + /api proxy to 8000)
├─ backup_*.sql           # DB backups (mysqldump)
├─ backend-laravel13/     # Backend
├─ frontend-vue3/         # Admin
├─ frontend-uniapp/       # C-end
└─ docs/                  # Project docs
```

### 3.2 Backend `backend-laravel13/`

```
├─ app/
│  ├─ Base/                    # Five base classes: BaseController / BaseService / BaseRepository / BaseModel / BaseMiddleware
│  ├─ Console/Commands/        # BaseCommand / MakeModuleCommand / LangSyncCommand
│  ├─ Contracts/               # LangSyncTranslator contract
│  ├─ Enums/                   # StatusCode + ErrorCode + 8 business enums (BackedEnum)
│  ├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP always 200 + body.code)
│  ├─ Helpers/                 # ApiHelper::message() + common.php (snow_id/client_ip/fingerprint/mask_text)
│  ├─ Http/
│  │  ├─ Controllers/Api/      # C-end (User/) + admin (Admin/) + Locale/Captcha/CMenu/Health
│  │  ├─ Middleware/           # 10 custom middleware (incl. CheckAdminOnline)
│  │  └─ Requests/             # User/ + Admin/ FormRequests per module
│  ├─ Models/                  # 14 models + Traits/HasSnowId + Casts/SaltedPasswordCast
│  ├─ Services/                # User/ (C-end) + Admin/ (admin) isolated business layer
│  │  ├─ User/                 # AuthService / MultiDeviceService / UserService / ProductService / DictService / MessageService / FavoriteService / CMenuService / NoticesService
│  │  └─ Admin/                # AuthService / AdminService / UserService / RoleService / PermissionService / MenuService / ProductService / DictService / MessageService / FavoriteService / OperationLogService / RecycleBinService / IpBlacklistService / RateLimitRuleService / CMenuService / NoticesService
│  ├─ Support/                 # Snowflake / SaltHasher / CaptchaService / LangSync
│  └─ Providers/               # AppServiceProvider (slow-query >500ms listener)
├─ database/
│  ├─ migrations/              # 18 create_* (all fields English/Chinese comment + table $table->comment)
│  ├─ seeders/                 # Permission / Role / Admin / Menu / Demo / User / CMenu / RateLimitRule (idempotent upsert)
│  └─ factories/               # 6 factories
├─ lang/{zh-CN,en}/            # api.php (flat ~444 keys incl. ui.*) + notice.php
├─ config/                     # auth / jwt / cors / broadcasting / reverb / audit / lang_sync / app
├─ routes/                     # api.php (94+ routes) + channels.php + console.php (4 scheduled tasks)
├─ openapi/openapi.yaml        # 78 operations
├─ tests/Feature/Api/          # 17 Pest files
└─ docs/                       # 13 main docs + extensions/ 8 extension snippets
```

### 3.3 Admin `frontend-vue3/` (17 pages)

`src/views/`: `login` / `dashboard` / `user` / `product` / `dict` / `admin` / `role` / `menu` / `permission` / `message` / `operation-log` / `ip-blacklist` / `rate-limit` / `favorite` / `recycle-bin` / `c-menu` / `profile`
Others: `layout/index.vue` (sidebar + topbar refresh dropdown + user dropdown), `layout/MenuTree.vue` (custom accordion menu, level 5), `api/*` (axios wrapper), `i18n/index.js` (fallback + md5 cache), `store` (Pinia: user/menu), `router/index.js` (guard + /profile)

### 3.4 C-end `frontend-uniapp/` (8 pages)

`pages/`: `index/index` (home entry grid + products), `product/list`, `product/detail`, `dict/index`, `favorite/index`, `message/index`, `mine/index`, `login/index`
Others: `App.vue` (PC desktop media query: ≥768px top nav + 1200px centered + hide tabBar), `index.html` (PC top nav bilingual), `common/i18n.js` (self-built reactive i18n + md5 cache), `common/request.js`, `static/`

### 3.5 Docs `docs/`

Main: `deploy.md` / `dev-guide.md` / `business-rule.md` / `test-case.md` / `demo-accounts.md` / `codegen.md` / `i18n-sync.md` / `测试与改进.md` / `extend-example-full-isolation.md`
Extensions: `extensions/{object-storage, sms, captcha, async-export, chunk-import, feature-toggle, system-configs, docker-compose}.md`

---

## 4. Database Structure (18 business tables)

> All business tables have a `snow_id` (snowflake ID, exposed externally; auto-increment `id` hidden via model `$hidden`); `$table->comment('table - purpose/English')` added; soft-deletable tables include `deleted_at`.

| Table | Purpose | Key fields / constraints |
|---|---|---|
| `users` | C-end users | name/email(unique)/password/**salt(6)**/status; soft-delete; JWTSubject |
| `password_reset_tokens` | Password reset tokens | email PK/token/created_at (framework default) |
| `sessions` | Sessions | id/user_id/ip/user_agent/payload/last_activity (framework default) |
| `admins` | Admin accounts | username(unique)/nickname/email/password/**salt**/avatar/status; soft-delete; JWTSubject |
| `roles` | Roles (RBAC) | name/code(unique)/remark; soft-delete |
| `permissions` | Permissions (action-level) | name/code(unique)/group/**action**/**type(1 system/2 custom)**/**label_en** |
| `admin_role` | Admin-role pivot | admin_id+role_id composite PK |
| `role_permission` | Role-permission pivot | role_id+permission_id composite PK |
| `role_menu` | Role-menu pivot | role_id+menu_id composite PK |
| `admin_menus` | Dynamic admin menus | parent_id/title/**title_en**/path/component/icon/permission_code/sort/is_show/is_cache/is_external/external_url; soft-delete |
| `products` | Product sample | name/desc/price(10,2)/status; soft-delete; index(status,created_at)/(status,price) |
| `dicts` | Data dictionary | type/label/**label_en**/value/sort/status; index(type,status); hard-delete |
| `messages` | In-app messages | receiver_type/receiver_id/msg_type/title/content/extra(json)/is_read/is_pop; soft-delete |
| `user_favorites` | User favorites | user_id+product_id composite unique; soft-delete |
| `ip_blacklist` | IP blacklist | ip(unique)/remark/expire_at; hard-delete |
| `rate_limit_rules` | Rate-limit rules (admin-configurable) | rule_key(unique)/max_attempts/decay_seconds/enabled/remark |
| `notices` | Notices (make:module demo) | name/title/status/remark (generator demo module) |
| `c_menus` | C-end user menus (separate table) | parent_id/title/title_en/icon/path/url/sort/is_show (**only show/hide + parent-child + i18n, no permission code**) |

> Framework tables: `migrations`, `cache`, `jobs` (no table comment).
> Audit log is **not a table** (driver-based storage, see §8).

**Seed data (8 seeders, idempotent upsert)**
- PermissionSeeder: 46 permission codes (incl. action-level + 4 common)
- RoleSeeder: `super-admin` (all perms + all menus), `operator` (product/dict)
- AdminSeeder: admin/admin123, demo/demo123 (salted)
- MenuSeeder: 18 admin menus (paths aligned with frontend routes)
- DemoSeeder: 5 products + 6 dicts (status/sex) + order_status 4 items
- UserSeeder: user@example.com/password (salted)
- CMenuSeeder: 5 default C-end entries (products/dict/favorites/messages/mine)
- RateLimitRuleSeeder: 5 default rate-limit rules

---

## 5. Authentication & Identity

| Item | Implementation |
|---|---|
| JWT dependency | `php-open-source-saver/jwt-auth` v2.9.3 |
| Guards | `api` (provider users) / `admin-api` (provider admins), driver=jwt; payload carries `guard` claim |
| Issue | `JWTAuth::claims(['guard'])->fromUser($user)` on login; ttl=config('jwt.ttl') |
| Blacklist | logout/kicked tokens enter Redis blacklist, rejected even before expiry |
| Cross-guard isolation | two independent guards + route isolation; cross-guard = 401 (1002) |
| **Salted password** | `salt varchar(6)` on users/admins; `SaltedPasswordCast` writes `Hash(plaintext+salt)`; 4 checks use `SaltHasher::check`; Seeders/Factory idempotent salt upgrade |
| **Admin SSO** | `CheckAdminOnline`: login `Cache::put('admin_online:{id}', token)`, new login overwrites, old token → 401 → frontend auto-redirect to login |
| **C-end multi-device (3)** | `MultiDeviceService`: login writes `user:token:list:{id}`; >3 `array_shift` kicks oldest + `invalidate` to blacklist; admin can force-kick all |
| Login captcha | `GET /api/captcha` first (id+image) → login with `captcha_id/captcha_code`; Redis `captcha:{id}` TTL300 one-time (1008) |

**Login endpoints**: `POST /api/login` (C-end), `POST /api/admin/login` (admin); refresh `/api/refresh`, `/api/admin/refresh`; logout `/api/logout`, `/api/admin/logout`.

---

## 6. Permissions & Menus (RBAC + Dynamic Menu)

### 6.1 Action-level RBAC
- Permission code `{resource}:{action}`: four base actions `list/create/update/delete`, custom actions unlimited (export/audit/kick…).
- Endpoints validated by `admin.permission:{code}` **exact code**; "can list ≠ can modify".
- Super-admin (super-admin role) has all permissions.
- Permission cache `admin:permissions:{adminId}` (Redis, TTL 600, **plain array** to avoid Collection unserialize crash); cleared on permission/role change.

### 6.2 Dynamic menus
- Admin menu page manages tree (create/edit/sort/show/hide/set parent), up to level 5.
- Cache `admin_menu:tree:{adminId}` (TTL 7 days); `my-tree` uses cache, `all-tree` reads DB.
- Menu change → clear all admin menu caches; role-menu change → clear that role's admins; admin-role change → clear only that admin.
- `is_show=false` is UI-only; **interface access relies on RBAC**.
- **buildTree recurses by snow_id** (fixed M2, 2026-10-29, avoids orphan nodes); menu title bilingual object `{zh-CN, en}`.

### 6.3 Role assignment
- **Assign permissions**: two-section checkbox — "Common permissions" (group=common, 4) + "Other permissions" (grouped by module), not a dropdown tree.
- **Assign menus**: menu-tree checkbox, submits `menu_ids: snow_id[]`, echo via `nextTick + setCheckedKeys`.

### 6.4 C-end menu linkage
- Separate `c_menus` table + public bilingual `GET /api/menus` (no login).
- Admin `/api/admin/c-menus` CRUD (only `auth:admin-api` login guard, no action RBAC).
- Admin create/edit/hide/delete → cache `c_menu:list` invalidated → C-end home entry grid updates in real time.

---

## 7. Unified API Contract

### 7.1 Status codes (HTTP always 200 + body.code)

| code | Meaning | Mapped HTTP semantics |
|---|---|---|
| 0 | Success | 200 |
| 1 | Generic error | 400 |
| 1001 | Invalid parameter | 400 |
| 1002 | Unauthenticated / session expired (token invalid/expired/kicked) | 401 |
| 1003 | No permission (RBAC) | 403 |
| 1004 | Resource not found | 404 |
| 1005 | Validation failed (data=errors) | 422 |
| 1006 | Rate limited | 429 |
| 1007 | Conflict / duplicate (idempotency, duplicate favorite) | 409 |
| 1008 | Captcha invalid / expired | 422 |
| 5000 | Internal server error | 500 |

> 2xxxx and above are business codes (registered in docs). `ApiExceptionHandler` maps all; production sets APP_DEBUG=false (no stack trace leak).

### 7.2 Response shape
Success: `{code:0, msg:"Operation success", data:{...}}`; failure: `{code:1005, msg:"Validation failed", data:errors}`. Frontend axios/uni judges by `body.code===0`.

### 7.3 Endpoint groups (routes/api.php, 94+)

**Public**: `GET /api/health`, `GET /api/captcha`, `GET /api/locale/{lang?}`, `/api/locale/{lang}/meta`, `GET /api/products`, `GET /api/products/{snow_id}`, `GET /api/dicts/{type}`, `GET /api/menus`, `GET /api/notices`

**C-end (auth:api + check.user.online)**: `POST /api/login|refresh|logout`, `GET/PUT /api/me`, `PUT /api/me/password`, `GET /api/messages*`, `POST/GET/DELETE /api/favorites*`

**Admin (auth:admin-api + admin.permission:{code})**: login/logout, `me`, menus (my-tree/all-tree/CRUD), roles (CRUD + assignPermissions/assignMenus), admins (CRUD + password/status), users (list/show/status/online/kick), product/dict/message/favorite CRUD, operation logs, IP blacklist, rate-limit rules, recycle bin, C-end menus, permission management (store/update/destroy), profile, `POST /api/admin/cache/flush`

---

## 8. Core Mechanisms

### 8.1 i18n
- Locale packs `lang/{zh-CN,en}/` flat (`api.*` prefix + `ui.*` 156 keys + `notice.*`), all calls `__()` / `message()` use `api.` prefix.
- `GET /api/locale/{lang}` returns `{lang, md5, items}` (recursively flattened, Redis cache TTL 86400); `/meta` lightweight md5 check.
- **Stable md5**: `ksort + json_encode(JSON_UNESCAPED_UNICODE|SLASHES)`.
- Frontend: local cache `{md5, items}` + meta compare (same → use local, different → pull full); **deepMerge** (remote only overrides same-name leaves, never replaces fallback subtree); refresh on foreground / button.
- Admin locale management: `GET /api/admin/locale/*`, `PUT .../{lang}` (only override existing keys), `POST /api/admin/locale/refresh` (permission admin:i18n:*).
- dicts/permissions bilingual label: API returns `{zh-CN, en}` object, frontend picks by locale.

### 8.2 Audit log (driver-based, not in MySQL)
- `OperationLogService` by driver: **file** (default, `storage/logs/audit/audit-YYYY-MM-DD.log` JSON lines, 10 fields) / **elasticsearch** (optional, AUDIT_LOG_DRIVER switch, falls back to file if unreachable).
- `AdminOperationLog` middleware auto-records url/method/ip/params/response/cost_ms/is_high_risk; password fields masked `***`.
- Admin operation-log endpoint reads from files; 90-day retention daily cleanup; **login actions intentionally not logged**.

### 8.3 Rate limiting (admin-configurable)
- `rate_limit_rules` table stores rules (rule_key/max_attempts/decay_seconds/enabled); `RateLimitRuleService::resolve` matches at runtime (path exact → guard global → fallback 60/60).
- key: `composite_rate:{path}:{ip}:uid_{id}` or `fp_{fingerprint}`; Redis storage, shared across instances.
- 5 defaults: `api:global 60/60`, `admin:global 120/60`, `api/login 5/60`, `api/admin/login 5/60`, `api/products 120/60`.
- Login anti-brute-force: 1006 from the 6th consecutive attempt.

### 8.4 Other mechanisms
- **Unified status codes**: see §7.1.
- **Snowflake ID**: `Snowflake` generator, written via `HasSnowId` event, always exposed as snow_id.
- **Soft delete + recycle bin**: products/users/admins/menus/roles/messages/user_favorites recoverable; recycle-bin endpoints by resource whitelist (view / single restore / batch restore).
- **Idempotency**: POST/PUT/DELETE + `X-Idempotency-Key`, Redis TTL 600, duplicate returns last result.
- **Response desensitization**: `mobile/id_card` masked, `Skip-Desensitize:1` header bypasses.
- **IP blacklist**: `ip_blacklist` table + Redis cache, blocks when hit and not expired (403).
- **Slow-query warning**: Eloquent SQL >500ms logs warning.
- **Health check**: `GET /api/health` probes app/db/redis/cache.
- **Scheduled tasks** (4): clean expired IP blacklist / archive 30-day-read messages / sweep idempotency cache / clean expired audit logs.
- **Reverb broadcast** (code-ready, not deployed): private-user.{id} / private-admin.{id} / public-system-announce; messages persisted in MySQL + offline pull.
- **Code generator**: `make:module {Name}` one-click generates five layers + migration (with comments + indexes) + routes + bilingual lang keys.

---

## 9. Frontend Structure

### 9.1 Admin frontend-vue3 (22 module pages)

| Page | Function |
|---|---|
| `/login` | Login (captcha + language selector) |
| `/dashboard` | Dashboard (4 stat cards + system health, bilingual computed live refresh) |
| `/user` | User management (list/status/online/kick) |
| `/product` | Product management (CRUD) |
| `/recycle-bin` | Recycle bin (view / batch restore) |
| `/dict` | Dictionary management (CRUD, label/label_en) |
| `/admin` | Admin management (create incl. confirm password / role link / reset pwd / enable / delete) |
| `/role` | Role management (CRUD + assign permissions [common+other] + assign menus) |
| `/menu` | Menu management (tree + new child + el-tree-select parent change) |
| `/permission` | Permission management (CRUD, label_en, select/custom code/group/action) |
| `/message` | Message center (list + send) |
| `/operation-log` | Operation logs (100 audits + high-risk marks) |
| `/ip-blacklist` | IP blacklist (block/unblock) |
| `/rate-limit` | Rate-limit rules (CRUD) |
| `/favorite` | Favorites management (search) |
| `/c-menu` | C-end menu management (show/hide switch + linkage) |
| `/profile` | Profile (user-dropdown entry) |

Layout: `layout/index.vue` topbar "Refresh" dropdown (locale pack / sidebar menu / cache) + user dropdown (Profile / Logout); `MenuTree.vue` custom accordion sidebar (open one at a time, level 5, bilingual, collapsed narrow display).

### 9.2 C-end frontend-uniapp

| Page | Function |
|---|---|
| `/pages/index/index` | Home (entry grid [from /api/menus] + products; PC grid 4 cols / mobile single col) |
| `/pages/product/list` | Product list |
| `/pages/product/detail` | Product detail (favorite button) |
| `/pages/dict/index` | Dictionary sample (order_status 4 items, label by locale) |
| `/pages/favorite/index` | My favorites |
| `/pages/message/index` | Message center (unread/mark all read) |
| `/pages/mine/index` | Profile (language switch / refresh pack / logout) |
| `/pages/login/index` | Login (captcha + language) |

**PC desktop (≥768px)**: `index.html` top nav (Home/Products/Messages/Favorites/Mine/Login, data-i18n bilingual) + `App.vue` 1200px centered + hide bottom tabBar + home product grid 4 cols / entry 6 cols; **mobile (<768px)** keeps bottom tabBar + single column.

---

## 10. Testing & Validation

### 10.1 Automated tests (Pest)
- **66 passed / 310 assertions**, 17 test classes: Auth(8) / AdminPermissionCache / AdminSsoTest(1) / Captcha(4) / CMenu(3) / Desensitize / Favorite / GuardIsolation / Idempotency / IpBlacklist / Locale(12) / Message / MultiDevice / ProductDict / RateLimit / RbacAndMenu / Recycle.

### 10.2 Browser real-world tests (TEST_REPORT §17, 2026-10-29)
- **Admin 22 modules all passed**; 3 fixes this round: M1 role code rebuild after soft-delete rejected / M2 menu build-tree orphan nodes (buildTree key → snow_id) / M3 menu edit dialog title [object Object].
- **C-end PC (1280×900) + H5 (375×812) all passed**; 4 fixes this round: C4 favorite/message page blank (computed:{t} → methods), C5 dict page top blank, C6 dict missing order_status data + unwrap + bilingual label interpolation, C7 proxy Content-Length double header causing XHR hang.
- **C-end multi-device (3) verified per design** (over 3 kicks oldest + blacklist, others unaffected).

### 10.3 Historical fixes closed (TEST_REPORT §4, 25 items)
Unified status codes / 1002 unauthenticated / audit not in DB / snow_id seeding / plain-array permission cache / menu path alignment / bilingual label / permission action column / role assignment two-section / sidebar accordion / auto-redirect to login / menu level 5 / el-switch boolean / C-end PC compatibility / salted passwords / admin SSO / migration consolidation + table comments — all closed and re-verified.

---

## 11. Environment & Accounts

| Item | Value |
|---|---|
| PHP | `D:\BtSoft\php\83\php.exe` (8.3.8) |
| MySQL | 127.0.0.1:3306, db ju_test, ju_test / mH4ZY4WJAJAwMYh8 (5.7.38) |
| Redis | 127.0.0.1:6379 no password (5.0.10), CACHE_STORE=redis |
| HBuilderX | `D:\Program Files (x86)\HBuilderX\HBuilderX.exe`; node `...\plugins\node\node.exe`; cli `...\plugins\uniapp-cli-vite` |
| Demo accounts | Admin admin/admin123 (super-admin), demo/demo123 (operator); C-end user@example.com/password |

---

## 12. Build & Run

```bash
# Backend
cd backend-laravel13
composer install
cp .env.example .env   # configure MySQL/Redis/AUDIT_LOG_DRIVER
php artisan key:generate && php artisan jwt:secret --force
php artisan migrate:fresh --seed      # dev rebuild (or migrate --seed)
php artisan serve --host=127.0.0.1 --port=8000
# Test
vendor/bin/pest

# Admin
cd ../frontend-vue3 && npm install && npm run build   # dev: npm run dev (5173)

# C-end (HBuilderX build H5)
cd ../frontend-uniapp
# Use HBuilderX bundled node + @dcloudio/vite-plugin-uni CLI:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp absolute path>
#   node node_modules\@dcloudio\vite-plugin-uni\bin\uni.js build --platform h5
# Output frontend-uniapp/dist/build/h5; _cserver.py hosts + /api proxy 8000 → http://127.0.0.1:5174

# Production daemons
php artisan queue:work / schedule:work / reverb:start (supervisor)
```

**Current dev services**: backend 8000 / admin 5173 / C-end 5174 (_cserver.py) all running; open `http://127.0.0.1:5174` to review the C-end PC version.

---

## 13. Remaining Items & TODOs (disclosed)

| Item | Status | Note |
|---|---|---|
| Elasticsearch audit driver | Not live-tested | code complete + file fallback; verify with AUDIT_LOG_DRIVER after deploy |
| Reverb broadcast | Not live end-to-end | code/config ready; needs Reverb deploy to integrate |
| 100k QPS load test | Not run | design satisfied; needs deployment per docs/deploy.md then k6/ab |
| Admin message-center leftover test message "多端测试公告" | Leftover | from msg-flow test; can be cleaned |
| Permission detail endpoint `GET /api/admin/permissions/{id}` | Known minor, unfixed | returns 5000; list/update/delete fine, no functional impact |
| Admin language persistence across windows | To decide | switches instantly but new window resets to Chinese; store locale choice in frontend if needed |
| C-end sub-page deep PC layout | As needed | detail/login/mine inherit full-width + 1200px centered, usable; add per-page media query if desktop polish required |
| Mini-program / App | To test on device | H5 tested; mini-program/App need HBuilderX packaging + real-device verification |
