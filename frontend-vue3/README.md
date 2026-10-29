# Laravel13 管理后台前端（Vue3 + Vite + Pinia + Element-Plus）

配套 `laravel13-api-skeleton` 的 B 端管理后台模板。登录后按后端 `my-tree` 接口动态渲染侧边栏菜单。

## 环境要求

- Node.js >= 18（推荐 v20+；本骨架在 Node v24 下验证）
- npm >= 9

## 启动步骤

```bash
# 1. 安装依赖
npm install --no-audit --no-fund

# 2. 启动开发服务（默认 http://localhost:5173）
npm run dev

# 3. 生产构建
npm run build
```

开发服务已在 `vite.config.js` 配置代理：

- `/api`  → `http://localhost:8000`（Laravel 后端，`php artisan serve`）
- `/broadcasting` → `http://localhost:8000`（Reverb WebSocket，如需）

## 对接后端说明

- 后端地址：本地 `php artisan serve --port=8000`
- 登录接口：`POST /api/admin/login`（账号/密码），成功后返回 `{ token, expires_in }`
- 登录成功后自动调用 `GET /api/admin/menu/my-tree` 拉取当前管理员可见菜单树，渲染侧边栏
- axios 实例统一注入：
  - `Authorization: Bearer <token>`
  - `Accept-Language: zh-CN`（后端 `LangSwitch` 中间件识别，可选 `en`）
  - `X-Client-Fingerprint`（本地持久化 uuid，供后端复合限流）
  - 写请求（POST/PUT/DELETE）自动携带 `X-Idempotency-Key`
- 统一响应结构 `{ code, msg, data }`（后端 HTTP 一律 200，按 body.code 判断）：
  - `code === 0` 成功，调用方用 `res.data` 取业务数据
  - `code !== 0` 时 ElMessage 弹出 `msg`；`401` 清 token 跳登录；`403/422/429` 分别提示

## 多语言（i18n）接入说明

### 接口契约

| 接口 | 方法 | 鉴权 | 说明 |
|---|---|---|---|
| `/api/locale/{lang}` | GET | 无 | 拉取全量语言包；返回 `data: { lang, md5, items:{key:value} }` |
| `/api/locale/{lang}/meta` | GET | 无 | 轻量校验端点；返回 `data: { md5 }`（不返回 items，省流量） |
| `/api/admin/locale/refresh` | POST | admin token | 清后端语言包缓存（Redis），下次拉取回源 |

`GET /api/locale/{lang}` 响应结构（HTTP 200）：

```json
{
  "code": 0,
  "msg": "OK",
  "data": {
    "lang": "zh-CN",
    "md5": "9f2c...",
    "items": {
      "common.confirm": "确认",
      "common.cancel": "取消",
      "login.title": "登录",
      "menu.dashboard": "工作台"
    }
  }
}
```

- `items` 是**扁平 key-value**，key 为点号结构（`模块.子项.字段`），后端按语言文件递归展开。
- `md5` 为整个 items 内容的摘要（后端按语言包文件 md5 或 items 序列化后 md5 计算），用于前端版本比对。
- 缓存策略：后端优先读 Redis；`POST /api/admin/locale/refresh` 清缓存后下次请求回源 MySQL 并重建。

### 本地缓存 + md5 版本校验（省流量机制）

**本地缓存结构**（vue3: localStorage key=`ju_locale_pack_{lang}`；uniapp: uni.setStorageSync 同 key）：

```json
{
  "md5": "9f2c...",
  "items": { "common.confirm": "确认" },
  "updatedAt": 1730000000000
}
```

**启动流程**：
1. 读本地缓存 → 立即渲染（防白屏、离线可用）。
2. 并行调 `GET /api/locale/{lang}/meta` 拿 `{ md5 }`：
   - **md5 一致** → 直接用本地缓存，**不发全量包**（省流量）。
   - **md5 不一致 / 无本地缓存 / meta 失败** → 调 `GET /api/locale/{lang}` 拉全量 → 覆盖本地缓存 + i18n.messages → 页面即时重渲染。
3. **降级**：meta 超时（5s AbortController）→ 直接拉全量；缓存 JSON parse 失败 / md5 字段缺失 → 丢弃缓存拉全量。

**触发时机**：
- 应用启动（`bootstrapI18n`）
- 回到前台（vue3 监听 `visibilitychange`；uniapp `App.onShow`）
- 点顶栏"刷新语言包"按钮（先调 `POST /admin/locale/refresh` 清后端缓存，再 force=true 强拉全量）
- 可选轮询（默认不开启，可在 `i18n/index.js` 加 `setInterval` 每 5 分钟 sync 一次）

### 前端实现要点（`src/i18n/index.js`）

1. **启动拉取**：`main.js` 在 `app.mount()` 前 `await bootstrapI18n()`，按 `localStorage.ju_locale`（默认 `zh-CN`）拉取语言包，注入 `vue-i18n`。
2. **运行时切换**：顶栏下拉选 `简体中文 / English` → `switchLocale(lang)` 重新拉取 + `i18n.global.locale.value = lang` + 写 localStorage。
3. **刷新按钮**：顶栏"刷新语言包"按钮 → 先调 `POST /api/admin/locale/refresh` 清后端缓存（admin token；后端未就绪时静默失败），再带 `?_t=Date.now()` cache-bust 强拉 `GET /api/locale/{lang}` → 更新 messages 即时重渲染。
4. **Accept-Language 头**：axios 请求拦截器自动从 `getStoredLocale()` 读取当前语言，写入 `Accept-Language`，后端 `LangSwitch` 中间件据此返回对应语言的 `msg`。
5. **兜底文案**：`i18n/index.js` 内置 `fallbackMessages`，后端接口不可用时页面仍可渲染基础 UI。

### 菜单 title 渲染约定（`layout/MenuItem.vue`）

- 后台 `admin_menus.title` 若配的是 i18n key（形如 `menu.dashboard`，含点号），前端自动 `$t(title)`；查不到则原样显示。
- 若配的是直接中文文案（如 `工作台`，不含点号），原样显示，不做翻译。

### 运维路径

> 后台改 `lang/zh-CN/api.php` 或 `lang/en/api.php` → 管理员点前端顶栏"刷新语言包" → 后端清 Redis 缓存 → 前端强拉最新语言包 → 页面所有文案即时更新，无需重新构建前端。

### uniapp 侧

`frontend-uniapp/common/i18n.js` 实现了同款闭环（启动拉取 + 我的页面语言 picker + 刷新按钮），用轻量响应式 `t()` 函数替代 vue-i18n，体积小、三端兼容。uniapp 侧刷新按钮只做强拉（无 admin 权限场景），带 cache-bust 参数。

## 前端文案规范（新增文案必须用 i18n key）

- **禁止硬编码中文**：模板文本节点、`{{ }}` 插值、`v-bind` 文案、placeholder、按钮文字、表单 label、ElMessage/ElMessageBox 提示语、表格列标题、状态标签（启用/禁用、上/下架等）一律走 `t('key')` / `$t('key')`。
- **key 命名**：点号层级、英文小驼峰/下划线，与后端 `lang/{zh-CN,en}/api.php` 嵌套结构一致。后端返回时自动展开为 `ui.login.title`、`common.success`、`product.saved` 等扁平 key。
- **新增文案流程**：
  1. 在后端 `backend-laravel13/lang/zh-CN/api.php` 与 `lang/en/api.php` 对应分组下追加 key（保持现有缩进与格式）；
  2. 运行 `php -l` 校验语法；
  3. 前端模板用 `t('分组.子项')` 引用；
  4. 后台改文案 → 前端点"刷新语言包"→ 即时生效，无需重新构建前端。
- **优先复用已有 key**：如 `common.success`、`common.fail`、`login.success`、`device.kicked_all`、`product.saved` 等后端已有业务文案 key，不要重复造。
- UI 类文案（按钮、表头、占位符）统一放在 `ui.*` 命名空间下（`ui.login.title`、`ui.product.create`、`ui.recycle.restore` 等），与业务消息 key 分离。

## PC / 手机响应式适配

### 已适配页面
| 页面 | 适配点 |
|---|---|
| 登录页 | 400px 卡片居中；≤480px 缩小 padding/验证码尺寸/标题字号；验证码图 + 语言选择器小屏可点；背景渐变全屏 |
| 主布局 | ≤768px 侧边栏自动收缩到 64px；顶栏隐藏面包屑/用户名；内容区 padding 缩到 8px；表格字体缩小、横向可滚动 |
| 全部 el-table 列表页 | `.main` 容器 `overflow-x: auto` 兜底横向滚动；窄屏字号自动缩小 |

### 未特别适配（残留）
- 弹窗对话框在手机宽度下未做全屏化（Element Plus 默认 dialog 宽度 480-560px，小屏会自动按视口比例收缩，基本可用）。
- dashboard 统计卡片在极窄屏仍为 4 列，小屏可能挤压。

### 断点
- 主断点 `@media (max-width: 768px)` 触发侧边栏折叠、顶栏精简。
- 登录页独立断点 `@media (max-width: 480px)` 进一步压缩验证码与标题。




## 目录结构

```
src/
├─ api/            # 各模块接口函数 + axios 封装
├─ layout/         # 主布局（侧边栏 + 顶栏）
├─ router/         # 静态路由（登录页 + 主布局 + 兜底）
├─ stores/         # Pinia：user（token/菜单）、app（侧边栏折叠）
├─ utils/          # 指纹 / 幂等 key 工具
└─ views/          # 各业务页面
```

## 动态路由说明

骨架内置**静态兜底路由**覆盖契约 §6.4 菜单树里的全部页面路径（`/dashboard`、`/user`、`/admin`、`/role`、`/menu`、`/permission`、`/product`、`/dict`、`/message`、`/operation-log`、`/ip-blacklist`、`/favorite`、`/recycle-bin`）。

登录后：
1. 调 `my-tree` 拿到 `menuTree` 存入 `stores/user.js`；
2. 侧边栏按 `menuTree` 递归渲染 `el-menu`（含 `is_external` 外链跳转处理）；
3. 如需真正的"后端 component 字符串 → 动态路由"，可在 `router/index.js` 的 `addDynamicRoutes` 中用 `import.meta.glob('../views/**/*.vue')` 映射 `menu.component`。当前骨架已按上述路径静态注册，保证开箱即用。
