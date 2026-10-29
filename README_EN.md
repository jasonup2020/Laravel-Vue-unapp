# Laravel13 API Skeleton (English Full)

> Production-grade admin system skeleton · One backend, three clients
> Full documentation: project summary & structure · environment requirements · deployment

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Key Features](#2-key-features)
3. [Tech Stack](#3-tech-stack)
4. [Directory Structure](#4-directory-structure)
5. [Database Schema](#5-database-schema)
6. [Authentication & Permissions](#6-authentication--permissions)
7. [Unified API Contract](#7-unified-api-contract)
8. [Environment Requirements](#8-environment-requirements)
9. [Quick Start (Development)](#9-quick-start-development)
10. [Production Deployment](#10-production-deployment)
11. [Demo Accounts](#11-demo-accounts)
12. [Testing & Validation](#12-testing--validation)
13. [Related Documents](#13-related-documents)

---

## 1. Introduction

**Laravel13 API Skeleton** is a production-grade admin system skeleton built on PHP 8.3 + Laravel 13 + MySQL + Redis. One backend serves three clients — a Vue3 admin console and a uni-app user client (PC desktop + H5 mobile) — with true frontend/backend separation and multi-client consistency.

Authentication is secured with **dual-table dual-guard JWT**: regular users and admins are fully isolated; passwords in both `users` and `admins` are salted with a random 6-char salt then Hash-stored. Permissions use **action-level RBAC** (`resource:action` codes) with configurable permissions, roles and dynamic menus (up to level 5) that link to the C-end. The admin console enforces **single sign-on** (one session per account), while the C-end supports **up to 3 concurrent devices** — beyond that, the oldest is auto-kicked and blacklisted. Logins use image captchas and composite rate limiting (IP/user/fingerprint). All APIs return the unified `HTTP 200 + body.code` contract, and the whole site switches between **zh-CN and en**.

The system includes driver-based audit logs (not stored in MySQL), IP blacklist, idempotency, response desensitization, recycle-bin soft-delete restore, slow-query alerts, health checks and a code generator. Data uses snowflake IDs, pagination limits and complete indexes, architected for millions of rows and 100k QPS. Automated tests (Pest 66) and multi-client browser testing all pass — ready to use out of the box.

## 2. Key Features

| Category | Capability |
|---|---|
| Auth | Dual-table dual-guard JWT (C-end `users` / admin `admins`), cross-guard isolation, token blacklist |
| Permissions | Action-level RBAC `{resource}:{action}`, role-permission-menu tiers, dynamic menu (≤5 levels) |
| Security | Image captcha (Redis one-time), composite rate limit (IP/user/fingerprint), salted passwords, IP blacklist |
| Sessions | Admin single sign-on; C-end 3-device control (kick oldest + blacklist) |
| i18n | zh-CN / en full switching, bilingual label_en for dicts/permissions, md5 cache validation |
| Audit | Driver-based storage (file/Elasticsearch), **not in MySQL**, password masking |
| Toolkit | Idempotency, desensitization, recycle bin restore, slow-query alert, health check, code generator |
| Scale | Snowflake ID, pagination (page_size≤100), complete indexes, cache-aside |

## 3. Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| Storage | MySQL 5.7+/8.0 (db ju_test) · Redis 5.0+ (rate-limit/blacklist/cache/multi-device/locale/captcha) |
| Admin | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-end | uni-app (H5 / mini-program / App) · HBuilderX build · PC desktop + H5 mobile |

## 4. Directory Structure

### 4.1 Project root

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md   # Project docs (this series)
├─ PROJECT_SPEC.md        # Authoritative technical spec V1.2-Full
├─ SHARED_CONVENTIONS.md  # Single coordination dev contract
├─ DELIVERY.md            # Handover notes (change list + verification baseline)
├─ TEST_REPORT.md         # Browser test report (§17 multi-client tests)
├─ SYSTEM_SUMMARY.md      # System-level summary & structure (Chinese)
├─ SYSTEM_SUMMARY_EN.md   # System-level summary & structure (English)
├─ _cserver.py            # C-end local static server (hosts H5 + /api proxy to 8000)
├─ backup_*.sql           # DB backups
├─ backend-laravel13/     # Backend
├─ frontend-vue3/         # Admin
├─ frontend-uniapp/       # C-end
└─ docs/                  # deploy/dev-guide/business-rule/test-case/accounts/codegen/i18n-sync
```

### 4.2 Backend `backend-laravel13/`

```
app/
├─ Base/                    # Five base classes: BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator; StatusCode/ErrorCode/8 business enums
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP always 200 + body.code)
├─ Helpers/                 # message() helper + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-end) + Api/Admin/(admin) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 middleware (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # User/ + Admin/ FormRequests per module
├─ Models/                  # 14 models + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-end) + Admin/(admin) isolated business layer
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (slow-query >500ms listener)

database/
├─ migrations/              # 18 create_* (field bilingual comments + table $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (idempotent upsert)
└─ factories/               # 6 factories
lang/{zh-CN,en}/            # api.php (~444 keys incl. ui.*) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ routes) + channels.php + console.php (4 scheduled tasks)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 Pest files
docs/                       # 13 main docs + extensions/ 8 extension snippets
```

### 4.3 Admin `frontend-vue3/` (17 pages)

`src/views/`: login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
Others: `layout/index.vue` (topbar refresh dropdown + user dropdown), `layout/MenuTree.vue` (custom accordion sidebar, 5 levels), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-end `frontend-uniapp/` (8 pages)

`pages/`: index (home entry grid + products), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
Others: `App.vue` (PC desktop media query), `index.html` (PC top nav bilingual), `common/i18n.js`, `common/request.js`

## 5. Database Schema (18 business tables)

| Table | Purpose | Key constraints |
|---|---|---|
| `users` | C-end users | email unique, salt, soft-delete |
| `password_reset_tokens` / `sessions` | Framework tables | framework default |
| `admins` | Admin accounts | username unique, salt, soft-delete |
| `roles` | Roles | code unique, soft-delete |
| `permissions` | Permissions (action-level) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | Pivot tables | composite PK |
| `admin_menus` | Dynamic admin menus | parent_id, title_en, soft-delete |
| `products` | Product sample | index(status,created_at), (status,price), soft-delete |
| `dicts` | Data dictionary | index(type,status), label_en, hard-delete |
| `messages` | In-app messages | index(receiver_type,receiver_id,is_read), soft-delete |
| `user_favorites` | Favorites | (user_id,product_id) unique, soft-delete |
| `ip_blacklist` | IP blacklist | ip unique, hard-delete |
| `rate_limit_rules` | Rate-limit rules | rule_key unique |
| `notices` | Notices (generator demo) | — |
| `c_menus` | C-end user menus | parent_id, title_en, show/hide + parent-child + i18n only |

> All business tables have `snow_id` (exposed); `$table->comment('table - purpose/English')` added; audit log **has no table** (driver-based).

## 6. Authentication & Permissions

- **Dual-guard JWT**: `api` (users) / `admin-api` (admins), payload carries `guard` claim, cross-guard isolated.
- **Salted password**: `salt varchar(6)`, `SaltedPasswordCast` writes `Hash(plaintext+salt)`, 4 checks use `SaltHasher::check`.
- **Admin SSO**: `CheckAdminOnline`, login `Cache::put('admin_online:{id}', token)`, new login kicks old, old token 401 → auto-redirect to login.
- **C-end 3 devices**: `MultiDeviceService`, >3 `array_shift` kicks oldest + `invalidate` to blacklist; admin can force-kick all.
- **Action-level RBAC**: `admin.permission:{code}` middleware exact-code check; permission cache `admin:permissions:{adminId}` (Redis, plain array).
- **Dynamic menu**: cache `admin_menu:tree:{adminId}` (TTL 7 days), `buildTree` recurses by snow_id, ≤5 levels; C-end menus in separate `c_menus` + public bilingual `/api/menus` linked.

## 7. Unified API Contract

- **HTTP always 200 + `body.code`**: `0` success / `1` generic / `1001` param / `1002` unauthenticated / `1003` no permission / `1004` not found / `1005` validation / `1006` rate-limited / `1007` conflict / `1008` captcha error / `5000` server error (+2xxxx business codes).
- **Success**: `{code:0, msg:"Operation success", data:{...}}`; **Failure**: `{code:1005, msg:"Validation failed", data:errors}`.
- **Endpoint groups**: Public (health/captcha/locale/products/dicts/menus/notices); C-end (auth:api + check.user.online: login/refresh/me/messages/favorites); Admin (auth:admin-api + admin.permission:{code}: menus/roles/admins/users/products/dicts/messages/favorites/logs/IP/rate-limit/recycle/c-menus/permissions/profile).

## 8. Environment Requirements

| Component | Recommended | Verified compatible | Notes |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | Extensions: `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | Migrations compatible with 5.7 (utf8mb4, JSON); no code change for 8.0 |
| Redis | 6.0+ | **5.0.10** | phpredis; avoid advanced commands unsupported in 5.0 |
| Nginx | 1.20+ | any mainstream | Reverse proxy + SSL + WebSocket proxy (prod) |
| Supervisor | 4.x | Linux prod | Daemonizes reverb / queue / schedule; not needed on Windows dev |
| Composer | 2.x | — | Dependency management |
| Node.js | 18+ | — | Admin Vite build |
| HBuilderX | latest | — | C-end H5 build (bundled node + uniapp-cli-vite) |

**PHP extension self-check**:

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. Quick Start (Development)

### 9.1 Backend (Windows BaoTa / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # configure MySQL/Redis/AUDIT_LOG_DRIVER

# First-time keys
php artisan key:generate
php artisan jwt:secret

# Rebuild dev DB + seed (or migrate --seed)
php artisan migrate:fresh --seed

# Dev server
php artisan serve --host=127.0.0.1 --port=8000

# Separate terminals (optional): Reverb WebSocket + queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 Admin

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (/api proxied to 8000)
npm run build          # production build
```

### 9.3 C-end (HBuilderX build H5)

```bash
# Use HBuilderX bundled node + @dcloudio/vite-plugin-uni CLI:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp absolute path>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# Output frontend-uniapp/dist/build/h5; served by _cserver.py + /api proxy to 8000
# Visit http://127.0.0.1:5174
```

> PC desktop (≥768px): top nav + 1200px centered + 4-column products; mobile (<768px): bottom tabBar + single column.

## 10. Production Deployment

### 10.1 Configure `.env` (key keys)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<generated by jwt:secret>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> Multi-instance must point to the **same Redis** (rate-limit/blacklist/menu cache/multi-device state globally consistent); **never use `CACHE_STORE=file` in production**.

### 10.2 Migrate & seed

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 Process daemonization (Supervisor, Linux)

Three daemons: `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (queue), `php artisan schedule:work` (scheduler). Configure three programs in `/etc/supervisor/conf.d/laravel13.conf` then:

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` proxies to PHP-FPM with `try_files $uri $uri/ /index.php?$query_string;`
- Forward real IP: `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (rate-limit/blacklist/logs depend on it)
- `location /app/` proxies Reverb (`proxy_set_header Upgrade $http_upgrade;`) to enable WebSocket
- Laravel `bootstrap/app.php` already configures `trustProxies(at:'*')`

### 10.5 Go-live security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis has password; firewall blocks public 6379
- [ ] Nginx forwards real IP; `/api/health` log shows correct client IP
- [ ] Upload dir blocks PHP execution; DB account least-privilege
- [ ] `JWT_SECRET`/DB/Redis passwords only in `.env`, never hardcoded
- [ ] Middleware registered: `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] Supervisor 3 programs RUNNING; logs rotated daily
- [ ] Initial admin/admin123 changed; MySQL daily backup + Redis persistence

### 10.6 Scale points (100k QPS / 10M rows)

Nginx worker tuning + keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), Redis persistence + shared across instances, MySQL read/write split, monthly RANGE partitions for `messages`/logs, `user_favorites` sharding, keyset deep pagination (`snow_id < cursor`), CDN for static assets, stateless horizontal scaling. See [docs/deploy.md](docs/deploy.md).

## 11. Demo Accounts

| Client | Account | Role | Notes |
|---|---|---|---|
| Admin | admin / admin123 | super-admin | Super admin (**change password after first login**) |
| Admin | demo / demo123 | operator | Limited menus |
| C-end | user@example.com / password | Regular user | C-end login |

## 12. Testing & Validation

- **Automated**: `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **Database**: `migrate:fresh --seed` → 18 migrations + 8 seeders OK; physical table comments on all 18 business tables.
- **Browser**: admin 22 modules + C-end PC (1280×900) + H5 (375×812) + C-end 3-device mechanism all passed (see TEST_REPORT §17).

## 13. Related Documents

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — System-level summary & structure (Chinese)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — System-level summary & structure (English)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — Technical spec V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — Development contract
- [DELIVERY.md](DELIVERY.md) — Handover notes
- [TEST_REPORT.md](TEST_REPORT.md) — Test report
- [docs/deploy.md](docs/deploy.md) — Deployment & scaling
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — Dev docs
