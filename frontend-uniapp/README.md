# frontend-uniapp — uni-app C 端模板

配套 `laravel13-api-skeleton` 的 C 端 uni-app（Vue3）模板。HBuilderX 工程，无需 CLI 构建。

## 目录结构

```
frontend-uniapp/
├─ pages.json          # 页面路由 + tabBar + globalStyle
├─ manifest.json       # appid 占位 / h5 devServer proxy / mp-weixin / vueVersion=3
├─ App.vue             # onLaunch 初始化指纹 + WS 连接
├─ main.js             # createSSRApp
├─ uni.scss            # 基础变量
├─ common/
│  ├─ request.js       # uni.request 封装（Bearer / Accept-Language / X-Client-Fingerprint / X-Idempotency-Key / 统一解包 / 401 处理）
│  ├─ fingerprint.js   # 客户端指纹 & 幂等 key 生成
│  └─ ws.js            # Reverb WebSocket 原生订阅示例（private-user.{userId}）
├─ api/
│  ├─ auth.js          # login / refresh / logout / me
│  ├─ product.js       # 商品列表 / 详情
│  ├─ dict.js          # 字典 by type
│  ├─ message.js       # 消息列表 / 未读数 / 已读 / 全部已读
│  └─ favorite.js      # 收藏 / 取消 / 列表 / 状态
└─ pages/
   ├─ login/index.vue
   ├─ index/index.vue       (tabBar)
   ├─ product/list.vue
   ├─ product/detail.vue
   ├─ dict/index.vue
   ├─ message/index.vue     (tabBar)
   ├─ favorite/index.vue
   └─ mine/index.vue        (tabBar)
```

## 启动步骤

1. 打开 HBuilderX → `文件 → 导入 → 从本地目录导入`，选择本目录 `frontend-uniapp/`。
2. 打开 `manifest.json`：
   - **基础配置**：填自己的 `AppID`（DCloud appid）；
   - **微信小程序配置**：填自己的小程序 AppID。
3. **对接后端**：
   - H5 调试：`manifest.json → h5.devServer.proxy` 已经把 `/api` 代理到 `http://localhost:8000`（即 Laravel `php artisan serve`）；`/app` 代理到 `ws://localhost:8080`（Reverb `php artisan reverb:start`）。
   - 小程序 / App：`common/request.js` 中的 `BASE_URL` 需要改成完整 `https://` 域名；小程序后台必须把对应域名加入 **request 合法域名**。
4. **Reverb WSS 域名**：
   - `common/ws.js` 顶部 `WS_BASE` / `WS_PORT` / `REVERB_APP_KEY` 必须与后端 `.env` 的 `REVERB_*` 对齐；
   - 小程序 / App 生产环境必须使用 **`wss://`** 加密域名，并在小程序后台配置 **socket 合法域名**。
5. 运行：`运行 → 运行到浏览器 → Chrome`（或 `运行到小程序模拟器 → 微信开发者工具`）。

## 与后端契约对齐点

- 请求头：`Authorization: Bearer <token>`、`Accept-Language: zh-CN`、`X-Client-Fingerprint: <uuid>`（复合限流）、`X-Idempotency-Key: <uuid>`（POST/PUT/DELETE 自动附加）。
- 响应解包：`{success, code, msg, data}`；`success=false` 时 toast 提示；`code=401` 清 token 并 `reLaunch` 到登录页（覆盖"多端登录被踢下线"场景）。
- 分页：`{list, total, page, page_size}`。
- WebSocket：订阅 `private-user.{userId}` 频道，接收后端 `App\Events\MessageSent` 广播。

## 可选 npm 依赖（仅 H5 使用）

如需官方 laravel-echo：
```bash
npm install laravel-echo pusher-js
```
然后在 `common/ws.js` 中替换为 Echo 实例；小程序环境仍建议保留原生 WebSocket 实现。
