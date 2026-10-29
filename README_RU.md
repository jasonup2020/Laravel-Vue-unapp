# Laravel13 API Skeleton (Русская версия)

> Производственный скелет системы управления · Один бэкенд, три клиента
> Полная документация: обзор проекта и структура · требования к окружению · развёртывание

---

## Содержание

1. [Обзор проекта](#1-обзор-проекта)
2. [Ключевые функции](#2-ключевые-функции)
3. [Технологический стек](#3-технологический-стек)
4. [Структура каталогов](#4-структура-каталогов)
5. [Структура базы данных](#5-структура-базы-данных)
6. [Аутентификация и права](#6-аутентификация-и-права)
7. [Единый API-контракт](#7-единый-api-контракт)
8. [Требования к окружению](#8-требования-к-окружению)
9. [Быстрый старт (разработка)](#9-быстрый-старт-разработка)
10. [Продакшн-развёртывание](#10-продакшн-развёртывание)
11. [Демо-аккаунты](#11-демо-аккаунты)
12. [Тестирование и валидация](#12-тестирование-и-валидация)
13. [Связанные документы](#13-связанные-документы)

---

## 1. Обзор проекта

**Laravel13 API Skeleton** — это производственный скелет системы управления на базе PHP 8.3 + Laravel 13 + MySQL + Redis. **Один бэкенд обслуживает три клиента** — консоль администрирования Vue3 и пользовательский клиент uni-app (версия для ПК + мобильная версия H5) — с настоящим разделением frontend/backend и согласованностью по всем каналам.

Аутентификация защищена **JWT с двумя таблицами и двумя охранниками**: обычные пользователи и администраторы полностью изолированы; пароли в `users` и `admins` снабжаются случайной солью (6 символов) и затем хешируются. Права используют **RBAC на уровне действий** (коды `ресурс:действие`) с настраиваемыми правами, ролями и динамическими меню (до 5-го уровня), синхронизируемыми с C-end. Консоль администрирования применяет **единый вход (SSO)** (одна сессия на аккаунт), а C-end поддерживает **до 3 одновременных устройств** — сверх этого самое старое устройство автоматически отключается и попадает в чёрный список. Входы используют графический капчи и составное ограничение скорости (IP/пользователь/отпечаток). Все API возвращают единый контракт `HTTP 200 + body.code` с полным переключением **zh-CN / en**.

Система включает журналы аудита (хранение на драйвере, не в MySQL), IP-чёрный список, идемпотентность, десенсибилизацию ответов, восстановление из корзины (мягкое удаление), предупреждения о медленных запросах, проверки работоспособности и генератор кода. Данные используют snowflake-идентификаторы, ограничение пагинации и полные индексы, рассчитанные на миллионы строк и 100k QPS. Автоматизированные тесты (Pest 66) и многоканальные браузерные тесты проходят все — готово к использованию.

## 2. Ключевые функции

| Категория | Возможность |
|---|---|
| Аутентификация | JWT с двумя таблицами/охранниками (C-end `users` / админ `admins`), изоляция охранников, чёрный список токенов |
| Права | RBAC на уровне действий `{ресурс:действие}`, уровни роль-право-меню, динамическое меню (≤5 уровней) |
| Безопасность | Капчи (Redis одноразовый), составное ограничение (IP/пользователь/отпечаток), солёные пароли, IP-чёрный список |
| Сессии | Единый вход администратора; контроль 3 устройств C-end (отключение старейшего + чёрный список) |
| i18n | Полное переключение zh-CN / en, двуязычный label_en для dicts/permissions, проверка md5-кэша |
| Аудит | Хранение на драйвере (file/Elasticsearch), **не в MySQL**, маскирование паролей |
| Инструменты | Идемпотентность, десенсибилизация, восстановление корзины, предупреждение о медленных запросах, здоровье, генератор кода |
| Масштаб | Snowflake ID, пагинация (page_size≤100), полные индексы, cache-aside |

## 3. Технологический стек

| Слой | Технология |
|---|---|
| Бэкенд | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| Хранилище | MySQL 5.7+/8.0 (БД ju_test) · Redis 5.0+ (скорость/чёрный список/кэш/многоприборность/locale/капчи) |
| Админ | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-end | uni-app (H5 / мини-программа / App) · сборка HBuilderX · ПК-десктоп + H5-мобайл |

## 4. Структура каталогов

### 4.1 Корень проекта

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md / README_DE.md / README_ES.md / README_KR.md / README_RU.md   # Документация проекта (эта серия)
├─ PROJECT_SPEC.md        # Авторитетная техническая спецификация V1.2-Full
├─ SHARED_CONVENTIONS.md  # Единый контракт разработки
├─ DELIVERY.md            # Заметки о передаче (список изменений + базовые показатели)
├─ TEST_REPORT.md         # Отчёт о браузерных тестах (§17 многоканальные тесты)
├─ SYSTEM_SUMMARY.md      # Обзор системы и структура (китайский)
├─ SYSTEM_SUMMARY_EN.md   # Обзор системы и структура (английский)
├─ _cserver.py            # Локальный статический сервер C-end (хостинг H5 + прокси /api к 8000)
├─ backup_*.sql           # Резервные копии БД
├─ backend-laravel13/     # Бэкенд
├─ frontend-vue3/         # Администрирование
├─ frontend-uniapp/       # C-end
└─ docs/                  # deploy/dev-guide/business-rule/test-case/аккаунты/codegen/i18n-sync
```

### 4.2 Бэкенд `backend-laravel13/`

```
app/
├─ Base/                    # Пять базовых классов: BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator; StatusCode/ErrorCode/8 бизнес-перечислений
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP всегда 200 + body.code)
├─ Helpers/                 # хелпер message() + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-end) + Api/Admin/(админ) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 middleware (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # FormRequest User/ + Admin/ по модулям
├─ Models/                  # 14 моделей + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-end) + Admin/(админ) изолированный бизнес-слой
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (слушатель медленных запросов >500ms)

database/
├─ migrations/              # 18 create_* (двуязычные комментарии полей + $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (идемпотентный upsert)
└─ factories/               # 6 фабрик
lang/{zh-CN,en}/            # api.php (~444 ключа, вкл. ui.*) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ маршрута) + channels.php + console.php (4 запланированных задачи)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 файлов Pest
docs/                       # 13 основных документов + extensions/ 8 фрагментов
```

### 4.3 Администрирование `frontend-vue3/` (17 страниц)

`src/views/`: login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
Прочие: `layout/index.vue` (выпадающее меню обновления + меню пользователя), `layout/MenuTree.vue` (кастомная гармошка-сайдбар, 5 уровней), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-end `frontend-uniapp/` (8 страниц)

`pages/`: index (сетка входа на главную + товары), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
Прочие: `App.vue` (media query ПК-десктоп), `index.html` (верхняя панель ПК двуязычная), `common/i18n.js`, `common/request.js`

## 5. Структура базы данных (18 бизнес-таблиц)

| Таблица | Назначение | Ключевые ограничения |
|---|---|---|
| `users` | Пользователи C-end | email unique, salt, мягкое удаление |
| `password_reset_tokens` / `sessions` | Таблицы фреймворка | по умолчанию |
| `admins` | Аккаунты админов | username unique, salt, мягкое удаление |
| `roles` | Роли | code unique, мягкое удаление |
| `permissions` | Права (уровень действия) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | Пивот-таблицы | составной первичный ключ |
| `admin_menus` | Динамические меню админа | parent_id, title_en, мягкое удаление |
| `products` | Пример товара | индекс(status,created_at), (status,price), мягкое удаление |
| `dicts` | Словарь данных | индекс(type,status), label_en, физическое удаление |
| `messages` | Внутренние сообщения | индекс(receiver_type,receiver_id,is_read), мягкое удаление |
| `user_favorites` | Избранное | (user_id,product_id) unique, мягкое удаление |
| `ip_blacklist` | IP-чёрный список | ip unique, физическое удаление |
| `rate_limit_rules` | Правила ограничения скорости | rule_key unique |
| `notices` | Объявления (демо генератора) | — |
| `c_menus` | Меню пользователя C-end | parent_id, title_en, только показ + родитель-ребёнок + i18n |

> Все бизнес-таблицы имеют `snow_id` (открыт); добавлен `$table->comment('таблица - назначение/English')`; журнал аудита **без таблицы** (хранение на драйвере).

## 6. Аутентификация и права

- **JWT с двумя охранниками**: `api` (users) / `admin-api` (admins), payload содержит `guard`, изоляция охранников.
- **Солёный пароль**: `salt varchar(6)`, `SaltedPasswordCast` пишет `Hash(текст+соль)`, 4 проверки через `SaltHasher::check`.
- **Единый вход администратора**: `CheckAdminOnline`, вход `Cache::put('admin_online:{id}', token)`, новый вход отключает старый, старый токен 401 → автоматический переход на страницу входа.
- **C-end 3 устройства**: `MultiDeviceService`, >3 `array_shift` отключает старейшее + `invalidate` в чёрный список; админ может отключить всё.
- **RBAC на уровне действия**: middleware `admin.permission:{code}` точная проверка; кэш прав `admin:permissions:{adminId}` (Redis, простой массив).
- **Динамическое меню**: кэш `admin_menu:tree:{adminId}` (TTL 7 дней), `buildTree` рекурсивно по snow_id, ≤5 уровней; меню C-end в отдельной таблице `c_menus` + публичный двуязычный интерфейс `/api/menus` синхронизирован.

## 7. Единый API-контракт

- **HTTP всегда 200 + `body.code`**: `0` успех / `1` общая ошибка / `1001` параметр / `1002` не аутентифицирован / `1003` нет прав / `1004` не найдено / `1005` валидация / `1006` ограничение скорости / `1007` конфликт / `1008` ошибка капчи / `5000` ошибка сервера (+бизнес-коды 2xxxx).
- **Успех**: `{code:0, msg:"Операция успешна", data:{...}}`; **Ошибка**: `{code:1005, msg:"Ошибка валидации", data:errors}`.
- **Группы интерфейсов**: Публичные (health/captcha/locale/products/dicts/menus/notices); C-end (auth:api + check.user.online: login/refresh/me/messages/favorites); Админ (auth:admin-api + admin.permission:{code}: меню/роли/админы/пользователи/товары/словари/сообщения/избранное/логи/IP/скорость/корзина/c-меню/права/профиль).

## 8. Требования к окружению

| Компонент | Рекомендуется | Проверено | Примечания |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | Расширения: `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | Миграции совместимы с 5.7 (utf8mb4, JSON); без изменений для 8.0 |
| Redis | 6.0+ | **5.0.10** | phpredis; избегать продвинутых команд, не поддерживаемых в 5.0 |
| Nginx | 1.20+ | любой современный | Обратный прокси + SSL + прокси WebSocket (продакшн) |
| Supervisor | 4.x | Linux прод | Демонизирует reverb / queue / schedule; не нужен на Windows dev |
| Composer | 2.x | — | Управление зависимостями |
| Node.js | 18+ | — | Сборка Vite админа |
| HBuilderX | последний | — | Сборка H5 C-end (встроенный node + uniapp-cli-vite) |

**Самопроверка расширений PHP**:

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. Быстрый старт (разработка)

### 9.1 Бэкенд (Windows / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # настройка MySQL/Redis/AUDIT_LOG_DRIVER

# Первичная генерация ключей
php artisan key:generate
php artisan jwt:secret

# Пересборка dev-БД + сид (или migrate --seed)
php artisan migrate:fresh --seed

# Dev-сервер
php artisan serve --host=127.0.0.1 --port=8000

# Отдельные терминалы (опционально): Reverb WebSocket + queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 Администрирование

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (прокси /api к 8000)
npm run build          # продакшн-сборка
```

### 9.3 C-end (сборка H5 через HBuilderX)

```bash
# Использовать встроенный node HBuilderX + CLI @dcloudio/vite-plugin-uni:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<абсолютный путь frontend-uniapp>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# Вывод frontend-uniapp/dist/build/h5; обслуживается _cserver.py + прокси /api к 8000
# Посетить http://127.0.0.1:5174
```

> ПК-десктоп (≥768px): верхняя навигация + центрирование 1200px + 4 колонки товаров; мобильный (<768px): нижняя tabBar + одна колонка.

## 10. Продакшн-развёртывание

### 10.1 Конфигурация `.env` (ключевые параметры)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<сгенерирован jwt:secret>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> Мульти-инстанс должен указывать на **тот же Redis** (скорость/чёрный список/кэш меню/состояние мультиустройств глобально согласованы); **никогда `CACHE_STORE=file` в продакшене**.

### 10.2 Миграция и сид

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 Демонизация процессов (Supervisor, Linux)

Три демона: `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (queue), `php artisan schedule:work` (планировщик). Настроить три программы в `/etc/supervisor/conf.d/laravel13.conf`, затем:

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` проксирует к PHP-FPM с `try_files $uri $uri/ /index.php?$query_string;`
- Передача реального IP: `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (скорость/чёрный список/логи зависят от этого)
- `location /app/` проксирует Reverb (`proxy_set_header Upgrade $http_upgrade;`) для WebSocket
- Laravel `bootstrap/app.php` уже настроен `trustProxies(at:'*')`

### 10.5 Контрольный список безопасности перед запуском

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis с паролем; firewall блокирует публичный 6379
- [ ] Nginx передаёт реальный IP; лог `/api/health` показывает корректный IP клиента
- [ ] Каталог загрузок запрещает выполнение PHP; учётная запись БД с минимальными правами
- [ ] `JWT_SECRET`/БД/Redis только в `.env`, не в коде
- [ ] Middleware зарегистрированы: `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] 3 программы Supervisor RUNNING; ротация логов ежедневно
- [ ] Начальный admin/admin123 изменён; ежедневный бэкап MySQL + персистентность Redis

### 10.6 Точки масштабирования (100k QPS / 10M строк)

Тюнинг воркеров Nginx + keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), персистентность Redis + общий доступ между инстансами, разделение чтения/записи MySQL, месячные RANGE-партиции для `messages`/логов, шардинг `user_favorites`, глубокая пагинация keyset (`snow_id < cursor`), CDN для статики, горизонтальное масштабирование без состояния. См. [docs/deploy.md](docs/deploy.md).

## 11. Демо-аккаунты

| Клиент | Аккаунт | Роль | Примечания |
|---|---|---|---|
| Админ | admin / admin123 | super-admin | Супер-админ (**смените пароль после первого входа**) |
| Админ | demo / demo123 | operator | Ограниченные меню |
| C-end | user@example.com / password | Обычный пользователь | Вход на C-end |

## 12. Тестирование и валидация

- **Автоматизированные**: `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **База данных**: `migrate:fresh --seed` → 18 миграций + 8 сидеров OK; физические комментарии на 18 бизнес-таблицах.
- **Браузер**: 22 модуля админа + C-end ПК (1280×900) + H5 (375×812) + механизм 3 устройств C-end все пройдены (см. TEST_REPORT §17).

## 13. Связанные документы

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — Обзор системы и структура (китайский)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — Обзор системы и структура (английский)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — Техническая спецификация V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — Контракт разработки
- [DELIVERY.md](DELIVERY.md) — Заметки о передаче
- [TEST_REPORT.md](TEST_REPORT.md) — Отчёт о тестах
- [docs/deploy.md](docs/deploy.md) — Развёртывание и масштабирование
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — Документы разработки
