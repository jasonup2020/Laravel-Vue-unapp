// C端菜单（admin 后台 CRUD）
import { get, post, put, del } from './request'

export const listCMenus = (params) => get('/admin/c-menus', params)
export const createCMenu = (data) => post('/admin/c-menus', data)
export const updateCMenu = (id, data) => put(`/admin/c-menus/${id}`, data)
export const deleteCMenu = (id) => del(`/admin/c-menus/${id}`)
