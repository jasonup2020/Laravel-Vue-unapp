# docker-compose 开发编排模板（docker-compose.md）

> 按需复制。一键起 PHP-FPM + Nginx + MySQL8 + Redis + Reverb，适合本地/CI 开发。生产建议分开部署。

---

## 1. 场景说明

- 新成员 clone 仓库后 `docker compose up -d` 即得完整环境；
- MySQL 8.0、Redis 7、PHP 8.3-FPM、Nginx、Reverb WebSocket 一键起；
- 数据卷持久化 MySQL/Redis 数据与 storage 目录。

---

## 2. 配置文件

### 2.1 `docker-compose.yml`（放在工程根 `laravel13-api-skeleton/`）

```yaml
version: "3.9"

services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile
    container_name: laravel13-app
    working_dir: /var/www/backend-laravel13
    volumes:
      - ./backend-laravel13:/var/www/backend-laravel13
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini
    depends_on:
      - mysql
      - redis
    networks:
      - skeleton

  nginx:
    image: nginx:1.27-alpine
    container_name: laravel13-nginx
    ports:
      - "8000:80"
    volumes:
      - ./backend-laravel13:/var/www/backend-laravel13
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - skeleton

  mysql:
    image: mysql:8.0
    container_name: laravel13-mysql
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    environment:
      MYSQL_DATABASE: ju_test
      MYSQL_USER: ju_test
      MYSQL_PASSWORD: ju_test_pwd
      MYSQL_ROOT_PASSWORD: root_pwd
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - skeleton

  redis:
    image: redis:7-alpine
    container_name: laravel13-redis
    command: redis-server --appendonly yes
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - skeleton

  reverb:
    build:
      context: .
      dockerfile: docker/Dockerfile
    container_name: laravel13-reverb
    working_dir: /var/www/backend-laravel13
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    volumes:
      - ./backend-laravel13:/var/www/backend-laravel13
    depends_on:
      - app
      - redis
    ports:
      - "8080:8080"
    networks:
      - skeleton

  queue:
    build:
      context: .
      dockerfile: docker/Dockerfile
    container_name: laravel13-queue
    working_dir: /var/www/backend-laravel13
    command: php artisan queue:work --tries=3 --timeout=90
    volumes:
      - ./backend-laravel13:/var/www/backend-laravel13
    depends_on:
      - app
      - redis
    networks:
      - skeleton

networks:
  skeleton:
    driver: bridge

volumes:
  mysql_data:
  redis_data:
```

### 2.2 `docker/Dockerfile`

```dockerfile
FROM php:8.3-fpm

# 系统依赖
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend-laravel13
```

### 2.3 `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/backend-laravel13/public;
    index index.php;

    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 2.4 `docker/php/php.ini`

```ini
memory_limit=256M
upload_max_filesize=20M
post_max_size=20M
date.timezone=Asia/Shanghai
```

---

## 3. 使用步骤

```bash
# 1. 起全部服务
docker compose up -d

# 2. 首次进 app 容器装依赖 + 迁移
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed

# 3. 访问
# API:       http://localhost:8000/api/health
# Reverb:    ws://localhost:8080/app/<key>
```

---

## 4. 注意事项

- `.env` 里 DB host 改为 `mysql`、Redis host 改为 `redis`（compose 服务名）；
- 本模板为**开发**用，MySQL 8.0 与生产实测 5.7.38 略有差异，迁移写法已兼容；
- 生产不要把 MySQL/Redis 端口暴露到公网，不要用默认弱密码；
- Windows 下 volumes 挂载性能较差，WSL2 后端最佳；
- 不要在容器里跑 `php artisan serve`，统一由 nginx+fpm 对外。
