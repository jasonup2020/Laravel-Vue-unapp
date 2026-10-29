// 管理员（B 端）+ 普通用户（C 端）管理接口（契约 §6.2 #36-46）
import { get, post, put, del } from './request'

// ===== 管理员自身账号（B 端 admin） =====
export const listAdmins = (params) => get('/admin/admins', params)
export const createAdmin = (data) => post('/admin/admins', data)
export const updateAdmin = (id, data) => put(`/admin/admins/${id}`, data)
export const resetAdminPassword = (id, data) =>
  put(`/admin/admins/${id}/password`, data)
export const updateAdminStatus = (id, data) =>
  put(`/admin/admins/${id}/status`, data)
export const deleteAdmin = (id) => del(`/admin/admins/${id}`)

// ===== 普通用户（C 端 users）后台管理 =====
export const listUsers = (params) => get('/admin/users', params)
export const getUserDetail = (id) => get(`/admin/users/${id}`)
export const updateUserStatus = (id, data) =>
  put(`/admin/users/${id}/status`, data)
export const getUserOnline = (id) => get(`/admin/users/${id}/online`)
export const kickUser = (id) => post(`/admin/users/${id}/kick`)
