# Laravel13 API Skeleton (Version française)

> Squelette de système de gestion de production · Un backend, trois clients
> Documentation complète : résumé du projet et structure · exigences d'environnement · déploiement

---

## Table des matières

1. [Présentation du projet](#1-présentation-du-projet)
2. [Fonctionnalités clés](#2-fonctionnalités-clés)
3. [Pile technique](#3-pile-technique)
4. [Structure des répertoires](#4-structure-des-répertoires)
5. [Structure de la base de données](#5-structure-de-la-base-de-données)
6. [Authentification et permissions](#6-authentification-et-permissions)
7. [Contrat API unifié](#7-contrat-api-unifié)
8. [Exigences d'environnement](#8-exigences-denvironnement)
9. [Démarrage rapide (développement)](#9-démarrage-rapide-développement)
10. [Déploiement en production](#10-déploiement-en-production)
11. [Comptes de démonstration](#11-comptes-de-démonstration)
12. [Tests et validation](#12-tests-et-validation)
13. [Documents associés](#13-documents-associés)

---

## 1. Présentation du projet

**Laravel13 API Skeleton** est un squelette de système de gestion de production construit sur PHP 8.3 + Laravel 13 + MySQL + Redis. Un backend sert trois clients — une console d'administration Vue3 et un client utilisateur uni-app (version bureau PC + version mobile H5) — avec une véritable séparation frontend/backend et une cohérence multicanal.

L'authentification est sécurisée par **JWT double-table double-garde** : les utilisateurs réguliers et les administrateurs sont totalement isolés ; les mots de passe dans `users` et `admins` sont salés (sel aléatoire de 6 caractères) puis hashés. Les permissions utilisent le **RBAC au niveau action** (codes `ressource:action`) avec permissions, rôles et menus dynamiques configurables (jusqu'au niveau 5) qui se synchronisent avec le C-end. La console d'administration applique le **single sign-on** (un seul session par compte), tandis que le C-end supporte **jusqu'à 3 appareils simultanés** — au-delà, l'appareil le plus ancien est automatiquement déconnecté et mis en liste noire. Les connexions utilisent un captcha d'image et une limitation de débit composite (IP/utilisateur/empreinte). Toutes les API renvoient le contrat unifié `HTTP 200 + body.code`, avec bascule complète **zh-CN / en**.

Le système inclut des journaux d'audit (stockage par pilote, non stockés en MySQL), une liste noire IP, l'idempotence, la désensibilisation des réponses, la restauration par corbeille (suppression logicielle), les alertes de requêtes lentes, les vérifications de santé et un générateur de code. Les données utilisent des IDs snowflake, des limites de pagination et des index complets, conçus pour des millions de lignes et 100k QPS. Les tests automatisés (Pest 66) et les tests navigateur multicanal réussissent tous — prêt à l'emploi.

## 2. Fonctionnalités clés

| Catégorie | Capacité |
|---|---|
| Authentification | JWT double-table double-garde (C-end `users` / admin `admins`), isolation entre gardes, liste noire de tokens |
| Permissions | RBAC au niveau action `{ressource:action}`, hiérarchie rôle-permission-menu, menu dynamique (≤5 niveaux) |
| Sécurité | Captcha image (Redis à usage unique), limitation de débit composite (IP/utilisateur/empreinte), mots de passe salés, liste noire IP |
| Sessions | Single sign-on admin ; contrôle C-end 3 appareils (déconnexion du plus ancien + liste noire) |
| i18n | Bascule complète zh-CN / en, label_en bilingue pour dicts/permissions, validation cache md5 |
| Audit | Stockage par pilote (file/Elasticsearch), **pas en MySQL**, masquage des mots de passe |
| Boîte à outils | Idempotence, désensibilisation, restauration corbeille, alerte requêtes lentes, santé, générateur de code |
| Échelle | ID snowflake, pagination (page_size≤100), index complets, cache-aside |

## 3. Pile technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · `php-open-source-saver/jwt-auth` · `fruitcake/laravel-cors` · `laravel/reverb` (WebSocket) |
| Stockage | MySQL 5.7+/8.0 (db ju_test) · Redis 5.0+ (débit/liste noire/cache/multi-appareil/locale/captcha) |
| Admin | Vue3 · Vite · Pinia · Element Plus · vue-i18n · ECharts |
| C-end | uni-app (H5 / mini-programme / App) · build HBuilderX · bureau PC + mobile H5 |

## 4. Structure des répertoires

### 4.1 Racine du projet

```
laravel13-api-skeleton/
├─ README.md / README_CN.md / README_EN.md / README_JP.md / README_FR.md   # Docs projet (cette série)
├─ PROJECT_SPEC.md        # Spécification technique de référence V1.2-Full
├─ SHARED_CONVENTIONS.md  # Contrat de développement unique
├─ DELIVERY.md            # Notes de livraison (liste des changements + ligne de base)
├─ TEST_REPORT.md         # Rapport de tests navigateur (§17 tests multicanal)
├─ SYSTEM_SUMMARY.md      # Résumé et structure système (chinois)
├─ SYSTEM_SUMMARY_EN.md   # Résumé et structure système (anglais)
├─ _cserver.py            # Serveur statique local C-end (héberge H5 + proxy /api vers 8000)
├─ backup_*.sql           # Sauvegardes base de données
├─ backend-laravel13/     # Backend
├─ frontend-vue3/         # Administration
├─ frontend-uniapp/       # C-end
└─ docs/                  # deploy/dev-guide/business-rule/test-case/comptes/codegen/i18n-sync
```

### 4.2 Backend `backend-laravel13/`

```
app/
├─ Base/                    # Cinq classes de base : BaseController/BaseService/BaseRepository/BaseModel/BaseMiddleware
├─ Console/Commands/        # BaseCommand/MakeModuleCommand/LangSyncCommand
├─ Contracts/  Enums/       # LangSyncTranslator ; StatusCode/ErrorCode/8 énumérations métier
├─ Exceptions/              # ApiException + ApiExceptionHandler (HTTP toujours 200 + body.code)
├─ Helpers/                 # helper message() + common.php (snow_id/client_ip/fingerprint/mask_text)
├─ Http/
│  ├─ Controllers/          # Api/(C-end) + Api/Admin/(admin) + Locale/Captcha/CMenu/Health
│  ├─ Middleware/           # 10 middleware (IpBlacklist/LangSwitch/HandleCors/CheckAdminOnline/...)
│  └─ Requests/             # FormRequests User/ + Admin/ par module
├─ Models/                  # 14 modèles + Traits/HasSnowId + Casts/SaltedPasswordCast
├─ Services/                # User/(C-end) + Admin/(admin) couche métier isolée
├─ Support/                 # Snowflake/SaltHasher/CaptchaService/LangSync
└─ Providers/               # AppServiceProvider (écoute requêtes lentes >500ms)

database/
├─ migrations/              # 18 create_* (commentaires bilingues des champs + $table->comment)
├─ seeders/                 # Permission/Role/Admin/Menu/Demo/User/CMenu/RateLimitRule (upsert idempotent)
└─ factories/               # 6 factories
lang/{zh-CN,en}/            # api.php (~444 clés incl. ui.*) + notice.php
config/                     # auth/jwt/cors/broadcasting/reverb/audit/lang_sync/app
routes/                     # api.php (94+ routes) + channels.php + console.php (4 tâches planifiées)
openapi/openapi.yaml        # 78 operations
tests/Feature/Api/          # 17 fichiers Pest
docs/                       # 13 docs principaux + extensions/ 8 extraits
```

### 4.3 Administration `frontend-vue3/` (17 pages)

`src/views/` : login / dashboard / user / product / dict / admin / role / menu / permission / message / operation-log / ip-blacklist / rate-limit / favorite / recycle-bin / c-menu / profile
Autres : `layout/index.vue` (menu déroulant de rafraîchissement + menu utilisateur), `layout/MenuTree.vue` (barre latérale accordéon, 5 niveaux), `api/*`, `i18n/index.js`, `store` (Pinia), `router/index.js`

### 4.4 C-end `frontend-uniapp/` (8 pages)

`pages/` : index (grille d'entrée d'accueil + produits), product/list, product/detail, dict/index, favorite/index, message/index, mine/index, login/index
Autres : `App.vue` (media query bureau PC), `index.html` (barre supérieure PC bilingue), `common/i18n.js`, `common/request.js`

## 5. Structure de la base de données (18 tables métier)

| Table | Usage | Contraintes clés |
|---|---|---|
| `users` | Utilisateurs C-end | email unique, salt, suppression logicielle |
| `password_reset_tokens` / `sessions` | Tables framework | par défaut |
| `admins` | Comptes admin | username unique, salt, suppression logicielle |
| `roles` | Rôles | code unique, suppression logicielle |
| `permissions` | Permissions (niveau action) | code unique, group/action/type/label_en |
| `admin_role` / `role_permission` / `role_menu` | Tables pivots | clé primaire composite |
| `admin_menus` | Menus dynamiques admin | parent_id, title_en, suppression logicielle |
| `products` | Exemple produit | index(status,created_at), (status,price), suppression logicielle |
| `dicts` | Dictionnaire de données | index(type,status), label_en, suppression physique |
| `messages` | Messages internes | index(receiver_type,receiver_id,is_read), suppression logicielle |
| `user_favorites` | Favoris | (user_id,product_id) unique, suppression logicielle |
| `ip_blacklist` | Liste noire IP | ip unique, suppression physique |
| `rate_limit_rules` | Règles de débit | rule_key unique |
| `notices` | Annonces (démo générateur) | — |
| `c_menus` | Menus utilisateur C-end | parent_id, title_en, affichage + parent-enfant + i18n uniquement |

> Toutes les tables métier ont `snow_id` (exposé) ; `$table->comment('table - usage/English')` ajouté ; journal d'audit **sans table** (stockage par pilote).

## 6. Authentification et permissions

- **JWT double-garde** : `api` (users) / `admin-api` (admins), payload porte `guard`, isolé entre gardes.
- **Mot de passe salé** : `salt varchar(6)`, `SaltedPasswordCast` écrit `Hash(texte+sel)`, 4 vérifications via `SaltHasher::check`.
- **Single sign-on admin** : `CheckAdminOnline`, connexion `Cache::put('admin_online:{id}', token)`, nouvelle connexion déconnecte l'ancienne, ancien token 401 → redirection automatique vers login.
- **C-end 3 appareils** : `MultiDeviceService`, >3 `array_shift` déconnecte le plus ancien + `invalidate` vers liste noire ; l'admin peut tout déconnecter.
- **RBAC niveau action** : middleware `admin.permission:{code}` vérification exacte ; cache permissions `admin:permissions:{adminId}` (Redis, tableau simple).
- **Menu dynamique** : cache `admin_menu:tree:{adminId}` (TTL 7 jours), `buildTree` récursif par snow_id, ≤5 niveaux ; menus C-end dans `c_menus` séparé + interface publique bilingue `/api/menus` synchronisée.

## 7. Contrat API unifié

- **HTTP toujours 200 + `body.code`** : `0` succès / `1` erreur générique / `1001` paramètre / `1002` non authentifié / `1003` pas de permission / `1004` introuvable / `1005` validation / `1006` limite de débit / `1007` conflit / `1008` erreur captcha / `5000` erreur serveur (+codes métier 2xxxx).
- **Succès** : `{code:0, msg:"Opération réussie", data:{...}}` ; **Échec** : `{code:1005, msg:"Validation échouée", data:errors}`.
- **Groupes d'interfaces** : Public (health/captcha/locale/products/dicts/menus/notices) ; C-end (auth:api + check.user.online : login/refresh/me/messages/favorites) ; Admin (auth:admin-api + admin.permission:{code} : menus/rôles/admins/utilisateurs/produits/dicts/messages/favoris/logs/IP/débit/corbeille/c-menus/permissions/profil).

## 8. Exigences d'environnement

| Composant | Recommandé | Compatible vérifié | Notes |
|---|---|---|---|
| PHP | 8.3+ | **8.3.8** | Extensions : `pdo_mysql`, `redis`, `openssl`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `tokenizer`, `xml` |
| MySQL | 8.0 | **5.7.38** | Migrations compatibles 5.7 (utf8mb4, JSON) ; aucun changement pour 8.0 |
| Redis | 6.0+ | **5.0.10** | phpredis ; éviter commandes avancées non supportées en 5.0 |
| Nginx | 1.20+ | tout courant | Proxy inverse + SSL + proxy WebSocket (prod) |
| Supervisor | 4.x | Linux prod | Démonise reverb / queue / schedule ; inutile sur Windows dev |
| Composer | 2.x | — | Gestion des dépendances |
| Node.js | 18+ | — | Build Vite admin |
| HBuilderX | dernier | — | Build H5 C-end (node intégré + uniapp-cli-vite) |

**Auto-vérification des extensions PHP** :

```powershell
& "D:\BtSoft\php\83\php.exe" -m | Select-String -Pattern "redis|pdo_mysql|mbstring|openssl|bcmath|fileinfo|curl"
```

## 9. Démarrage rapide (développement)

### 9.1 Backend (Windows / Linux)

```bash
cd laravel13-api-skeleton/backend-laravel13
cp .env.example .env                # configurer MySQL/Redis/AUDIT_LOG_DRIVER

# Clés de première génération
php artisan key:generate
php artisan jwt:secret

# Reconstruction DB dev + seed (ou migrate --seed)
php artisan migrate:fresh --seed

# Serveur de dev
php artisan serve --host=127.0.0.1 --port=8000

# Terminaux séparés (optionnel) : Reverb WebSocket + queue
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:work
```

### 9.2 Administration

```bash
cd laravel13-api-skeleton/frontend-vue3
npm install
npm run dev            # http://127.0.0.1:5173 (/api proxifié vers 8000)
npm run build          # build de production
```

### 9.3 C-end (build H5 via HBuilderX)

```bash
# Utiliser le node intégré de HBuilderX + CLI @dcloudio/vite-plugin-uni :
#   UNI_PLATFORM=h5 UNI_INPUT_DIR=<chemin absolu frontend-uniapp>
#   node node_modules/@dcloudio/vite-plugin-uni/bin/uni.js build --platform h5
# Sortie frontend-uniapp/dist/build/h5 ; servi par _cserver.py + proxy /api vers 8000
# Visiter http://127.0.0.1:5174
```

> Bureau PC (≥768px) : barre de navigation supérieure + centrage 1200px + produits 4 colonnes ; mobile (<768px) : tabBar inférieure + colonne unique.

## 10. Déploiement en production

### 10.1 Configuration `.env` (clés principales)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=127.0.0.1  DB_DATABASE=ju_test  DB_USERNAME=xxx  DB_PASSWORD=xxx
REDIS_CLIENT=phpredis  REDIS_HOST=127.0.0.1  REDIS_PASSWORD=xxx  REDIS_PORT=6379
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis  BROADCAST_CONNECTION=reverb
JWT_SECRET=<généré par jwt:secret>
AUDIT_LOG_DRIVER=file          # file | elasticsearch
AUDIT_RETENTION_DAYS=90
```

> Multi-instance doit pointer vers le **même Redis** (débit/liste noire/cache menu/état multi-appareil globalement cohérent) ; **jamais `CACHE_STORE=file` en production**.

### 10.2 Migration et seed

```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan config:clear
php artisan route:cache && config:cache && event:cache
```

### 10.3 Démonisation des processus (Supervisor, Linux)

Trois démons : `php artisan reverb:start` (WebSocket), `php artisan queue:work --tries=3 --timeout=90` (queue), `php artisan schedule:work` (planificateur). Configurer trois programmes dans `/etc/supervisor/conf.d/laravel13.conf` puis :

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel13-reverb laravel13-queue laravel13-schedule
```

### 10.4 Nginx

- `location /` proxifie vers PHP-FPM avec `try_files $uri $uri/ /index.php?$query_string;`
- Transmettre l'IP réelle : `fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;` (débit/liste noire/logs en dépendent)
- `location /app/` proxifie Reverb (`proxy_set_header Upgrade $http_upgrade;`) pour activer WebSocket
- Laravel `bootstrap/app.php` configure déjà `trustProxies(at:'*')`

### 10.5 Checklist de sécurité avant mise en ligne

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Redis avec mot de passe ; pare-feu bloque le 6379 public
- [ ] Nginx transmet l'IP réelle ; le log `/api/health` montre le bon IP client
- [ ] Répertoire d'upload interdit l'exécution PHP ; compte DB moindre privilège
- [ ] `JWT_SECRET`/DB/Redis uniquement dans `.env`, jamais codés en dur
- [ ] Middleware enregistrés : `ip.blacklist`, `api.composite.rate`, `lang`
- [ ] 3 programmes Supervisor RUNNING ; journaux rotatifs quotidiens
- [ ] admin/admin123 initial modifié ; sauvegarde MySQL quotidienne + persistance Redis

### 10.6 Points d'échelle (100k QPS / 10M lignes)

Tuning des workers Nginx + keepalive, PHP-FPM `max_children`/OpCache (`validate_timestamps=0`), persistance Redis + partage inter-instances, séparation lecture/écriture MySQL, partitions RANGE mensuelles pour `messages`/logs, sharding `user_favorites`, pagination profonde keyset (`snow_id < cursor`), CDN pour les actifs statiques, mise à l'échelle horizontale sans état. Voir [docs/deploy.md](docs/deploy.md).

## 11. Comptes de démonstration

| Client | Compte | Rôle | Notes |
|---|---|---|---|
| Admin | admin / admin123 | super-admin | Super admin (**changez le mot de passe après la première connexion**) |
| Admin | demo / demo123 | operator | Menus limités |
| C-end | user@example.com / password | Utilisateur régulier | Connexion C-end |

## 12. Tests et validation

- **Automatisés** : `vendor/bin/pest` → **66 passed / 310 assertions** (Auth/AdminSso/Captcha/CMenu/Desensitize/Favorite/GuardIsolation/Idempotency/IpBlacklist/Locale/Message/MultiDevice/ProductDict/RateLimit/RbacAndMenu/Recycle).
- **Base de données** : `migrate:fresh --seed` → 18 migrations + 8 seeders OK ; commentaires physiques sur les 18 tables métier.
- **Navigateur** : 22 modules admin + C-end PC (1280×900) + H5 (375×812) + mécanisme 3 appareils C-end tous réussis (voir TEST_REPORT §17).

## 13. Documents associés

- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) — Résumé et structure système (chinois)
- [SYSTEM_SUMMARY_EN.md](SYSTEM_SUMMARY_EN.md) — Résumé et structure système (anglais)
- [PROJECT_SPEC.md](PROJECT_SPEC.md) — Spécification technique V1.2-Full
- [SHARED_CONVENTIONS.md](SHARED_CONVENTIONS.md) — Contrat de développement
- [DELIVERY.md](DELIVERY.md) — Notes de livraison
- [TEST_REPORT.md](TEST_REPORT.md) — Rapport de tests
- [docs/deploy.md](docs/deploy.md) — Déploiement et mise à l'échelle
- [docs/dev-guide.md](docs/dev-guide.md) / [docs/business-rule.md](docs/business-rule.md) / [docs/demo-accounts.md](docs/demo-accounts.md) / [docs/codegen.md](docs/codegen.md) / [docs/i18n-sync.md](docs/i18n-sync.md) — Docs de développement
