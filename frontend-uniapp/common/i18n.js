// common/i18n.js — uni-app 多语言运行时加载（本地缓存 + md5 版本校验）
//
// 流程（与 vue3 侧一致）：
//   1. 启动：读本地缓存 ju_locale_pack_{lang} → 立即渲染（防白屏/离线可用）
//   2. 并行：GET /api/locale/{lang}/meta → { md5 }
//      - md5 一致 → 用本地缓存，不发全量
//      - md5 不一致/无缓存/meta 失败 → GET /api/locale/{lang} 拉全量
//        后端返回 data: { lang, md5, items:{key:value} }
//        → 覆盖本地缓存 + 响应式 messages → 页面自动重渲染
//   3. 触发时机：启动、App onShow（回前台）、我的页面点"刷新语言包"
//   4. 降级：meta 失败 → 拉全量；缓存损坏 → 丢弃拉全量

import { reactive } from 'vue'

export const SUPPORT_LOCALES = ['zh-CN', 'en']
export const DEFAULT_LOCALE = 'zh-CN'
const STORAGE_KEY = 'ju_locale'
const PACK_CACHE_PREFIX = 'ju_locale_pack_'

// 兜底静态文案（接口不可用时也能渲染基础 UI）
const fallbackMessages = {
  'zh-CN': {
    'common.confirm': '确认', 'common.cancel': '取消', 'common.save': '保存',
    'common.success': '操作成功', 'common.fail': '操作失败',
    'common.refreshLocale': '刷新语言包', 'common.language': '语言',
    'common.updated': '语言已更新',
    'tab.home': '首页', 'tab.message': '消息', 'tab.mine': '我的',
    'login.title': '登录', 'login.username': '账号', 'login.password': '密码', 'login.submit': '登 录',
    'login.captcha': '验证码', 'login.captcha_load': '点击加载验证码',
    'login.required': '请输入账号密码', 'login.success': '登录成功',
    'common.new_message': '收到新消息',
    'product.favorite': '收藏', 'product.favorited': '已收藏',
    'favorite.added': '收藏成功', 'favorite.removed': '已取消收藏',
    'favorite.empty': '还没有收藏', 'favorite.remove': '取消收藏',
    'message.mark_read': '全部已读',
    'message.unread': '未读：', 'message.mark_all': '全部已读', 'message.empty': '暂无消息',
    'mine.guest': '未登录', 'mine.lang_fail': '语言包加载失败', 'mine.refresh_fail': '刷新失败', 'mine.logged_out': '已退出',
    'mine.favorites': '我的收藏', 'mine.products': '商品列表', 'mine.go_login': '去登录',
    'mine.tip': '说明：账号在其他设备登录被踢下线后，任意受保护接口会返回 code=401，request.js 会自动清除 token 并跳转登录页。语言包由 GET /api/locale/{lang} 运行时加载；点“刷新语言包”会强拉最新文案。',
    'dict.type': '字典类型：', 'dict.placeholder': '例如 order_status', 'dict.load': '加载', 'dict.empty': '暂无数据',
    'mine.logout': '退出登录',
    'home.title': '首页 / 商品推荐', 'home.dict_entry': '字典示例',
    'home.empty': '暂无商品', 'home.page_not_found': '页面暂未上线'
  },
  en: {
    'common.confirm': 'OK', 'common.cancel': 'Cancel', 'common.save': 'Save',
    'common.success': 'Success', 'common.fail': 'Failed',
    'common.refreshLocale': 'Refresh i18n', 'common.language': 'Language',
    'common.updated': 'Language updated',
    'tab.home': 'Home', 'tab.message': 'Messages', 'tab.mine': 'Me',
    'login.title': 'Sign in', 'login.username': 'Username', 'login.password': 'Password', 'login.submit': 'Sign in',
    'login.captcha': 'Captcha', 'login.captcha_load': 'Tap to load captcha',
    'login.required': 'Enter email and password', 'login.success': 'Signed in',
    'common.new_message': 'New message',
    'product.favorite': 'Favorite', 'product.favorited': 'Favorited',
    'favorite.added': 'Added to favorites', 'favorite.removed': 'Removed from favorites',
    'favorite.empty': 'No favorites yet', 'favorite.remove': 'Unfavorite',
    'message.mark_read': 'Mark all read',
    'message.unread': 'Unread: ', 'message.mark_all': 'Mark all read', 'message.empty': 'No messages',
    'mine.guest': 'Not signed in', 'mine.lang_fail': 'Failed to load language pack', 'mine.refresh_fail': 'Refresh failed', 'mine.logged_out': 'Signed out',
    'mine.favorites': 'Favorites', 'mine.products': 'Products', 'mine.go_login': 'Sign in',
    'mine.tip': 'Note: when your account is signed out on another device, any protected API returns code=401, and request.js clears the token and redirects to the login page. Language packs are loaded at runtime from GET /api/locale/{lang}; tap "Refresh i18n" to force the latest copy.',
    'dict.type': 'Dict type: ', 'dict.placeholder': 'e.g. order_status', 'dict.load': 'Load', 'dict.empty': 'No data',
    'mine.logout': 'Logout',
    'home.title': 'Home / Products', 'home.dict_entry': 'Dictionary demo',
    'home.empty': 'No products', 'home.page_not_found': 'Page not available'
  }
}

const state = reactive({
  locale: getStoredLocale(),
  messages: { ...(fallbackMessages[getStoredLocale()] || {}) }
})

function getStoredLocale() {
  try {
    const l = uni.getStorageSync(STORAGE_KEY)
    if (l && SUPPORT_LOCALES.includes(l)) return l
  } catch (e) {}
  return DEFAULT_LOCALE
}
function setStoredLocale(lang) {
  try { uni.setStorageSync(STORAGE_KEY, lang) } catch (e) {}
}

// ===== 本地缓存 =====
function cacheKey(lang) { return PACK_CACHE_PREFIX + lang }
function readLocalPack(lang) {
  try {
    const raw = uni.getStorageSync(cacheKey(lang))
    if (!raw) return null
    const pack = typeof raw === 'string' ? JSON.parse(raw) : raw
    if (!pack || typeof pack !== 'object' || !pack.items || !pack.md5) return null
    return pack
  } catch (e) { return null }
}
function writeLocalPack(lang, pack) {
  try {
    uni.setStorageSync(cacheKey(lang), JSON.stringify({ ...pack, updatedAt: Date.now() }))
  } catch (e) {}
}

export function t(key) {
  const v = state.messages[key]
  if (v !== undefined && v !== null && v !== '') return v
  const fb = fallbackMessages[state.locale] || {}
  return fb[key] !== undefined ? fb[key] : key
}
export function getLocale() { return state.locale }

// ===== 远程请求（H5 用 fetch，小程序/App 用 uni.request） =====
function requestJson(url, timeout = 8000) {
  return new Promise((resolve, reject) => {
    uni.request({
      url, method: 'GET', timeout,
      header: { Accept: 'application/json' },
      success: (r) => resolve(r.data || {}),
      fail: reject
    })
  })
}

async function fetchMeta(lang) {
  try {
    const body = await requestJson(`/api/locale/${encodeURIComponent(lang)}/meta`, 5000)
    return body?.data?.md5 || null
  } catch (e) { return null }
}

async function fetchFull(lang, cacheBust = false) {
  const url = `/api/locale/${encodeURIComponent(lang)}${cacheBust ? `?_t=${Date.now()}` : ''}`
  const body = await requestJson(url)
  const d = body.data || {}
  if (d.items && typeof d.items === 'object') {
    return { md5: d.md5 || null, items: d.items }
  }
  // 旧扁平形态
  return { md5: null, items: d }
}

function applyItems(items) {
  state.messages = { ...(fallbackMessages[state.locale] || {}), ...items }
}

/** 核心：检查并按需更新 */
export async function syncLocale(lang, opts = {}) {
  // 1) 先渲染本地缓存
  const local = readLocalPack(lang)
  if (local?.items) applyItems(local.items)

  if (opts.force) {
    const { md5, items } = await fetchFull(lang, true)
    applyItems(items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }
  if (!local) {
    const { md5, items } = await fetchFull(lang)
    applyItems(items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }
  const remoteMd5 = await fetchMeta(lang)
  if (!remoteMd5) {
    const { md5, items } = await fetchFull(lang)
    applyItems(items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }
  if (remoteMd5 === local.md5) {
    return { changed: false, md5: local.md5 }
  }
  const { md5, items } = await fetchFull(lang)
  applyItems(items)
  writeLocalPack(lang, { md5: md5 || remoteMd5, items })
  return { changed: true, md5: md5 || remoteMd5 }
}

export async function switchLocale(lang) {
  if (!SUPPORT_LOCALES.includes(lang)) lang = DEFAULT_LOCALE
  state.locale = lang
  setStoredLocale(lang)
  await syncLocale(lang, { force: true })
  return lang
}

export async function refreshLocale(force = true) {
  const res = await syncLocale(state.locale, { force })
  return { lang: state.locale, ...res }
}

export async function bootstrapI18n() {
  const lang = getStoredLocale()
  state.locale = lang
  const local = readLocalPack(lang)
  if (local?.items) applyItems(local.items)
  syncLocale(lang).catch((e) => console.warn('[i18n] sync 失败', e))
  return lang
}

export { state }
export default { t, state, getLocale, switchLocale, refreshLocale, bootstrapI18n, syncLocale, SUPPORT_LOCALES }
