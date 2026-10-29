# Laravel13 API Skeleton (Deutsche Version)

> Produktionsreifes Administrations-Systemgerüst · Ein Backend, drei Clients
> Vollständige Dokumentation: Projektübersicht und Struktur · Umgebungsanforderungen · Bereitstellung

---

## Inhaltsverzeichnis

1. [Projektübersicht](#1-projektübersicht)
2. [Hauptfunktionen](#2-hauptfunktionen)
3. [Technologie-Stack](#3-technologie-stack)
4. [Verzeichnisstruktur](#4-verzeichnisstruktur)
5. [Datenbankstruktur](#5-datenbankstruktur)
6. [Authentifizierung und Berechtigungen](#6-authentifizierung-und-berechtigungen)
7. [Einheitlicher API-Vertrag](#7-einheitlicher-api-vertrag)
8. [Umgebungsanforderungen](#8-umgebungsanforderungen)
9. [Schnellstart (Entwicklung)](#9-schnellstart-entwicklung)
10. [Produktionsbereitstellung](#10-produktionsbereitstellung)
11. [Demokonten](#11-demokonten)
12. [Tests und Validierung](#12-tests-und-validierung)
13. [Zugehörige Dokumente](#13-zugehörige-dokumente)

---

## 1. Projektübersicht

**Laravel13 API Skeleton** ist ein produktionsreifes Administrations-Systemgerüst auf Basis von PHP 8.3 + Laravel 13 + MySQL + Redis. Ein Backend bedient drei Clients — eine Vue3-Administrationskonsole und einen uni-app-Benutzerclient (PC-Desktop + H5-Mobil) — mit echter Frontend/Backend-Trennung und Multikanal-Konsistenz.

Die Authentifizierung wird durch **JWT mit zwei Tabellen und zwei Wächtern** gesichert: reguläre Benutzer und Administratoren sind vollständig isoliert; Passwörter in `users` und `admins` werden mit einem zufälligen 6-Zeichen-Salt versehen und dann gehasht. Berechtigungen verwenden **aktionsbasiertes RBAC** (Codes `Ressource:Aktion`) mit konfigurierbaren Berechtigungen, Rollen und dynamischen Menüs (bis Ebene 5), die sich mit dem C-End synchronisieren. Die Administrationskonsole erzwingt **Single Sign-On** (eine Sitzung pro Konto), während der C-End **bis zu 3 gleichzeitige Geräte** unterstützt — darüber hinaus wird das älteste Gerät automatisch abgemeldet und auf die Blacklist gesetzt. Logins verwenden Bild-CAPTCHAs und eine zusammengesetzte Ratenbegrenzung (IP/Benutzer/Fingerabdruck). Alle APIs geben den einheitlichen Vertrag `HTTP 200 + body.code` zurück, mit vollständiger Umschaltung **zh-CN / en**.

Das System umfasst treiberbasierte Audit-Logs (nicht in MySQL gespeichert), IP-Blacklist, Idempotenz, Antwort-Desensibilisierung, Papierkorb-Wiederherstellung (Soft Delete), Slow-Query-Warnungen, Health-Checks und einen Codegenerator. Die Daten verwenden Snowflake-IDs, Seitenlimitierung und vollständige Indizes, ausgelegt für Millionen von Datensätzen und 100k QPS. Automatisierte Tests (Pest 66) und Multikanal-Browsertests bestehen alle — einsatzbereit.

## 2. Hauptfunktionen

| Kategorie | Funktion |
|---|---|
| Authentifizierung | JWT mit zwei Tabellen/Wächtern (C-End `users` / Admin `admins`), Wächter-Isolation, Token-Blacklist |
| Berechtigungen | Aktionsbasiertes RBAC `{Ressource:Aktion}`, Rolle-Berechtigung-Menü-Ebenen, dynamisches Menü (≤5 Ebenen) |
| Sicherheit | Bild-CAPTCHA (Redis einmalig), zusammengesetzte Ratenbegrenzung (IP/Benutzer/Fingerabdruck), gesalzene Passwörter, IP-Blacklist |
| Sitzungen | Admin Single Sign-On; C-End 3-Geräte-Kontrolle (ältestes abmelden + Blacklist) |
| i18n | Vollständige Umschaltung zh-CN / en, zweisprachiges label_en für dicts/permissions, md5-Cache-Validierung |
| Audit | Treiberbasierte Speicherung (file/Elasticsearch), **nicht in MySQL**, Passwort-Maskierung |
| Werkzeuge | Idempotenz, Desensibilisierung, Papierkorb-Wiederherstellung, Slow-Query-Warnung, Health-Check, Codegenerator |
| Skalierung | Snowflake-ID, Seitenlimitierung (page_size≤100), vollständige Indizes, Cache-aside |

## 3. Technologie-Stack

| Ebene | Technologie |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| Speicher | MySQL 5.7+/8.0 (DB ju_test) · Redis 5.0+ (Ratenlimit/Blacklist/Cache/Multigerät/Locale/CAPTCHA) |
| Admin | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-End | uni-app (H5 / Mini-Programm / App) · HBuilderX-Build · PC-Desktop + H5-Mobil |

## 4. Verzeichnisstruktur

### 4.1 Projektstamm

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md / README_DE.md   # Projektdokumente (diese Serie)
├─ PROJECT_SPEC.md        # Autoritative technische Spezifikation V1.2-Full
├─ SHARED_CONVENTIONS.md  # Einheitlicher Entwicklungsvertrag
├─ DELIVERY.md            # Übergabehinweise (Änderungsliste + Validierungsbasis)
├─ TEST_REPORT.md         # Browsertest-Bericht (§17 Multikanal-Tests)
├─ SYSTEM_SUMMARY.md      # Systemübersicht und Struktur (Chinesisch)
├─ SYSTEM_SUMMARY_EN.md   # Systemübersicht und Struktur (Englisch)
├─ _cserver.py            # C-End lokaler statischer Server (hostet H5 + /api-Proxy zu 8000)
├─ backup_*.sql           # Datenbanksicherungen
├─ backend-laravel13/     # Backend
├─ frontend-vue3/         # Administration
├─ frontend-uniapp/       # C-End
└─ docs/                  # deploy/dev-guide/business-rule/test-case/Konten/codegen/i18n-sync
```

### 4.2 Backend `backend-laravel13/`

```
app/
├─ Base/                    # Fünf Basisklassen: BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator; StatusCode/ErrorCode/8 Geschäfts-Enums
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP immer 200 + body.code)
├─ Helpers/                 # message()-Helper + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-End) + Api/Admin/(Admin) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 Middleware (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # User/ + Admin/ FormRequests pro Modul
├─ Models/                  # 14 Modelle + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-End) + Admin/(Admin) isolierte Geschäftsschicht
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (Slow-Query-Listener >500ms)

database/
├─ migrations/              # 18 create_* (zweisprachige Feld-Kommentare + $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (idempotenter Upsert)
└─ factories/               # 6 Factories
lang/{zh-CN,en}/            # api.php (~444 Schlüssel inkl. ui.*) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ Routen) + channels.php + console.php (4 geplante Tasks)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 Pest-Dateien
docs/                       # 13 Hauptdokumente + extensions/ 8 Erweiterungen
```

### 4.3 Administration `frontend-vue3/` (17 Seiten)

`src/views/`: login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
Weitere: `layout/index.vue` (Aktualisierungs-Dropdown + Benutzer-Dropdown), `layout/MenuTree.vue` (benutzerdefinierte Akkordeon-Sidebar, 5 Ebenen), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-End `frontend-uniapp/` (8 Seiten)

`pages/`: index (Startseiten-Einstiegsraster + Produkte), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
Weitere: `App.vue` (PC-Desktop Media Query), `index.html` (PC-Oberleiste zweisprachig), `common/i18n.js`, `common/request.js`

## 5. Datenbankstruktur (18 Geschäftstabellen)

| Tabelle | Zweck | Wichtige Einschränkungen |
|---|---|---|
| `users` | C-End-Benutzer | email unique, Salt, Soft Delete |
| `password_reset_tokens` / `sessions` | Framework-Tabellen | Standard |
| `admins` | Admin-Konten | username unique, Salt, Soft Delete |
| `roles` | Rollen | code unique, Soft Delete |
| `permissions` | Berechtigungen (aktionsbasiert) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | Pivot-Tabellen | zusammengesetzter Primärschlüssel |
| `admin_menus` | Dynamische Admin-Menüs | parent_id, title_en, Soft Delete |
| `products` | Produktbeispiel | Index(status,created_at), (status,price), Soft Delete |
| `dicts` | Datenwörterbuch | Index(type,status), label_en, hartes Löschen |
| `messages` | Interne Nachrichten | Index(receiver_type,receiver_id,is_read), Soft Delete |
| `user_favorites` | Favoriten | (user_id,product_id) unique, Soft Delete |
| `ip_blacklist` | IP-Blacklist | ip unique, hartes Löschen |
| `rate_limit_rules` | Ratenlimit-Regeln | rule_key unique |
| `notices` | Ankündigungen (Generator-Demo) | — |
| `c_menus` | C-End-Benutzermenüs | parent_id, title_en, nur Anzeige + Eltern-Kind + i18n |

> Alle Geschäftstabellen haben `snow_id` (exponiert); `$table->comment('Tabelle - Zweck/English')` hinzugefügt; Audit-Log **ohne Tabelle** (treiberbasierte Speicherung).

## 6. Authentifizierung und Berechtigungen

- **JWT mit zwei Wächtern**: `api` (users) / `admin-api` (admins), Payload trägt `guard`, Wächter-isoliert.
- **Gesalzenes Passwort**: `salt varchar(6)`, `SaltedPasswordCast` schreibt `Hash(Klartext+Salz)`, 4 Prüfungen über `SaltHasher::check`.
- **Admin Single Sign-On**: `CheckAdminOnline`, Login `Cache::put('admin_online:{id}', token)`, neuer Login meldet alten ab, alter Token 401 → automatische Weiterleitung zum Login.
- **C-End 3 Geräte**: `MultiDeviceService`, >3 `array_shift` meldet ältestes ab + `invalidate` zur Blacklist; Admin kann alle abmelden.
- **Aktionsbasiertes RBAC**: Middleware `admin.permission:{code}` Exaktcode-Prüfung; Berechtigungs-Cache `admin:permissions:{adminId}` (Redis, einfaches Array).
- **Dynamisches Menü**: Cache `admin_menu:tree:{adminId}` (TTL 7 Tage), `buildTree` rekursiv per snow_id, ≤5 Ebenen; C-End-Menüs in separater `c_menus`-Tabelle + öffentliche zweisprachige `/api/menus`-Schnittstelle synchronisiert.

## 7. Einheitlicher API-Vertrag

- **HTTP immer 200 + `body.code`**: `0` Erfolg / `1` allgemeiner Fehler / `1001` Parameter / `1002` nicht authentifiziert / `1003` keine Berechtigung / `1004` nicht gefunden / `1005` Validierung / `1006` Ratenlimit / `1007` Konflikt / `1008` CAPTCHA-Fehler / `5000` Serverfehler (+Geschäftscodes 2xxxx).
- **Erfolg**: `{code:0, msg:"Vorgang erfolgreich", data:{...}}`; **Fehler**: `{code:1005, msg:"Validierung fehlgeschlagen", data:errors}`.
- **Schnittstellengruppen**: Öffentlich (health/captcha/locale/products/dicts/menus/notices); C-End (auth:api + check.user.online: login/refresh/me/messages/favorites); Admin (auth:admin-api + admin.permission:{code}: Menüs/Rollen/Admins/Benutzer/Produkte/Wörterbücher/Nachrichten/Favoriten/Logs/IP/Ratenlimit/Papierkorb/C-Menüs/Berechtigungen/Profil).

## 8. Umgebungsanforderungen

| Komponente | Empfohlen | Kompatibel verifiziert | Hinweise |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | Erweiterungen: `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | Migrationen kompatibel mit 5.7 (utf8mb4, JSON); keine Codeänderung für 8.0 |
| Redis | 6.0+ | **5.0.10** | phpredis; fortgeschrittene Befehle vermeiden, die 5.0 nicht unterstützt |
| Nginx | 1.20+ | jedes aktuelle | Reverse-Proxy + SSL + WebSocket-Proxy (Prod) |
| Supervisor | 4.x | Linux Prod | Daemonisiert reverb / queue / schedule; nicht nötig auf Windows Dev |
| Composer | 2.x | — | Abhängigkeitsverwaltung |
| Node.js | 18+ | — | Admin-Vite-Build |
| HBuilderX | neueste | — | C-End-H5-Build (eingebautes Node + uniapp-cli-vite) |

**PHP-Erweiterungs-Selbsttest**:

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. Schnellstart (Entwicklung)

### 9.1 Backend (Windows / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # MySQL/Redis/AUDIT_LOG_DRIVER konfigurieren

# Erstgenerierung der Schlüssel
php artisan key:generate
php artisan jwt:secret

# Dev-DB neu aufbauen + seed (oder migrate --seed)
php artisan migrate:fresh --seed

# Dev-Server
php artisan serve --host=127.0.0.1 --port=8000

# Separate Terminals (optional): Reverb WebSocket + Queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 Administration

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (/api-Proxy zu 8000)
npm run build          # Produktions-Build
```

### 9.3 C-End (H5-Build über HBuilderX)

```bash
# Eingebautes Node von HBuilderX + @dcloudio/vite-plugin-uni CLI verwenden:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<absoluter Pfad frontend-uniapp>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# Ausgabe frontend-uniapp/dist/build/h5; von _cserver.py bereitgestellt + /api-Proxy zu 8000
# Zugriff http://127.0.0.1:5174
```

> PC-Desktop (≥768px): obere Navigationsleiste + 1200px zentriert + 4-Spalten-Produkte; Mobil (<768px): untere tabBar + einzelne Spalte.

## 10. Produktionsbereitstellung

### 10.1 `.env`-Konfiguration (Hauptschlüssel)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<von jwt:secret generiert>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> Multi-Instanz muss auf den **gleichen Redis** zeigen (Ratenlimit/Blacklist/Menü-Cache/Multigerätestatus global konsistent); **niemals `CACHE_STORE=file` in Produktion**.

### 10.2 Migration und Seed

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 Prozess-Daemonisierung (Supervisor, Linux)

Drei Daemons: `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (Queue), `php artisan schedule:work` (Planer). Drei Programme in `/etc/supervisor/conf.d/laravel13.conf` konfigurieren, dann:

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` proxifiziert zu PHP-FPM mit `try_files $uri $uri/ /index.php?$query_string;`
- Echte IP weiterleiten: `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (Ratenlimit/Blacklist/Logs hängen davon ab)
- `location /app/` proxifiziert Reverb (`proxy_set_header Upgrade $http_upgrade;`), um WebSocket zu aktivieren
- Laravel `bootstrap/app.php` konfiguriert bereits `trustProxies(at:'*')`

### 10.5 Sicherheits-Checkliste vor Go-Live

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis mit Passwort; Firewall blockiert öffentlichen 6379
- [ ] Nginx leitet echte IP weiter; `/api/health`-Log zeigt korrekte Client-IP
- [ ] Upload-Verzeichnis blockiert PHP-Ausführung; DB-Konto mit minimalen Rechten
- [ ] `JWT_SECRET`/DB/Redis-Passwörter nur in `.env`, nie hartcodiert
- [ ] Middleware registriert: `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] 3 Supervisor-Programme RUNNING; Logs täglich rotiert
- [ ] Initiales admin/admin123 geändert; tägliches MySQL-Backup + Redis-Persistenz

### 10.6 Skalierungspunkte (100k QPS / 10M Datensätze)

Nginx-Worker-Tuning + Keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), Redis-Persistenz + Sharing über Instanzen, MySQL-Lese/Schreib-Trennung, monatliche RANGE-Partitionen für `messages`/Logs, Sharding von `user_favorites`, Keyset-Tiefpaginierung (`snow_id < cursor`), CDN für statische Assets, zustandslose horizontale Skalierung. Siehe [docs/deploy.md](docs/deploy.md).

## 11. Demokonten

| Client | Konto | Rolle | Hinweise |
|---|---|---|---|
| Admin | admin / admin123 | super-admin | Super-Admin (**Passwort nach Erstlogin ändern**) |
| Admin | demo / demo123 | operator | Eingeschränkte Menüs |
| C-End | user@example.com / password | Regulärer Benutzer | C-End-Login |

## 12. Tests und Validierung

- **Automatisiert**: `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **Datenbank**: `migrate:fresh --seed` → 18 Migrationen + 8 Seeder OK; physische Tabellenkommentare auf allen 18 Geschäftstabellen.
- **Browser**: 22 Admin-Module + C-End PC (1280×900) + H5 (375×812) + C-End-3-Geräte-Mechanismus alle bestanden (siehe TEST_REPORT §17).

## 13. Zugehörige Dokumente

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — Systemübersicht und Struktur (Chinesisch)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — Systemübersicht und Struktur (Englisch)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — Technische Spezifikation V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — Entwicklungsvertrag
- [DELIVERY.md](DELIVERY.md) — Übergabehinweise
- [TEST_REPORT.md](TEST_REPORT.md) — Testbericht
- [docs/deploy.md](docs/deploy.md) — Bereitstellung und Skalierung
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — Entwicklungsdokumente
