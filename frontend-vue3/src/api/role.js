// 角色 + 权限接口（契约 §6.2 #29-35）
import { get, post, put, del } from './request'

// GET /api/admin/permissions
export const listPermissions = (params) => get('/admin/permissions', params)
export const createPermission = (data) => post('/admin/permissions', data)
export const updatePermission = (id, data) => put(`/admin/permissions/${id}`, data)
export const deletePermission = (id) => del(`/admin/permissions/${id}`)

// GET /api/admin/roles
export const listRoles = (params) => get('/admin/roles', params)
export const createRole = (data) => post('/admin/roles', data)
export const updateRole = (id, data) => put(`/admin/roles/${id}`, data)
export const deleteRole = (id) => del(`/admin/roles/${id}`)

// PUT /api/admin/roles/{id}/permissions  body: { permission_ids: [] }
export const assignRolePermissions = (id, data) =>
  put(`/admin/roles/${id}/permissions`, data)

// PUT /api/admin/roles/{id}/menus  body: { menu_ids: [] }
export const assignRoleMenus = (id, data) =>
  put(`/admin/roles/${id}/menus`, data)
