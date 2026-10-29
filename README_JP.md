# Laravel13 API Skeleton（日本語版）

> 本番運用向け中・後台管理システム骨格 · 一つのバックエンド、三つのクライアント
> 完全ドキュメント：プロジェクト概要と構成説明 · 環境要件 · デプロイ方法

---

## 目次

1. [プロジェクト概要](#1-プロジェクト概要)
2. [主な機能](#2-主な機能)
3. [技術スタック](#3-技術スタック)
4. [ディレクトリ構成](#4-ディレクトリ構成)
5. [データベース構造](#5-データベース構造)
6. [認証と権限](#6-認証と権限)
7. [統一 API 規約](#7-統一-api-規約)
8. [環境要件](#8-環境要件)
9. [クイックスタート（開発環境）](#9-クイックスタート開発環境)
10. [本番デプロイ](#10-本番デプロイ)
11. [デモアカウント](#11-デモアカウント)
12. [テストと検証](#12-テストと検証)
13. [関連ドキュメント](#13-関連ドキュメント)

---

## 1. プロジェクト概要

**Laravel13 API Skeleton** は、PHP 8.3 + Laravel 13 + MySQL + Redis で構築された本番運用向けの管理システム骨格です。**一つのバックエンドで三つのクライアント**を同時に提供します——Vue3 管理画面と、uni-app ユーザー側クライアント（PC デスクトップ版 + H5 モバイル版）——真正のフロント・バック分離とマルチクライアント統一を実現します。

認証は**二テーブル・二ガード JWT** で保護します：一般ユーザーと管理者を完全に隔離し、`users`/`admins` のパスワードはランダム 6 桁のソルトを付与してから Hash 化して保存し、撞庫攻撃を防止します。権限は**アクションレベル RBAC**（`リソース:アクション` 権限コード）を採用し、権限・ロール・動的メニューをバックエンドで柔軟に設定できます（メニューは第 5 階層まで対応、C 端と連動）。管理画面は**シングルサインオン**（同一アカウントはセッション 1 つのみ）、C 端は**最大 3 端末同時ログイン**をサポートし、超過時は最古端末を自動キックしてブラックリスト入りします。ログインには画像キャプチャと複合レート制限（IP/ユーザー/フィンガープリント）が付き、API は `HTTP 200 + body.code` の統一規約を返し、全サイトで**中日…中英バイリンガル**切替に対応します。

監査ログ（ドライバー方式保存、MySQL に落とさない）、IP ブラックリスト、冪等性（重複防止）、レスポンス機密マスキング、ゴミ箱（論理削除復元）、遅いクエリ警告、ヘルスチェック、コード生成器などの拡張機能を内蔵します。データはスノーフレーク ID・ページング制限・インデックス完備設計を採用し、千万レベルのデータと十万レベルの QPS スケール拡張に対応します。自動テスト（Pest 66 件）とマルチクライアント・ブラウザ実測がすべて合格し、そのまま使い始められます。

## 2. 主な機能

| 分類 | 機能 |
|---|---|
| 認証 | 二テーブル・二ガード JWT（C 端 `users` / 管理 `admins`）、ガード間分離、トークンブラックリスト |
| 権限 | アクションレベル RBAC `{リソース:アクション}`、ロール・権限・メニュー三層、動的メニュー（≤5 階層） |
| セキュリティ | 画像キャプチャ（Redis 一回性）、複合レート制限（IP/ユーザー/フィンガープリント）、パスワードソルト、IP ブラックリスト |
| セッション | 管理画面シングルサインオン、C 端 3 端末制御（最古キック + ブラックリスト） |
| 多言語 | zh-CN / en 全サイト切替、dicts/permissions バイリンガル label_en、md5 キャッシュ検証 |
| 監査 | ドライバー方式保存（file/Elasticsearch）、**MySQL に落とさない**、パスワードマスキング |
| 拡張 | 冪等性、レスポンス機密マスキング、ゴミ箱論理削除復元、遅いクエリ警告、ヘルスチェック、コード生成器 |
| スケール | スノーフレーク ID、ページング制限（page_size≤100）、インデックス完備、cache-aside |

## 3. 技術スタック

| 層 | 技術 |
|---|---|
| バックエンド | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth`（二ガード JWT）· `fruitcake/laravel-cors` · `laravel/reverb`（WebSocket） |
| ストレージ | MySQL 5.7+/8.0（DB ju_test）· Redis 5.0+（レート制限/ブラックリスト/キャッシュ/多端/言語パック/キャプチャ） |
| 管理画面 | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C 端 | uni-app（H5 / ミニアプリ / App）· HBuilderX ビルド · PC デスクトップ + H5 モバイル |

## 4. ディレクトリ構成

### 4.1 プロジェクトルート

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md   # プロジェクトドキュメント（本シリーズ）
├─ PROJECT_SPEC.md        # 権威技術仕様 V1.2-Full
├─ SHARED_CONVENTIONS.md  # 唯一の開発調整契約
├─ DELIVERY.md            # 引渡し説明（変更リスト + 検証ベースライン）
├─ TEST_REPORT.md         # ブラウザ実測テストレポート（§17 マルチクライアントテスト）
├─ SYSTEM_SUMMARY.md      # システムレベル要約と構成説明（中国語）
├─ SYSTEM_SUMMARY_EN.md   # システムレベル要約と構成説明（英語）
├─ _cserver.py            # C 端ローカル静的サーバー（H5 配信 + /api プロキシ 8000）
├─ backup_*.sql           # データベースバックアップ
├─ backend-laravel13/     # バックエンド
├─ frontend-vue3/         # 管理画面
├─ frontend-uniapp/       # C 端
└─ docs/                  # デプロイ/開発ガイド/業務ルール/テストケース/アカウント/コード生成/i18n 同期
```

### 4.2 バックエンド `backend-laravel13/`

```
app/
├─ Base/                    # 五基クラス：BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator；StatusCode/ErrorCode/8 業務列挙
├─ Exceptions/              # ApiException + ApiExceptionHandler（HTTP 恒 200 + body.code）
├─ Helpers/                 # message() ヘルパー + common.php（snow_id/client_ip/fingerprint/mask_text）
├─ Http/
│  ├─ Controllers/          # Api/(C端) + Api/Admin/(管理) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 個のミドルウェア（IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...）
│  └─ Requests/             # User/ + Admin/ 各モジュール FormRequest
├─ Models/                  # 14 モデル + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C端) + Admin/(管理) 二重隔離業務層
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider（遅いクエリ >500ms 監視）

database/
├─ migrations/              # 18 個の create_*（フィールド中英コメント + テーブル $table->comment）
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule（冪等 upsert）
└─ factories/               # 6 個のファクトリー
lang/{zh-CN,en}/            # api.php（約 444 キー、ui.* 含む）+ notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php（94+ ルート）+ channels.php + console.php（4 計画タスク）
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 個の Pest ファイル
docs/                       # 13 主ドキュメント + extensions/ 8 拡張
```

### 4.3 管理画面 `frontend-vue3/`（17 ページ）

`src/views/`：login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
その他：`layout/index.vue`（トップバー更新ドロップダウン + ユーザードロップダウン）、`layout/MenuTree.vue`（カスタム手風琴サイドバー、5 階層）、`api/*`、`i18n/index.js`、`store`（Pinia）、`router/index.js`

### 4.4 C 端 `frontend-uniapp/`（8 ページ）

`pages/`：index（ホーム入口グリッド + 商品）、product/list、product/detail、dict/index、favorite/index、message/index、mine/index、login/index
その他：`App.vue`（PC デスクトップ版 media query）、`index.html`（PC トップバーバイリンガル）、`common/i18n.js`、`common/request.js`

## 5. データベース構造（18 業務テーブル）

| テーブル | 用途 | 主な制約 |
|---|---|---|
| `users` | C 端一般ユーザー | email unique、salt、論理削除 |
| `password_reset_tokens` / `sessions` | フレームワーク表 | 骨格そのまま |
| `admins` | 管理者 | username unique、salt、論理削除 |
| `roles` | ロール | code unique、論理削除 |
| `permissions` | 権限（アクションレベル） | code unique、group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | 関連中間テーブル | 複合主キー |
| `admin_menus` | 管理画面動的メニュー | parent_id、title_en、論理削除 |
| `products` | 商品サンプル | インデックス(status,created_at)、(status,price)、論理削除 |
| `dicts` | データ辞書 | インデックス(type,status)、label_en、物理削除 |
| `messages` | サイト内メッセージ | インデックス(receiver_type,receiver_id,is_read)、論理削除 |
| `user_favorites` | お気に入り | (user_id,product_id) 複合 unique、論理削除 |
| `ip_blacklist` | IP ブラックリスト | ip unique、物理削除 |
| `rate_limit_rules` | レート制限ルール | rule_key unique |
| `notices` | お知らせ（生成器デモ） | — |
| `c_menus` | C 端ユーザーメニュー | parent_id、title_en、表示/非表示 + 親子 + 多言語のみ |

> 全業務テーブルに `snow_id`（外部公開）；`$table->comment('テーブル名 - 用途/English')` 付与；監査ログは**テーブルなし**（ドライバー方式保存）。

## 6. 認証と権限

- **二ガード JWT**：`api`（users）/ `admin-api`（admins）、payload に `guard` claim、ガード間分離。
- **パスワードソルト**：`salt varchar(6)`、`SaltedPasswordCast` が `Hash(平文+ソルト)` を書込み、4 箇所の検証で `SaltHasher::check` を使用。
- **管理画面シングルサインオン**：`CheckAdminOnline`、ログインで `Cache::put('admin_online:{id}', token)`、新ログインが旧セッションをキック、旧トークン 401 → フロント自動ログインページ遷移。
- **C 端 3 端末**：`MultiDeviceService`、>3 端末で `array_shift` が最古をキック + `invalidate` でブラックリスト、管理者が強制キック可能。
- **アクションレベル RBAC**：`admin.permission:{code}` ミドルウェアが正確コード検証；権限キャッシュ `admin:permissions:{adminId}`（Redis、純配列）。
- **動的メニュー**：キャッシュ `admin_menu:tree:{adminId}`（TTL 7 日）、`buildTree` は snow_id で再帰、≤5 階層対応；C 端メニューは独立 `c_menus` テーブル + `/api/menus` バイリンガル公開インターフェースで連動。

## 7. 統一 API 規約

- **HTTP 恒 200 + `body.code`**：`0` 成功 / `1` 汎用エラー / `1001` パラメータ / `1002` 未認証 / `1003` 権限なし / `1004` 不存在 / `1005` 検証失敗 / `1006` レート制限 / `1007` 衝突 / `1008` キャプチャエラー / `5000` サーバーエラー（+2xxxx 業務コード）。
- **成功レスポンス**：`{code:0, msg:"操作成功", data:{...}}`；**失敗**：`{code:1005, msg:"入力検証失敗", data:errors}`。
- **インターフェースグループ**：公開（health/captcha/locale/products/dicts/menus/notices）；C 端（auth:api + check.user.online：login/refresh/me/messages/favorites）；管理（auth:admin-api + admin.permission:{code}：メニュー/ロール/管理者/ユーザー/商品/辞書/メッセージ/お気に入り/ログ/IP/レート制限/ゴミ箱/Cメニュー/権限/プロフィール）。

## 8. 環境要件

| コンポーネント | 推奨 | 実測互換 | 説明 |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | 拡張：`pdo_mysql`、`redis`、`openssl`、`mbstring`、`curl`、`fileinfo`、`bcmath`、`tokenizer`、`xml` |
| MySQL | 8.0 | **5.7.38** | マイグレーションは 5.7 互換（utf8mb4、JSON）；8.0 へアップグレードしてもコード変更不要 |
| Redis | 6.0+ | **5.0.10** | phpredis 拡張；5.0 非対応の高次コマンドを回避 |
| Nginx | 1.20+ | 主流任意 | リバースプロキシ + SSL + WebSocket プロキシ（本番） |
| Supervisor | 4.x | Linux 本番 | reverb / queue / schedule を常駐化；Windows 開発では不要 |
| Composer | 2.x | — | 依存管理 |
| Node.js | 18+ | — | 管理画面 Vite ビルド |
| HBuilderX | 最新 | — | C 端 H5 ビルド（内蔵 node + uniapp-cli-vite） |

**PHP 拡張セルフチェック**：

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. クイックスタート（開発環境）

### 9.1 バックエンド（Windows / Linux 共通）

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # MySQL/Redis/AUDIT_LOG_DRIVER を設定

# 初回キー生成
php artisan key:generate
php artisan jwt:secret

# 開発 DB 再構築 + シード（または migrate --seed）
php artisan migrate:fresh --seed

# 開発サーバー起動
php artisan serve --host=127.0.0.1 --port=8000

# 別ターミナル（任意）：Reverb WebSocket とキューワーカー
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 管理画面

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173（/api は 8000 へプロキシ）
npm run build          # 本番ビルド
```

### 9.3 C 端（HBuilderX で H5 ビルド）

```bash
# HBuilderX 内蔵 node + @dcloudio/vite-plugin-uni CLI を使用：
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<frontend-uniapp 絶対パス>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# 出力 frontend-uniapp/dist/build/h5；_cserver.py で配信 + /api プロキシ 8000
# アクセス http://127.0.0.1:5174
```

> PC デスクトップ版（≥768px）：トップナビ + 1200px 中央 + 商品 4 列；モバイル（<768px）：下部 tabBar + 単列。

## 10. 本番デプロイ

### 10.1 `.env` 設定（主要キー）

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<jwt:secret で生成>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> マルチインスタンスは**同一 Redis** を指す必要があります（レート制限/ブラックリスト/メニューキャッシュ/多端状態をグローバル一致）；**本番で `CACHE_STORE=file` は禁止**。

### 10.2 マイグレーションとシード

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 プロセス常駐（Supervisor、Linux）

3 つの常駐プロセス：`php artisan reverb:start`（WebSocket）、`php artisan queue:work --tries=3 --timeout=90`（キュー）、`php artisan schedule:work`（スケジューラ）。`/etc/supervisor/conf.d/laravel13.conf` に 3 プログラムを設定後：

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` で PHP-FPM へプロキシ、`try_files $uri $uri/ /index.php?$query_string;`
- 実 IP 転送：`fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;`（レート制限/ブラックリスト/ログが依存）
- `location /app/` で Reverb へプロキシ（`proxy_set_header Upgrade $http_upgrade;`）して WebSocket を有効化
- Laravel `bootstrap/app.php` は `trustProxies(at:'*')` を設定済み

### 10.5 リリース前セキュリティチェックリスト

- [ ] `APP_DEBUG=false`、`APP_ENV=production`
- [ ] Redis にパスワード設定、ファイアウォールで外部 6379 遮断
- [ ] Nginx が実 IP 転送、`/api/health` ログの IP が正しい
- [ ] アップロードディレクトリで PHP 実行禁止；DB アカウント最小権限
- [ ] `JWT_SECRET`/DB/Redis パスワードは `.env` のみ、ハードコード禁止
- [ ] ミドルウェア登録：`ip.blacklist`、`api.composite.rate`、`lang`
- [ ] Supervisor 3 プロセス RUNNING；ログ日次分割
- [ ] 初期 admin/admin123 を変更；MySQL 日次バックアップ + Redis 永続化

### 10.6 スケールポイント（10 万 QPS / 千万データ）

Nginx worker チューニング + 長連接、PHP-FPM `max_children`/OpCache（`validate_timestamps=0`）、Redis 永続化 + インスタンス共有、MySQL 読み書き分離、`messages`/ログの月次 RANGE パーティション、`user_favorites` シャーディング、keyset 深いページング（`snow_id < cursor`）、CDN 静的リソース分離、ステートレス水平拡張。詳細は [docs/deploy.md](docs/deploy.md)。

## 11. デモアカウント

| クライアント | アカウント | ロール | 説明 |
|---|---|---|---|
| 管理画面 | admin / admin123 | super-admin | スーパー管理者（**初回ログイン後にパスワード変更**） |
| 管理画面 | demo / demo123 | operator | 運営担当（制限メニュー） |
| C 端 | user@example.com / password | 一般ユーザー | C 端ログイン |

## 12. テストと検証

- **自動テスト**：`vendor/bin/pest` → **66 passed / 310 assertions**（Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle）。
- **データベース**：`migrate:fresh --seed` → 18 マイグレーション + 8 シード成功；18 業務テーブルに物理コメント反映。
- **ブラウザ実測**：管理画面 22 モジュール + C 端 PC 版（1280×900）+ H5 版（375×812）+ C 端 3 端末機構すべて合格（TEST_REPORT §17 参照）。

## 13. 関連ドキュメント

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — システムレベル要約と構成説明（中国語）
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — システムレベル要約と構成説明（英語）
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — 技術仕様 V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — 開発調整契約
- [DELIVERY.md](DELIVERY.md) — 引渡し説明
- [TEST_REPORT.md](TEST_REPORT.md) — テストレポート
- [docs/deploy.md](docs/deploy.md) — デプロイとスケール
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — 開発ドキュメント
