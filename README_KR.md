# Laravel13 API Skeleton (한국어 버전)

> 프로덕션용 관리 시스템 스켈레톤 · 하나의 백엔드, 세 개의 클라이언트
> 전체 문서: 프로젝트 요약 및 구조 · 환경 요구사항 · 배포 방법

---

## 목차

1. [프로젝트 소개](#1-프로젝트-소개)
2. [주요 기능](#2-주요-기능)
3. [기술 스택](#3-기술-스택)
4. [디렉터리 구조](#4-디렉터리-구조)
5. [데이터베이스 구조](#5-데이터베이스-구조)
6. [인증 및 권한](#6-인증-및-권한)
7. [통합 API 규약](#7-통합-api-규약)
8. [환경 요구사항](#8-환경-요구사항)
9. [빠른 시작 (개발)](#9-빠른-시작-개발)
10. [프로덕션 배포](#10-프로덕션-배포)
11. [데모 계정](#11-데모-계정)
12. [테스트 및 검증](#12-테스트-및-검증)
13. [관련 문서](#13-관련-문서)

---

## 1. 프로젝트 소개

**Laravel13 API Skeleton**은 PHP 8.3 + Laravel 13 + MySQL + Redis 기반의 프로덕션용 관리 시스템 스켈레톤입니다. **하나의 백엔드가 세 개의 클라이언트**를 제공합니다 — Vue3 관리 콘솔과 uni-app 사용자 클라이언트(PC 데스크톱 + H5 모바일) — 진정한 프론트/백엔드 분리와 멀티채널 일관성을 실현합니다.

인증은 **이중 테이블·이중 가드 JWT**로 보호합니다: 일반 사용자와 관리자를 완전히 격리하고, `users`/`admins`의 비밀번호는 랜덤 6자리 솔트를 부여한 뒤 해시하여 저장합니다. 권한은 **액션 레벨 RBAC**(`리소스:액션` 코드)을 사용하며, 백엔드에서 권한·역할·동적 메뉴(5단계까지)를 유연하게 설정하고 C-end와 연동합니다. 관리 콘솔은 **싱글 사인온**(계정당 세션 1개), C-end는 **최대 3개 기기 동시 로그인**을 지원하며, 초과 시 가장 오래된 기기를 자동 강제 로그아웃하고 블랙리스트에 등록합니다. 로그인에는 이미지 캡차와 복합 요청 제한(IP/사용자/핑거프린트)이 있으며, 모든 API는 `HTTP 200 + body.code` 통합 규약을 반환하고 사이트 전체에서 **zh-CN / en** 전환을 지원합니다.

시스템에는 드라이버 기반 감사 로그(MySQL에 저장하지 않음), IP 블랙리스트, 멱등성, 응답 마스킹, 휴지통(논리 삭제 복원), 느린 쿼리 경고, 헬스 체크, 코드 생성기가 포함됩니다. 데이터는 스노우플레이크 ID, 페이지 제한, 완전한 인덱스 설계를 사용하며, 수천만 행과 10만 QPS 규모 확장을 목표로 합니다. 자동 테스트(Pest 66)와 멀티채널 브라우저 테스트가 모두 통과 — 바로 사용 가능합니다.

## 2. 주요 기능

| 분류 | 기능 |
|---|---|
| 인증 | 이중 테이블·이중 가드 JWT(C-end `users` / 관리 `admins`), 가드 간 격리, 토큰 블랙리스트 |
| 권한 | 액션 레벨 RBAC `{리소스:액션}`, 역할-권한-메뉴 3계층, 동적 메뉴(≤5단계) |
| 보안 | 이미지 캡차(Redis 1회용), 복합 요청 제한(IP/사용자/핑거프린트), 솔트 비밀번호, IP 블랙리스트 |
| 세션 | 관리 콘솔 싱글 사인온; C-end 3기기 제어(최고령 강제 로그아웃 + 블랙리스트) |
| 다국어 | zh-CN / en 전체 전환, dicts/permissions 이중 언어 label_en, md5 캐시 검증 |
| 감사 | 드라이버 기반 저장(file/Elasticsearch), **MySQL 아님**, 비밀번호 마스킹 |
| 도구 | 멱등성, 마스킹, 휴지통 복원, 느린 쿼리 경고, 헬스 체크, 코드 생성기 |
| 확장 | 스노우플레이크 ID, 페이지 제한(page_size≤100), 완전한 인덱스, cache-aside |

## 3. 기술 스택

| 계층 | 기술 |
|---|---|
| 백엔드 | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| 저장소 | MySQL 5.7+/8.0 (DB ju_test) · Redis 5.0+ (요청 제한/블랙리스트/캐시/멀티기기/locale/캡차) |
| 관리 | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-end | uni-app (H5 / 미니 프로그램 / App) · HBuilderX 빌드 · PC 데스크톱 + H5 모바일 |

## 4. 디렉터리 구조

### 4.1 프로젝트 루트

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md / README_DE.md / README_ES.md / README_KR.md   # 프로젝트 문서 (이 시리즈)
├─ PROJECT_SPEC.md        # 권위 기술 사양 V1.2-Full
├─ SHARED_CONVENTIONS.md  # 단일 개발 계약
├─ DELIVERY.md            # 인도 설명 (변경 목록 + 검증 기준)
├─ TEST_REPORT.md         # 브라우저 실측 테스트 보고서 (§17 멀티채널 테스트)
├─ SYSTEM_SUMMARY.md      # 시스템 요약 및 구조 (중국어)
├─ SYSTEM_SUMMARY_EN.md   # 시스템 요약 및 구조 (영어)
├─ _cserver.py            # C-end 로컬 정적 서버 (H5 호스팅 + /api 프록시 8000)
├─ backup_*.sql           # 데이터베이스 백업
├─ backend-laravel13/     # 백엔드
├─ frontend-vue3/         # 관리 콘솔
├─ frontend-uniapp/       # C-end
└─ docs/                  # deploy/dev-guide/business-rule/test-case/계정/codegen/i18n-sync
```

### 4.2 백엔드 `backend-laravel13/`

```
app/
├─ Base/                    # 5개 기본 클래스: BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator; StatusCode/ErrorCode/8개 비즈니스 열거형
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP 항상 200 + body.code)
├─ Helpers/                 # message() 헬퍼 + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-end) + Api/Admin/(관리) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10개 미들웨어 (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # User/ + Admin/ 모듈별 FormRequest
├─ Models/                  # 14개 모델 + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-end) + Admin/(관리) 이중 격리 비즈니스 계층
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (느린 쿼리 >500ms 리스너)

database/
├─ migrations/              # 18개 create_* (필드 이중 언어 주석 + $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (멱등 upsert)
└─ factories/               # 6개 팩토리
lang/{zh-CN,en}/            # api.php (~444 키, ui.* 포함) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ 라우트) + channels.php + console.php (4개 예약 태스크)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17개 Pest 파일
docs/                       # 13개 주요 문서 + extensions/ 8개 확장
```

### 4.3 관리 콘솔 `frontend-vue3/` (17개 페이지)

`src/views/`: login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
기타: `layout/index.vue` (새로고침 드롭다운 + 사용자 드롭다운), `layout/MenuTree.vue` (커스텀 아코디언 사이드바, 5단계), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-end `frontend-uniapp/` (8개 페이지)

`pages/`: index (홈 진입 그리드 + 상품), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
기타: `App.vue` (PC 데스크톱 media query), `index.html` (PC 상단바 이중 언어), `common/i18n.js`, `common/request.js`

## 5. 데이터베이스 구조 (18개 비즈니스 테이블)

| 테이블 | 용도 | 주요 제약 |
|---|---|---|
| `users` | C-end 일반 사용자 | email unique, salt, 논리 삭제 |
| `password_reset_tokens` / `sessions` | 프레임워크 테이블 | 기본값 |
| `admins` | 관리자 계정 | username unique, salt, 논리 삭제 |
| `roles` | 역할 | code unique, 논리 삭제 |
| `permissions` | 권한 (액션 레벨) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | 피벗 테이블 | 복합 기본 키 |
| `admin_menus` | 관리 콘솔 동적 메뉴 | parent_id, title_en, 논리 삭제 |
| `products` | 상품 예제 | 인덱스(status,created_at), (status,price), 논리 삭제 |
| `dicts` | 데이터 사전 | 인덱스(type,status), label_en, 물리 삭제 |
| `messages` | 사이트 내 메시지 | 인덱스(receiver_type,receiver_id,is_read), 논리 삭제 |
| `user_favorites` | 즐겨찾기 | (user_id,product_id) unique, 논리 삭제 |
| `ip_blacklist` | IP 블랙리스트 | ip unique, 물리 삭제 |
| `rate_limit_rules` | 요청 제한 규칙 | rule_key unique |
| `notices` | 공지 (생성기 데모) | — |
| `c_menus` | C-end 사용자 메뉴 | parent_id, title_en, 표시/숨김 + 부모-자식 + i18n만 |

> 모든 비즈니스 테이블에 `snow_id` (외부 노출); `$table->comment('테이블 - 용도/English')` 추가; 감사 로그는 **테이블 없음** (드라이버 기반 저장).

## 6. 인증 및 권한

- **이중 가드 JWT**: `api` (users) / `admin-api` (admins), payload에 `guard` 포함, 가드 간 격리.
- **솔트 비밀번호**: `salt varchar(6)`, `SaltedPasswordCast`가 `Hash(평문+솔트)` 작성, 4개 검증에서 `SaltHasher::check` 사용.
- **관리 콘솔 싱글 사인온**: `CheckAdminOnline`, 로그인 `Cache::put('admin_online:{id}', token)`, 새 로그인이 기존 세션 강제 로그아웃, 기존 토큰 401 → 프론트 자동 로그인 페이지 이동.
- **C-end 3기기**: `MultiDeviceService`, >3기기 시 `array_shift`가 최고령 강제 로그아웃 + `invalidate`로 블랙리스트, 관리자가 전체 강제 로그아웃 가능.
- **액션 레벨 RBAC**: 미들웨어 `admin.permission:{code}` 정확 코드 검증; 권한 캐시 `admin:permissions:{adminId}` (Redis, 순수 배열).
- **동적 메뉴**: 캐시 `admin_menu:tree:{adminId}` (TTL 7일), `buildTree` snow_id로 재귀, ≤5단계; C-end 메뉴는 별도 `c_menus` 테이블 + `/api/menus` 이중 언어 공개 인터페이스 연동.

## 7. 통합 API 규약

- **HTTP 항상 200 + `body.code`**: `0` 성공 / `1` 일반 오류 / `1001` 파라미터 / `1002` 미인증 / `1003` 권한 없음 / `1004` 없음 / `1005` 검증 실패 / `1006` 요청 제한 / `1007` 충돌 / `1008` 캡차 오류 / `5000` 서버 오류 (+2xxxx 비즈니스 코드).
- **성공**: `{code:0, msg:"작업 성공", data:{...}}`; **실패**: `{code:1005, msg:"입력 검증 실패", data:errors}`.
- **인터페이스 그룹**: 공개 (health/captcha/locale/products/dicts/menus/notices); C-end (auth:api + check.user.online: login/refresh/me/messages/favorites); 관리 (auth:admin-api + admin.permission:{code}: 메뉴/역할/관리자/사용자/상품/사전/메시지/즐겨찾기/로그/IP/요청 제한/휴지통/C메뉴/권한/프로필).

## 8. 환경 요구사항

| 구성 요소 | 권장 | 실측 호환 | 비고 |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | 확장: `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | 마이그레이션 5.7 호환 (utf8mb4, JSON); 8.0 업그레이드 시 코드 변경 없음 |
| Redis | 6.0+ | **5.0.10** | phpredis 확장; 5.0 미지원 고급 명령 회피 |
| Nginx | 1.20+ | 일반 최신 | 리버스 프록시 + SSL + WebSocket 프록시 (프로덕션) |
| Supervisor | 4.x | Linux 프로덕션 | reverb / queue / schedule 데몬화; Windows 개발 불필요 |
| Composer | 2.x | — | 의존성 관리 |
| Node.js | 18+ | — | 관리 콘솔 Vite 빌드 |
| HBuilderX | 최신 | — | C-end H5 빌드 (내장 node + uniapp-cli-vite) |

**PHP 확장 자가 점검**:

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. 빠른 시작 (개발)

### 9.1 백엔드 (Windows / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # MySQL/Redis/AUDIT_LOG_DRIVER 설정

# 첫 키 생성
php artisan key:generate
php artisan jwt:secret

# 개발 DB 재구축 + 시드 (또는 migrate --seed)
php artisan migrate:fresh --seed

# 개발 서버
php artisan serve --host=127.0.0.1 --port=8000

# 별도 터미널 (선택): Reverb WebSocket + queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 관리 콘솔

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (/api 프록시 8000)
npm run build          # 프로덕션 빌드
```

### 9.3 C-end (HBuilderX로 H5 빌드)

```bash
# HBuilderX 내장 node + @dcloudio/vite-plugin-uni CLI 사용:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp 절대 경로>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# 출력 frontend-uniapp/dist/build/h5; _cserver.py 제공 + /api 프록시 8000
# 접속 http://127.0.0.1:5174
```

> PC 데스크톱 (≥768px): 상단 내비게이션 + 1200px 중앙 정렬 + 상품 4열; 모바일 (<768px): 하단 tabBar + 단일 열.

## 10. 프로덕션 배포

### 10.1 `.env` 설정 (주요 키)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<jwt:secret로 생성>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> 멀티 인스턴스는 **동일 Redis**를 가리켜야 합니다 (요청 제한/블랙리스트/메뉴 캐시/멀티기기 상태 글로벌 일치); **프로덕션에서 `CACHE_STORE=file` 금지**.

### 10.2 마이그레이션 및 시드

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 프로세스 데몬화 (Supervisor, Linux)

세 개의 데몬: `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (queue), `php artisan schedule:work` (스케줄러). `/etc/supervisor/conf.d/laravel13.conf`에 3개 프로그램 설정 후:

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` PHP-FPM으로 프록시, `try_files $uri $uri/ /index.php?$query_string;`
- 실 IP 전달: `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (요청 제한/블랙리스트/로그 의존)
- `location /app/` Reverb 프록시 (`proxy_set_header Upgrade $http_upgrade;`)로 WebSocket 활성화
- Laravel `bootstrap/app.php`는 `trustProxies(at:'*')` 설정 완료

### 10.5 출시 전 보안 체크리스트

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis 비밀번호 설정, 방화벽에서 외부 6379 차단
- [ ] Nginx 실 IP 전달, `/api/health` 로그 IP 정확
- [ ] 업로드 디렉토리 PHP 실행 금지; DB 계정 최소 권한
- [ ] `JWT_SECRET`/DB/Redis 비밀번호 `.env`만, 하드코딩 금지
- [ ] 미들웨어 등록: `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] Supervisor 3개 프로세스 RUNNING; 로그 일별 분할
- [ ] 초기 admin/admin123 변경; MySQL 일일 백업 + Redis 영속화

### 10.6 확장 포인트 (10만 QPS / 수천만 행)

Nginx worker 튜닝 + keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), Redis 영속화 + 인스턴스 공유, MySQL 읽기/쓰기 분리, `messages`/로그 월별 RANGE 파티션, `user_favorites` 샤딩, keyset 딥 페이지네이션 (`snow_id < cursor`), CDN 정적 자산 분리, 무상태 수평 확장. 자세히는 [docs/deploy.md](docs/deploy.md).

## 11. 데모 계정

| 클라이언트 | 계정 | 역할 | 비고 |
|---|---|---|---|
| 관리 콘솔 | admin / admin123 | super-admin | 슈퍼 관리자 (**첫 로그인 후 비밀번호 변경**) |
| 관리 콘솔 | demo / demo123 | operator | 제한 메뉴 |
| C-end | user@example.com / password | 일반 사용자 | C-end 로그인 |

## 12. 테스트 및 검증

- **자동 테스트**: `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **데이터베이스**: `migrate:fresh --seed` → 18개 마이그레이션 + 8개 시더 OK; 18개 비즈니스 테이블 물리 주석 적용.
- **브라우저 실측**: 관리 22개 모듈 + C-end PC (1280×900) + H5 (375×812) + C-end 3기기 메커니즘 모두 통과 (TEST_REPORT §17 참조).

## 13. 관련 문서

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — 시스템 요약 및 구조 (중국어)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — 시스템 요약 및 구조 (영어)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — 기술 사양 V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — 개발 계약
- [DELIVERY.md](DELIVERY.md) — 인도 설명
- [TEST_REPORT.md](TEST_REPORT.md) — 테스트 보고서
- [docs/deploy.md](docs/deploy.md) — 배포 및 확장
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — 개발 문서
