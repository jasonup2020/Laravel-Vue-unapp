# Laravel13 API Skeleton (Versión en español)

> Esqueleto de sistema de gestión de producción · Un backend, tres clientes
> Documentación completa: resumen del proyecto y estructura · requisitos de entorno · implementación

---

## Tabla de contenidos

1. [Introducción](#1-introducción)
2. [Funcionalidades clave](#2-funcionalidades-clave)
3. [Pila tecnológica](#3-pila-tecnológica)
4. [Estructura de directorios](#4-estructura-de-directorios)
5. [Estructura de la base de datos](#5-estructura-de-la-base-de-datos)
6. [Autenticación y permisos](#6-autenticación-y-permisos)
7. [Contrato API unificado](#7-contrato-api-unificado)
8. [Requisitos de entorno](#8-requisitos-de-entorno)
9. [Inicio rápido (desarrollo)](#9-inicio-rápido-desarrollo)
10. [Implementación en producción](#10-implementación-en-producción)
11. [Cuentas de demostración](#11-cuentas-de-demostración)
12. [Pruebas y validación](#12-pruebas-y-validación)
13. [Documentos relacionados](#13-documentos-relacionados)

---

## 1. Introducción

**Laravel13 API Skeleton** es un esqueleto de sistema de gestión de producción construido con PHP 8.3 + Laravel 13 + MySQL + Redis. Un backend sirve a tres clientes — una consola de administración Vue3 y un cliente de usuario uni-app (versión escritorio PC + versión móvil H5) — con verdadera separación frontend/backend y coherencia multicanal.

La autenticación está asegurada con **JWT de doble tabla y doble guardia**: los usuarios regulares y los administradores están totalmente aislados; las contraseñas en `users` y `admins` se salan (sal aleatoria de 6 caracteres) y luego se hashean. Los permisos usan **RBAC a nivel de acción** (códigos `recurso:acción`) con permisos, roles y menús dinámicos configurables (hasta el nivel 5) que se sincronizan con el C-end. La consola de administración aplica **inicio de sesión único** (una sesión por cuenta), mientras que el C-end admite **hasta 3 dispositivos simultáneos** — más allá, el dispositivo más antiguo se desconecta automáticamente y se pone en lista negra. Los inicios de sesión usan un captcha de imagen y una limitación de velocidad compuesta (IP/usuario/huella). Todas las API devuelven el contrato unificado `HTTP 200 + body.code`, con cambio completo **zh-CN / en**.

El sistema incluye registros de auditoría (almacenamiento por controlador, no en MySQL), lista negra IP, idempotencia, desensibilización de respuestas, restauración por papelera (borrado lógico), alertas de consultas lentas, comprobaciones de salud y un generador de código. Los datos usan IDs snowflake, límites de paginación e índices completos, diseñados para millones de filas y 100k QPS. Las pruebas automatizadas (Pest 66) y las pruebas de navegador multicanal pasan todas — listo para usar.

## 2. Funcionalidades clave

| Categoría | Capacidad |
|---|---|
| Autenticación | JWT de doble tabla/guardia (C-end `users` / admin `admins`), aislamiento entre guardias, lista negra de tokens |
| Permisos | RBAC a nivel de acción `{recurso:acción}`, niveles rol-permiso-menú, menú dinámico (≤5 niveles) |
| Seguridad | Captcha de imagen (Redis de un solo uso), limitación compuesta (IP/usuario/huella), contraseñas saladas, lista negra IP |
| Sesiones | Inicio de sesión único admin; control de 3 dispositivos C-end (desconexión del más antiguo + lista negra) |
| i18n | Cambio completo zh-CN / en, label_en bilingüe para dicts/permissions, validación de caché md5 |
| Auditoría | Almacenamiento por controlador (file/Elasticsearch), **no en MySQL**, enmascaramiento de contraseñas |
| Herramientas | Idempotencia, desensibilización, restauración de papelera, alerta de consultas lentas, salud, generador de código |
| Escala | ID snowflake, paginación (page_size≤100), índices completos, cache-aside |

## 3. Pila tecnológica

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| Almacenamiento | MySQL 5.7+/8.0 (db ju_test) · Redis 5.0+ (velocidad/lista negra/caché/multidispositivo/locale/captcha) |
| Admin | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-end | uni-app (H5 / mini-programa / App) · build HBuilderX · escritorio PC + móvil H5 |

## 4. Estructura de directorios

### 4.1 Raíz del proyecto

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md / README_DE.md / README_ES.md   # Docs del proyecto (esta serie)
├─ PROJECT_SPEC.md        # Especificación técnica de referencia V1.2-Full
├─ SHARED_CONVENTIONS.md  # Contrato de desarrollo único
├─ DELIVERY.md            # Notas de entrega (lista de cambios + línea base)
├─ TEST_REPORT.md         # Informe de pruebas de navegador (§17 pruebas multicanal)
├─ SYSTEM_SUMMARY.md      # Resumen y estructura del sistema (chino)
├─ SYSTEM_SUMMARY_EN.md   # Resumen y estructura del sistema (inglés)
├─ _cserver.py            # Servidor estático local C-end (aloja H5 + proxy /api a 8000)
├─ backup_*.sql           # Copias de seguridad de la base de datos
├─ backend-laravel13/     # Backend
├─ frontend-vue3/         # Administración
├─ frontend-uniapp/       # C-end
└─ docs/                  # deploy/dev-guide/business-rule/test-case/cuentas/codegen/i18n-sync
```

### 4.2 Backend `backend-laravel13/`

```
app/
├─ Base/                    # Cinco clases base: BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator; StatusCode/ErrorCode/8 enumeraciones de negocio
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP siempre 200 + body.code)
├─ Helpers/                 # helper message() + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-end) + Api/Admin/(admin) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 middleware (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # FormRequests User/ + Admin/ por módulo
├─ Models/                  # 14 modelos + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-end) + Admin/(admin) capa de negocio aislada
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (escucha consultas lentas >500ms)

database/
├─ migrations/              # 18 create_* (comentarios bilingües de campos + $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (upsert idempotente)
└─ factories/               # 6 factories
lang/{zh-CN,en}/            # api.php (~444 claves incl. ui.*) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ rutas) + channels.php + console.php (4 tareas programadas)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 archivos Pest
docs/                       # 13 documentos principales + extensions/ 8 fragmentos
```

### 4.3 Administración `frontend-vue3/` (17 páginas)

`src/views/`: login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
Otros: `layout/index.vue` (menú desplegable de actualización + menú de usuario), `layout/MenuTree.vue` (barra lateral acordeón, 5 niveles), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-end `frontend-uniapp/` (8 páginas)

`pages/`: index (cuadrícula de entrada de inicio + productos), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
Otros: `App.vue` (media query de escritorio PC), `index.html` (barra superior PC bilingüe), `common/i18n.js`, `common/request.js`

## 5. Estructura de la base de datos (18 tablas de negocio)

| Tabla | Uso | Restricciones clave |
|---|---|---|
| `users` | Usuarios C-end | email unique, salt, borrado lógico |
| `password_reset_tokens` / `sessions` | Tablas del framework | predeterminado |
| `admins` | Cuentas admin | username unique, salt, borrado lógico |
| `roles` | Roles | code unique, borrado lógico |
| `permissions` | Permisos (nivel de acción) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | Tablas pivote | clave primaria compuesta |
| `admin_menus` | Menús dinámicos admin | parent_id, title_en, borrado lógico |
| `products` | Ejemplo de producto | índice(status,created_at), (status,price), borrado lógico |
| `dicts` | Diccionario de datos | índice(type,status), label_en, borrado físico |
| `messages` | Mensajes internos | índice(receiver_type,receiver_id,is_read), borrado lógico |
| `user_favorites` | Favoritos | (user_id,product_id) unique, borrado lógico |
| `ip_blacklist` | Lista negra IP | ip unique, borrado físico |
| `rate_limit_rules` | Reglas de velocidad | rule_key unique |
| `notices` | Avisos (demo de generador) | — |
| `c_menus` | Menús de usuario C-end | parent_id, title_en, solo mostrar + padre-hijo + i18n |

> Todas las tablas de negocio tienen `snow_id` (expuesto); `$table->comment('tabla - uso/English')` añadido; el registro de auditoría **no tiene tabla** (almacenamiento por controlador).

## 6. Autenticación y permisos

- **JWT de doble guardia**: `api` (users) / `admin-api` (admins), el payload lleva `guard`, aislado entre guardias.
- **Contraseña salada**: `salt varchar(6)`, `SaltedPasswordCast` escribe `Hash(texto+sal)`, 4 verificaciones vía `SaltHasher::check`.
- **Inicio de sesión único admin**: `CheckAdminOnline`, inicio `Cache::put('admin_online:{id}', token)`, un nuevo inicio desconecta el antiguo, token antiguo 401 → redirección automática al login.
- **C-end 3 dispositivos**: `MultiDeviceService`, >3 `array_shift` desconecta el más antiguo + `invalidate` a lista negra; el admin puede desconectarlo todo.
- **RBAC a nivel de acción**: middleware `admin.permission:{code}` verificación exacta; caché de permisos `admin:permissions:{adminId}` (Redis, matriz simple).
- **Menú dinámico**: caché `admin_menu:tree:{adminId}` (TTL 7 días), `buildTree` recursivo por snow_id, ≤5 niveles; menús C-end en `c_menus` separado + interfaz pública bilingüe `/api/menus` sincronizada.

## 7. Contrato API unificado

- **HTTP siempre 200 + `body.code`**: `0` éxito / `1` error genérico / `1001` parámetro / `1002` no autenticado / `1003` sin permiso / `1004` no encontrado / `1005` validación / `1006` límite de velocidad / `1007` conflicto / `1008` error de captcha / `5000` error de servidor (+códigos de negocio 2xxxx).
- **Éxito**: `{code:0, msg:"Operación exitosa", data:{...}}`; **Error**: `{code:1005, msg:"Validación fallida", data:errors}`.
- **Grupos de interfaces**: Público (health/captcha/locale/products/dicts/menus/notices); C-end (auth:api + check.user.online: login/refresh/me/messages/favorites); Admin (auth:admin-api + admin.permission:{code}: menús/roles/admins/usuarios/productos/dicts/mensajes/favoritos/logs/IP/velocidad/papelera/c-menus/permisos/perfil).

## 8. Requisitos de entorno

| Componente | Recomendado | Compatible verificado | Notas |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | Extensiones: `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | Migraciones compatibles con 5.7 (utf8mb4, JSON); sin cambios para 8.0 |
| Redis | 6.0+ | **5.0.10** | phpredis; evitar comandos avanzados no compatibles en 5.0 |
| Nginx | 1.20+ | cualquier actual | Proxy inverso + SSL + proxy WebSocket (prod) |
| Supervisor | 4.x | Linux prod | Daemoniza reverb / queue / schedule; innecesario en Windows dev |
| Composer | 2.x | — | Gestión de dependencias |
| Node.js | 18+ | — | Build Vite admin |
| HBuilderX | último | — | Build H5 C-end (node integrado + uniapp-cli-vite) |

**Autoverificación de extensiones PHP**:

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. Inicio rápido (desarrollo)

### 9.1 Backend (Windows / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # configurar MySQL/Redis/AUDIT_LOG_DRIVER

# Claves de primera generación
php artisan key:generate
php artisan jwt:secret

# Reconstrucción DB dev + seed (o migrate --seed)
php artisan migrate:fresh --seed

# Servidor de desarrollo
php artisan serve --host=127.0.0.1 --port=8000

# Terminales separadas (opcional): Reverb WebSocket + queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 Administración

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (/api proxy a 8000)
npm run build          # build de producción
```

### 9.3 C-end (build H5 vía HBuilderX)

```bash
# Usar el node integrado de HBuilderX + CLI @dcloudio/vite-plugin-uni:
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<ruta absoluta frontend-uniapp>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# Salida frontend-uniapp/dist/build/h5; servida por _cserver.py + proxy /api a 8000
# Visitar http://127.0.0.1:5174
```

> Escritorio PC (≥768px): barra de navegación superior + centrado 1200px + productos 4 columnas; móvil (<768px): tabBar inferior + columna única.

## 10. Implementación en producción

### 10.1 Configuración `.env` (claves principales)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<generado por jwt:secret>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> Multi-instancia debe apuntar al **mismo Redis** (velocidad/lista negra/caché de menú/estado multidispositivo globalmente coherente); **nunca `CACHE_STORE=file` en producción**.

### 10.2 Migración y seed

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 Daemonización de procesos (Supervisor, Linux)

Tres demonios: `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (queue), `php artisan schedule:work` (planificador). Configurar tres programas en `/etc/supervisor/conf.d/laravel13.conf` luego:

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` proxifica a PHP-FPM con `try_files $uri $uri/ /index.php?$query_string;`
- Transmitir IP real: `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (velocidad/lista negra/logs dependen de ello)
- `location /app/` proxifica Reverb (`proxy_set_header Upgrade $http_upgrade;`) para activar WebSocket
- Laravel `bootstrap/app.php` ya configura `trustProxies(at:'*')`

### 10.5 Lista de verificación de seguridad antes del lanzamiento

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis con contraseña; firewall bloquea el 6379 público
- [ ] Nginx transmite IP real; el log `/api/health` muestra el IP cliente correcto
- [ ] Directorio de carga prohíbe la ejecución PHP; cuenta DB con mínimo privilegio
- [ ] `JWT_SECRET`/DB/Redis solo en `.env`, nunca codificados en duro
- [ ] Middleware registrados: `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] 3 programas Supervisor RUNNING; logs rotativos diarios
- [ ] admin/admin123 inicial modificado; copia MySQL diaria + persistencia Redis

### 10.6 Puntos de escala (100k QPS / 10M filas)

Ajuste de workers Nginx + keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), persistencia Redis + compartición entre instancias, separación lectura/escritura MySQL, particiones RANGE mensuales para `messages`/logs, sharding de `user_favorites`, paginación profunda keyset (`snow_id < cursor`), CDN para activos estáticos, escala horizontal sin estado. Ver [docs/deploy.md](docs/deploy.md).

## 11. Cuentas de demostración

| Cliente | Cuenta | Rol | Notas |
|---|---|---|---|
| Admin | admin / admin123 | super-admin | Super admin (**cambie la contraseña tras el primer inicio**) |
| Admin | demo / demo123 | operator | Menús limitados |
| C-end | user@example.com / password | Usuario regular | Inicio de sesión C-end |

## 12. Pruebas y validación

- **Automatizadas**: `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **Base de datos**: `migrate:fresh --seed` → 18 migraciones + 8 seeders OK; comentarios físicos en las 18 tablas de negocio.
- **Navegador**: 22 módulos admin + C-end PC (1280×900) + H5 (375×812) + mecanismo 3 dispositivos C-end todos aprobados (ver TEST_REPORT §17).

## 13. Documentos relacionados

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — Resumen y estructura del sistema (chino)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — Resumen y estructura del sistema (inglés)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — Especificación técnica V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — Contrato de desarrollo
- [DELIVERY.md](DELIVERY.md) — Notas de entrega
- [TEST_REPORT.md](TEST_REPORT.md) — Informe de pruebas
- [docs/deploy.md](docs/deploy.md) — Implementación y escala
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — Documentos de desarrollo
