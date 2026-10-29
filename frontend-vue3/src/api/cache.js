import request from '@/api/request'

// 清空后端应用缓存（语言包/菜单/字典/C端菜单/限流规则等）
// Flush all backend application caches (locale/menu/dict/c_menu/rate rules...)
export function flushCache() {
  return request.post('/admin/cache/flush')
}
