// api/auth.js — C 端认证接口（契约 6.1 #2/#3/#4/#5）
// C-end auth APIs per SHARED_CONVENTIONS §6.1

import { get, post, put } from '@/common/request.js'

/**
 * 登录 / Login
 * POST /api/login  data=token/expires_in
 * @param {{email:string, password:string}} payload
 * @returns {Promise<{token:string, expires_in:number}>}
 */
export function login(payload) {
  return post('/login', payload)
}

/**
 * 刷新 token / Refresh token
 * POST /api/refresh
 * @returns {Promise<{token:string, expires_in:number}>}
 */
export function refresh() {
  return post('/refresh')
}

/**
 * 登出 / Logout
 * POST /api/logout
 * @returns {Promise<any>}
 */
export function logout() {
  return post('/logout')
}

/**
 * 获取当前登录用户资料 / Get current user profile
 * GET /api/me
 * @returns {Promise<{snow_id:number, name:string, email:string}>}
 */
export function me() {
  return get('/me')
}

/**
 * 更新资料（可选）/ Update profile (optional)
 * PUT /api/me
 */
export function updateProfile(payload) {
  return put('/me', payload)
}
