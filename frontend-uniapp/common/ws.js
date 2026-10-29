// common/ws.js — Laravel Reverb WebSocket 订阅示例（原生 WebSocket 实现）
// Reverb WebSocket client (raw WebSocket, mimicking laravel-echo minimal flow)
//
// 背景 / Background（PROJECT_SPEC §11、SHARED_CONVENTIONS §5.2）：
//   - 后端使用 Laravel Reverb，默认 WS 端口 8080；
//   - 私有频道格式：private-user.{userId}；
//   - Reverb WebSocket 握手 URL：
//       ws(s)://{host}:{port}/app/{REVERB_APP_KEY}?protocol=7&client=uni-app&version=1.0&flash=false
//     其中 {REVERB_APP_KEY} 来自后端 config/broadcasting.php（REVERB_APP_KEY）；
//     如需带 JWT 做鉴权（private channel），在 URL query 上追加 &token=<jwt>。
//   - Reverb 协议帧（JSON）主要类型：
//       握手成功： {"event":"pusher:connection_established","data":"{\"socket_id\":\"...\"}"}
//       订阅私有频道：发送
//         {"event":"pusher:subscribe","data":{"auth":"","channel":"private-user.{userId}"}}
//       收到业务推送：
//         {"event":"Illuminate\\Notifications\\Events\\BroadcastNotificationCreated",
//          "channel":"private-user.{userId}", "data":{...}}
//
// 说明：生产推荐使用官方 laravel-echo + pusher-js（在 H5 中可 npm i）；
//       小程序/App 环境下官方 SDK 兼容性有限，这里给出一个最小可用的原生实现，
//       帮助理解握手/订阅/收消息的完整闭环。
//
// 使用示例 / Usage：
//   import wsClient from '@/common/ws.js'
//   wsClient.connect(jwtToken, userId, {
//     onMessage: (payload) => console.log('new message', payload)
//   })
//   // 页面 onUnload / 退出登录时：
//   wsClient.disconnect()

// ===== 配置区 / Config =====
// H5 下可走 manifest.json devServer 代理 /app → ws://localhost:8080；
// 小程序/App 必须使用 wss:// 完整域名。
// REVERB_APP_KEY 与后端 .env 的 REVERB_APP_KEY 保持一致。
const WS_BASE = '' // H5 走相对路径时留空，由 proxy 处理；小程序/App 填 wss://api.example.com
const WS_PORT = '' // H5 proxy 模式留空；直连时填 8080（wss 时一般 443）
const REVERB_APP_KEY = 'local-reverb-key' // TODO 与后端 REVERB_APP_KEY 对齐

// ===== 内部状态 / Internal state =====
let socket = null
let currentToken = ''
let currentUserId = 0
let opts = { onMessage: null, onStatusChange: null }
let reconnectTimer = null
let manuallyClosed = false

function buildWsUrl(token) {
  // 构造 Reverb WebSocket 握手 URL
  // Build Reverb WS handshake URL
  const scheme = WS_BASE.startsWith('wss') || WS_BASE.startsWith('ws') ? '' : (WS_BASE === '' ? 'ws://' : 'wss://')
  const host = WS_BASE ? WS_BASE.replace(/^wss?:\/\//, '') : 'localhost'
  const portPart = WS_PORT ? ':' + WS_PORT : ''
  const base = `${scheme}${host}${portPart}`
  // 注意：H5 走 devServer proxy 时，/app 由代理转发到 ws://localhost:8080/app
  // On H5 with devServer proxy, /app is proxied to ws://localhost:8080/app
  const path = `/app/${REVERB_APP_KEY}?protocol=7&client=uni-app&version=1.0&flash=false`
  const tokenPart = token ? `&token=${encodeURIComponent(token)}` : ''
  return `${base}${path}${tokenPart}`
}

function notifyStatus(status) {
  if (typeof opts.onStatusChange === 'function') opts.onStatusChange(status)
}

function sendSubscribeFrame(userId) {
  // Reverb 私有频道订阅帧；auth 字段由后端 /broadcasting/auth 签发，
  // 若后端在握手 URL 里已携带 token 并自动鉴权，可发送空 auth。
  // For private channel, normally you first POST /broadcasting/auth with socket_id+channel
  // to get `auth` string. This sample sends empty auth; wire it up if needed.
  const frame = {
    event: 'pusher:subscribe',
    data: {
      auth: '',
      channel: `private-user.${userId}`
    }
  }
  if (socket && socket.readyState === 1) {
    socket.send(JSON.stringify(frame))
  }
}

function handleRawMessage(evt) {
  let parsed = null
  try {
    parsed = JSON.parse(typeof evt.data === 'string' ? evt.data : evt.data.toString())
  } catch (e) {
    return
  }
  if (!parsed || !parsed.event) return

  // 连接建立完成 → 订阅私有频道
  if (parsed.event === 'pusher:connection_established') {
    sendSubscribeFrame(currentUserId)
    notifyStatus('connected')
    return
  }

  // 业务消息推送（Illuminate\\Notifications\\Events\\BroadcastNotificationCreated
  // 或后端自定义 event，如 App\\Events\\MessageSent::class）
  // Business push events from backend
  if (typeof opts.onMessage === 'function') {
    // data 字段可能是字符串 JSON，按需二次 parse
    let data = parsed.data
    if (typeof data === 'string') {
      try { data = JSON.parse(data) } catch (e) { /* keep string */ }
    }
    opts.onMessage({ event: parsed.event, channel: parsed.channel, data })
  }
}

/**
 * 建立 WebSocket 连接 / Establish WebSocket connection
 * @param {string} token      JWT
 * @param {number|string} userId 当前登录用户 ID（users 表自增 id）
 * @param {{onMessage?:Function, onStatusChange?:Function}} [options]
 */
function connect(token, userId, options = {}) {
  currentToken = token || ''
  currentUserId = userId
  opts = { onMessage: options.onMessage || opts.onMessage, onStatusChange: options.onStatusChange || opts.onStatusChange }
  manuallyClosed = false

  if (reconnectTimer) {
    clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (socket) {
    try { socket.close() } catch (e) { /* noop */ }
    socket = null
  }

  const url = buildWsUrl(currentToken)
  notifyStatus('connecting')

  // H5 用 window.WebSocket；App/小程序用 uni.connectSocket
  // H5 uses window.WebSocket; App/MP uses uni.connectSocket
  // 这里以 H5 为主，App/MP 请按 uni.connectSocket API 等价改写。
  if (typeof window !== 'undefined' && window.WebSocket) {
    socket = new window.WebSocket(url)
    socket.onopen = () => { notifyStatus('open') }
    socket.onmessage = handleRawMessage
    socket.onerror = (e) => { notifyStatus('error') }
    socket.onclose = () => {
      notifyStatus('closed')
      // 未主动关闭且仍有 token → 3 秒后重连
      // Reconnect after 3s if not manually closed and token still present
      if (!manuallyClosed && uni.getStorageSync('token')) {
        reconnectTimer = setTimeout(() => {
          connect(currentToken, currentUserId, opts)
        }, 3000)
      }
    }
  } else {
    // uni.connectSocket 适配（小程序/App）— 骨架示意
    // uni.connectSocket adaptation (MP/App) — skeleton
    const task = uni.connectSocket({ url, complete: () => {} })
    socket = task
    task.onOpen(() => notifyStatus('open'))
    task.onMessage(handleRawMessage)
    task.onError(() => notifyStatus('error'))
    task.onClose(() => notifyStatus('closed'))
  }
}

/**
 * 主动断开（登出/页面卸载时调用）
 * Manually disconnect (on logout / page unmount)
 */
function disconnect() {
  manuallyClosed = true
  if (reconnectTimer) {
    clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (socket) {
    try {
      if (typeof socket.close === 'function') socket.close()
    } catch (e) { /* noop */ }
    socket = null
  }
  notifyStatus('closed')
}

/**
 * 当前是否处于已连接状态
 * Whether socket is currently open
 * @returns {boolean}
 */
function isConnected() {
  return !!(socket && (socket.readyState === 1))
}

export default { connect, disconnect, isConnected, buildWsUrl }
