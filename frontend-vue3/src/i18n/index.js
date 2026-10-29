// i18n 运行时加载：本地缓存 + md5 版本校验
//
// 流程：
//   1. 启动：读本地缓存 ju_locale_pack_{lang} → 立即渲染（防白屏/离线可用）
//   2. 并行：GET /api/locale/{lang}/meta → 拿 { md5 }
//      - md5 与本地一致 → 不发全量包，直接用本地缓存
//      - md5 不一致（或无本地缓存）→ GET /api/locale/{lang} 拉全量
//        后端返回 data: { lang, md5, items:{key:value} }
//        → 覆盖本地缓存 + i18n.messages → 页面文案自动重渲染
//   3. 降级：meta 失败/超时 → 直接拉全量；缓存损坏 → 丢弃拉全量
//   4. 触发时机：启动、visibilitychange（回前台）、点"刷新语言包"按钮
import { createI18n } from 'vue-i18n'

export const SUPPORT_LOCALES = ['zh-CN', 'en']
export const DEFAULT_LOCALE = 'zh-CN'
const STORAGE_KEY = 'ju_locale'
const PACK_CACHE_PREFIX = 'ju_locale_pack_'

// 兜底静态文案（后端接口不可用时也能渲染基础页面）
const fallbackMessages = {
  'zh-CN': {
    common: { success: '操作成功', fail: '操作失败' },
    ui: {
      home: '首页', confirm: '确认', cancel: '取消', save: '保存', delete: '删除',
      edit: '编辑', create: '新增', search: '查询', submit: '提交', reset: '重置',
      action: '操作', status: '状态', enabled: '启用', disabled: '禁用', sort: '排序', id: 'ID',
      created_at: '创建时间', updated_at: '更新时间', deleted_at: '删除时间',
      on: '上架', off: '下架',
      show: '显示', hide: '隐藏', refresh: '刷新', refresh_locale: '刷新语言包',
      language: '语言', logout: '退出登录', updated: '语言已更新',
      refresh_menu: '刷新左边栏菜单', refresh_cache: '刷新缓存',
      profile: '个人中心', profile_username: '账号', profile_nickname: '昵称',
      profile_email: '邮箱', profile_roles: '角色',
      loading: '加载中...', no_data: '暂无数据', keyword: '关键词',
      snow_id: '雪花ID', lang_zh: '简体中文', lang_en: '英文',
      lang_switched: '已切换为中文', lang_load_failed: '语言包加载失败',
      refresh_failed: '刷新失败', admin_fallback_name: '管理员',
      login: { title: 'Laravel13 管理后台', username: '账号', password: '密码', submit: '登 录', captcha: '验证码', captcha_load: '点击加载验证码', success: '登录成功' },
      dashboard: { title: '工作台', health: '系统健康', health_tip: '后端健康检查：GET /api/health' },
      user: { title: '普通用户列表', name: '用户名', email: '邮箱', online: '在线终端', kick: '踢下线', kick_confirm: '确认踢下线该用户全部终端？' },
      admin: { title: '管理员管理', create: '新增管理员', username: '账号', nickname: '昵称', email: '邮箱', password: '密码', password_confirm: '确认密码', reset_pwd: '重置密码', new_password: '新密码', edit: '编辑', delete: '删除', roles: '角色', assign_role: '分配角色', delete_confirm: '确认删除该管理员？' },
      role: { title: '角色管理', create: '新增角色', name: '角色名', code: '标识', remark: '备注', edit: '编辑', assign_perm: '分配权限', assign_menu: '分配菜单', delete: '删除', delete_confirm: '确认删除该角色？', assigned: '权限已分配', menu_assigned: '菜单已分配', perm_common: '公共权限', perm_other: '其它权限' },
      menu: { title: '菜单管理（全量树）', create_root: '新增根菜单', create_child: '新增子级', edit: '编辑', delete: '删除', title_col: '标题', path: '路径', icon: '图标', sort: '排序', visible: '显示', external: '外链', parent: '父菜单', component: '组件路径', perm_code: '权限码', is_external: '是否外链', external_url: '外链URL', is_show: '侧边显示', delete_confirm: '确认删除该菜单？' },
      permission: { title: '权限列表', name: '名称', label_en: '英文名称', code: '权限码', group: '分组', action: '动作', remark: '备注', create: '新增权限', edit: '编辑权限', delete: '删除', delete_confirm: '确认删除该权限？', system_not_deletable: '系统预置权限不可删除', code_ph: '如 admin:product:export' },
      perm_group: { admin: '管理员', dict: '字典', favorite: '收藏', ip: 'IP黑名单', log: '操作日志', menu: '菜单', message: '消息', permission: '权限', product: '商品', rate: '限流', recycle: '回收站', role: '角色', system: '系统', user: '用户' },
      cmenu: { title: 'C端菜单管理', create: '新增菜单', create_child: '新增子级', title_en: '英文标题', path: '页面路径', is_show: '显示', is_external: '是否外链', delete_confirm: '确认删除该 C 端菜单？' },
      product: { title: '商品管理', create: '新增商品', edit: '编辑', delete: '删除', name: '商品名称', price: '价格', desc: '描述', delete_confirm: '确认删除该商品？' },
      dict: { title: '字典管理', create: '新增字典项', edit: '编辑', delete: '删除', type: '类型', label: '显示文本', label_en: '英文名称', value: '存储值', delete_confirm: '确认删除该字典项？' },
      message: { title: '消息中心', send: '发送消息', receiver_type: '接收类型', receiver_id: '接收人ID', msg_type: '消息类型', title_col: '标题', content: '内容', is_pop: '弹窗', sent: '消息已发送' },
      log: { title: '操作日志', url: 'URL', method: '方法', ip: 'IP', cost_ms: '耗时ms', high_risk: '高危', admin_id: '管理员ID' },
      ip: { title: 'IP 黑名单', create: '新增封禁', unblock: '解除', ip: 'IP', remark: '备注', expire_at: '过期时间', permanent: '永久', unblock_confirm: '确认解除该 IP 封禁？' },
      recycle: { title: '回收站', resource: '资源类型', restore: '恢复', batch_restore: '批量恢复', restored: '已恢复', batch_restored: '批量恢复成功', deleted_at: '删除时间', products: '商品', users: '普通用户', admins: '管理员', menus: '菜单', roles: '角色', messages: '消息', favorites: '收藏' },
      favorite: { title: '收藏管理', user_id: '用户ID', product_id: '商品ID', product_name: '商品名', fav_time: '收藏时间' },
      rate: { title: '限流规则', create: '新增规则', rule_key: '规则标识', rule_key_ph: '如 /api/login', max_attempts: '最大次数', decay_seconds: '窗口秒数', enabled: '启用', remark: '备注', delete_confirm: '确认删除该限流规则？' }
    }
  },
  en: {
    common: { success: 'Success', fail: 'Failed' },
    ui: {
      home: 'Home', confirm: 'OK', cancel: 'Cancel', save: 'Save', delete: 'Delete',
      edit: 'Edit', create: 'Create', search: 'Search', submit: 'Submit', reset: 'Reset',
      action: 'Action', status: 'Status', enabled: 'Enabled', disabled: 'Disabled', sort: 'Sort', id: 'ID',
      created_at: 'Created at', updated_at: 'Updated at', deleted_at: 'Deleted at',
      on: 'On sale', off: 'Off sale',
      show: 'Show', hide: 'Hide', refresh: 'Refresh', refresh_locale: 'Refresh i18n',
      language: 'Language', logout: 'Logout', updated: 'Language updated',
      refresh_menu: 'Refresh sidebar menu', refresh_cache: 'Refresh cache',
      profile: 'Profile', profile_username: 'Username', profile_nickname: 'Nickname',
      profile_email: 'Email', profile_roles: 'Roles',
      loading: 'Loading...', no_data: 'No data', keyword: 'Keyword',
      snow_id: 'Snow ID', lang_zh: 'Simplified Chinese', lang_en: 'English',
      lang_switched: 'Language switched', lang_load_failed: 'Failed to load language pack',
      refresh_failed: 'Refresh failed', admin_fallback_name: 'Admin',
      login: { title: 'Laravel13 Admin', username: 'Username', password: 'Password', submit: 'Sign in', captcha: 'Captcha', captcha_load: 'Click to load captcha', success: 'Login successful' },
      dashboard: { title: 'Dashboard', health: 'System health', health_tip: 'Health check: GET /api/health' },
      user: { title: 'Users', name: 'Name', email: 'Email', online: 'Online', kick: 'Kick', kick_confirm: 'Kick all devices of this user?' },
      admin: { title: 'Admins', create: 'New admin', username: 'Username', nickname: 'Nickname', email: 'Email', password: 'Password', password_confirm: 'Confirm password', reset_pwd: 'Reset password', new_password: 'New password', edit: 'Edit', delete: 'Delete', roles: 'Roles', assign_role: 'Assign roles', delete_confirm: 'Delete this admin?' },
      role: { title: 'Roles', create: 'New role', name: 'Name', code: 'Code', remark: 'Remark', edit: 'Edit', assign_perm: 'Assign permissions', assign_menu: 'Assign menus', delete: 'Delete', delete_confirm: 'Delete this role?', assigned: 'Permissions assigned', menu_assigned: 'Menus assigned', perm_common: 'Common permissions', perm_other: 'Other permissions' },
      menu: { title: 'Menus (full tree)', create_root: 'New root menu', create_child: 'New child', edit: 'Edit', delete: 'Delete', title_col: 'Title', path: 'Path', icon: 'Icon', sort: 'Sort', visible: 'Visible', external: 'External', parent: 'Parent', component: 'Component', perm_code: 'Permission code', is_external: 'Is external', external_url: 'External URL', is_show: 'Show in sidebar', delete_confirm: 'Delete this menu?' },
      permission: { title: 'Permissions', name: 'Name', label_en: 'English name', code: 'Code', group: 'Group', action: 'Action', remark: 'Remark', create: 'New permission', edit: 'Edit permission', delete: 'Delete', delete_confirm: 'Delete this permission?', system_not_deletable: 'System preset permissions cannot be deleted', code_ph: 'e.g. admin:product:export' },
      perm_group: { admin: 'Admin', dict: 'Dictionary', favorite: 'Favorites', ip: 'IP Blacklist', log: 'Operation Logs', menu: 'Menu', message: 'Messages', permission: 'Permissions', product: 'Products', rate: 'Rate Limits', recycle: 'Recycle Bin', role: 'Roles', system: 'System', user: 'Users' },
      cmenu: { title: 'C-menu Management', create: 'New menu', create_child: 'New child', title_en: 'English title', path: 'Page path', is_show: 'Visible', is_external: 'External', delete_confirm: 'Delete this C-end menu?' },
      product: { title: 'Products', create: 'New product', edit: 'Edit', delete: 'Delete', name: 'Name', price: 'Price', desc: 'Description', delete_confirm: 'Delete this product?' },
      dict: { title: 'Dictionary', create: 'New dict item', edit: 'Edit', delete: 'Delete', type: 'Type', label: 'Label', label_en: 'English label', value: 'Value', delete_confirm: 'Delete this dict item?' },
      message: { title: 'Messages', send: 'Send message', receiver_type: 'Receiver type', receiver_id: 'Receiver ID', msg_type: 'Message type', title_col: 'Title', content: 'Content', is_pop: 'Popup', sent: 'Message sent' },
      log: { title: 'Operation logs', url: 'URL', method: 'Method', ip: 'IP', cost_ms: 'Cost (ms)', high_risk: 'High risk', admin_id: 'Admin ID' },
      ip: { title: 'IP Blacklist', create: 'Block IP', unblock: 'Unblock', ip: 'IP', remark: 'Remark', expire_at: 'Expires at', permanent: 'Permanent', unblock_confirm: 'Unblock this IP?' },
      recycle: { title: 'Recycle Bin', resource: 'Resource type', restore: 'Restore', batch_restore: 'Batch restore', restored: 'Restored', batch_restored: 'Batch restored', deleted_at: 'Deleted at', products: 'Products', users: 'Users', admins: 'Admins', menus: 'Menus', roles: 'Roles', messages: 'Messages', favorites: 'Favorites' },
      favorite: { title: 'Favorites', user_id: 'User ID', product_id: 'Product ID', product_name: 'Product', fav_time: 'Favorited at' },
      rate: { title: 'Rate Limits', create: 'New rule', rule_key: 'Rule key', rule_key_ph: 'e.g. /api/login', max_attempts: 'Max attempts', decay_seconds: 'Window (s)', enabled: 'Enabled', remark: 'Remark', delete_confirm: 'Delete this rate limit rule?' }
    }
  }
}

const i18n = createI18n({
  legacy: false,
  locale: getStoredLocale(),
  fallbackLocale: DEFAULT_LOCALE,
  messages: fallbackMessages
})

export function getStoredLocale() {
  try {
    const l = localStorage.getItem(STORAGE_KEY)
    if (l && SUPPORT_LOCALES.includes(l)) return l
  } catch (e) {}
  return DEFAULT_LOCALE
}
export function setStoredLocale(lang) {
  try { localStorage.setItem(STORAGE_KEY, lang) } catch (e) {}
}

// ===== 本地缓存读写 =====
function cacheKey(lang) { return PACK_CACHE_PREFIX + lang }

/** 读取本地缓存 { md5, items, updatedAt }；损坏/缺失返回 null */
function readLocalPack(lang) {
  try {
    const raw = localStorage.getItem(cacheKey(lang))
    if (!raw) return null
    const pack = JSON.parse(raw)
    if (!pack || typeof pack !== 'object' || !pack.items || !pack.md5) return null
    return pack
  } catch (e) {
    return null
  }
}
function writeLocalPack(lang, pack) {
  try {
    localStorage.setItem(cacheKey(lang), JSON.stringify({ ...pack, updatedAt: Date.now() }))
  } catch (e) {}
}

/** 扁平 key-value → vue-i18n 嵌套对象 */
export function flattenToNested(flat) {
  const out = {}
  Object.entries(flat || {}).forEach(([k, v]) => {
    const parts = k.split('.')
    let cur = out
    for (let i = 0; i < parts.length - 1; i++) {
      if (typeof cur[parts[i]] !== 'object' || cur[parts[i]] === null) cur[parts[i]] = {}
      cur = cur[parts[i]]
    }
    cur[parts[parts.length - 1]] = v
  })
  return out
}

/** 深度合并：后端 items 覆盖同名叶子，保留兜底包中后端未提供的 key（避免后端部分 ui.* 替换整个兜底 ui.*） */
function deepMerge(base, extra) {
  const out = { ...base }
  for (const [k, v] of Object.entries(extra || {})) {
    if (v && typeof v === 'object' && !Array.isArray(v) && out[k] && typeof out[k] === 'object' && !Array.isArray(out[k])) {
      out[k] = deepMerge(out[k], v)
    } else {
      out[k] = v
    }
  }
  return out
}

/** 把 items 注入 i18n（深合并兜底，后端优先） */
function applyItems(lang, items) {
  const nested = flattenToNested(items)
  const existing = i18n.global.getLocaleMessage(lang) || {}
  i18n.global.setLocaleMessage(lang, deepMerge(existing, nested))
}

// ===== 远程接口 =====
// 单例锁：同 lang 并发调用 syncLocale 时复用同一个 Promise，避免上百次重复请求
const pendingSync = new Map()

async function fetchMeta(lang) {
  // 轻量端点：只返回 md5，不返回 items
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), 5000)
  try {
    const resp = await fetch(`/api/locale/${encodeURIComponent(lang)}/meta`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal
    })
    if (!resp.ok) return null
    const body = await resp.json()
    return body?.data?.md5 || null
  } catch (e) {
    return null // 超时/失败 → 降级走全量
  } finally {
    clearTimeout(timer)
  }
}

async function fetchFull(lang, cacheBust = false) {
  const url = `/api/locale/${encodeURIComponent(lang)}${cacheBust ? `?_t=${Date.now()}` : ''}`
  const resp = await fetch(url, { headers: { Accept: 'application/json' } })
  if (!resp.ok) throw new Error(`HTTP ${resp.status}`)
  const body = await resp.json()
  // 后端新契约：data = { lang, md5, items:{key:value} }
  // 兼容旧契约：data = { key:value } 扁平
  const d = body.data || {}
  if (d.items && typeof d.items === 'object') {
    return { md5: d.md5 || null, items: d.items }
  }
  // 旧扁平形态
  return { md5: null, items: d }
}

/**
 * 核心：检查并按需更新语言包。
 * @param {string} lang
 * @param {object} opts { force: boolean, silent: boolean }
 *   force=true → 跳过 meta 比对，直接拉全量（用于"刷新语言包"按钮）
 * @returns {Promise<{changed: boolean, md5?: string}>}
 */
export async function syncLocale(lang, opts = {}) {
  // force 模式（刷新按钮/切换语言）不走单例锁，必须强拉
  if (!opts.force) {
    const key = lang + (opts.silent ? ':silent' : '')
    if (pendingSync.has(key)) return pendingSync.get(key)
    const p = doSync(lang, opts).finally(() => pendingSync.delete(key))
    pendingSync.set(key, p)
    return p
  }
  return doSync(lang, opts)
}

async function doSync(lang, opts = {}) {
  // 1) 先渲染本地缓存（若有）
  const local = readLocalPack(lang)
  if (local?.items) {
    applyItems(lang, local.items)
  }

  // 2) force 模式：直接拉全量
  if (opts.force) {
    const { md5, items } = await fetchFull(lang, true)
    applyItems(lang, items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }

  // 3) 无本地缓存 → 必须拉全量
  if (!local) {
    const { md5, items } = await fetchFull(lang)
    applyItems(lang, items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }

  // 4) 有本地缓存 → 先问 meta 比对 md5
  const remoteMd5 = await fetchMeta(lang)
  if (!remoteMd5) {
    // meta 失败 → 降级拉全量
    const { md5, items } = await fetchFull(lang)
    applyItems(lang, items)
    writeLocalPack(lang, { md5, items })
    return { changed: true, md5 }
  }
  if (remoteMd5 === local.md5) {
    // 一致，直接用本地缓存，不发全量
    return { changed: false, md5: local.md5 }
  }
  // 不一致 → 拉全量
  const { md5, items } = await fetchFull(lang)
  applyItems(lang, items)
  writeLocalPack(lang, { md5: md5 || remoteMd5, items })
  return { changed: true, md5: md5 || remoteMd5 }
}

/** 切换语言 */
export async function switchLocale(lang) {
  if (!SUPPORT_LOCALES.includes(lang)) lang = DEFAULT_LOCALE
  i18n.global.locale.value = lang
  setStoredLocale(lang)
  await syncLocale(lang, { force: true })
  return lang
}

/** 刷新当前语言包（走 md5 比对流程；force=true 强拉全量） */
export async function refreshCurrentLocale(force = true) {
  const lang = i18n.global.locale.value || getStoredLocale()
  const res = await syncLocale(lang, { force })
  return { lang, ...res }
}

/** 启动：先渲染本地缓存，后台静默 sync */
export async function bootstrapI18n() {
  const lang = getStoredLocale()
  i18n.global.locale.value = lang
  // 立即渲染本地缓存
  const local = readLocalPack(lang)
  if (local?.items) applyItems(lang, local.items)
  // 后台静默校验更新（失败不阻塞首屏）
  syncLocale(lang).catch((e) => console.warn('[i18n] sync 失败', e))
  return lang
}

// 回前台时按需校验（visibilitychange）
if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      const lang = i18n.global.locale.value || getStoredLocale()
      syncLocale(lang).catch(() => {})
    }
  })
}

export default i18n
