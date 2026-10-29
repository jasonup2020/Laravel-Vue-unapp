// 回收站接口（契约 §6.2 #62-64）
// resource 白名单：products,users,admins,admin_menus,roles,messages,user_favorites
import { get, put } from './request'

export const listRecycle = (resource, params) =>
  get(`/admin/recycle-bin/${resource}`, params)

export const restoreRecycle = (resource, id) =>
  put(`/admin/recycle-bin/${resource}/${id}/restore`)

// body: { ids: [snow_id, ...] }
export const restoreBatchRecycle = (resource, data) =>
  put(`/admin/recycle-bin/${resource}/restore-batch`, data)
