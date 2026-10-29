// 语言包接口 & 后台刷新缓存接口
import service from './request'

// GET /api/locale/{lang}  （公开接口，无 token；此处仅作备用，i18n 模块内部用 fetch 直连）
export const fetchLocale = (lang) =>
  fetch(`/api/locale/${encodeURIComponent(lang)}?_t=${Date.now()}`, {
    headers: { Accept: 'application/json' }
  }).then((r) => r.json())

// POST /api/admin/locale/refresh  （admin token，清后端语言包缓存）
// 后端未就绪时前端会按 404/异常 toast 提示；不阻塞本地刷新流程。
export const refreshLocaleCache = () =>
  service.post('/admin/locale/refresh')
