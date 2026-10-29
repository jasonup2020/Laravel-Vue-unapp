# 演示账号 / Demo Accounts

> 首次 `php artisan migrate --seed` 后可用 / Available after first `php artisan migrate --seed`

## C 端普通用户 / C-end User

| 字段 | 值 |
|---|---|
| 邮箱 / Email | `user@example.com` |
| 密码 / Password | `password` |
| 名称 / Name | 示例用户 |

登录接口：`POST /api/login`

## 管理端 / Backend Admin

| 账号 | 密码 | 角色 | 说明 |
|---|---|---|---|
| `admin` | `admin123` | super-admin | 超级管理员，全部权限 |
| `demo` | `demo123` | operator | 演示管理员，仅商品+字典权限 |

登录接口：`POST /api/admin/login`
