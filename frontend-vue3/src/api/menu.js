// 菜单相关接口（契约 §6.2 #24-28）
import { get, post, put, del } from './request'

// GET /api/admin/menu/my-tree  登录后侧边栏用
export const getMyTree = () => get('/admin/menu/my-tree')

// GET /api/admin/menu/all-tree  菜单管理页用（直读库）
export const getAllTree = () => get('/admin/menu/all-tree')

// POST /api/admin/menu
export const createMenu = (data) => post('/admin/menu', data)

// PUT /api/admin/menu/{id}
export const updateMenu = (id, data) => put(`/admin/menu/${id}`, data)

// DELETE /api/admin/menu/{id}
export const deleteMenu = (id) => del(`/admin/menu/${id}`)
