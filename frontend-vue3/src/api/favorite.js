// 用户收藏接口（契约 §6.2 #57）
import { get } from './request'

// GET /api/admin/favorites  可按 user_id / product_id 过滤
export const listFavorites = (params) => get('/admin/favorites', params)
