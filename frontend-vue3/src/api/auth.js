// 管理员认证相关接口（契约 §6.2 #19-23）
import { get, post, put } from './request'

// POST /api/admin/login
export const adminLogin = (data) => post('/admin/login', data)

// POST /api/admin/refresh
export const adminRefresh = () => post('/admin/refresh')

// POST /api/admin/logout
export const adminLogout = () => post('/admin/logout')

// GET /api/admin/me
export const getProfile = () => get('/admin/me')

// PUT /api/admin/me/password
export const changeMyPassword = (data) => put('/admin/me/password', data)
