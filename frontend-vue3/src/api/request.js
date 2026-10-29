// axios 实例：统一 baseURL / 请求拦截 / 响应解包
//
// 契约（与后端"HTTP 一律 200"约定一致）：
//   成功：{ code: 0, msg: "...", data: ... }
//   失败：{ code: <非0业务码>, msg: "...", data: null }
//   - 1002 → 未认证/登录过期 → 清 token 跳登录
//   - 1003 → 无权限
//   - 1005 → 校验失败
//   - 1006 → 限流
//   兼容：HTTP 401（网关/极端场景）同样清 token 跳登录
import axios from 'axios'
import { ElMessage } from 'element-plus'
import { getFingerprint, genIdempotencyKey } from '@/utils/fingerprint'
import { getStoredLocale } from '@/i18n'

const TOKEN_KEY = 'admin_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}
export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}
export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  timeout: 15000
})

// 请求拦截
service.interceptors.request.use(
  (config) => {
    const token = getToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    // 多语言（后端 LangSwitch 识别 Accept-Language）
    config.headers['Accept-Language'] = getStoredLocale() || 'zh-CN'
    // 浏览器指纹（限流辅助）
    config.headers['X-Client-Fingerprint'] = getFingerprint()

    // 写请求自动带幂等 key
    const method = (config.method || 'get').toLowerCase()
    if (['post', 'put', 'delete', 'patch'].includes(method)) {
      config.headers['X-Idempotency-Key'] = genIdempotencyKey()
    }
    return config
  },
  (error) => Promise.reject(error)
)

// 响应拦截：后端 HTTP 一律 200，统一按 body.code 判断
service.interceptors.response.use(
  (response) => {
    // 文件下载等二进制直接放行
    if (response.config.responseType === 'blob') return response.data

    const body = response.data || {}
    // 兼容：如果 body 没有 code 字段（如静态资源），直接返回
    if (body.code === undefined) return body

    // 业务失败
    if (body.code !== 0) {
      const msg = body.msg || '请求失败'
      // 未认证/登录过期（业务码 1002）或 HTTP 401：清 token 并跳登录
      if (body.code === 1002 || body.code === 401) {
        ElMessage.error(msg || '登录已过期')
        clearToken()
        if (location.hash !== '#/login') location.hash = '#/login'
      } else if (body.code === 1003) {
        ElMessage.error(msg || '无权限访问')
      } else if (body.code === 1005) {
        ElMessage.error(msg || '输入校验失败')
      } else if (body.code === 1006) {
        ElMessage.error(msg || '请求过于频繁')
      } else {
        ElMessage.error(msg)
      }
      return Promise.reject(new Error(msg))
    }
    // 成功：返回完整 body（调用方用 .data 取业务数据）
    return body
  },
  (error) => {
    // HTTP 层异常（极端情况：网关 502/超时等）
    const status = error.response?.status
    const msg = error.response?.data?.msg || error.message || '网络错误'
    if (status === 401) {
      clearToken()
      if (location.hash !== '#/login') location.hash = '#/login'
    }
    ElMessage.error(msg)
    return Promise.reject(error)
  }
)

// 便捷方法：成功时返回 body.data
export async function get(url, params = {}, config = {}) {
  const res = await service.get(url, { params, ...config })
  return res?.data
}
export async function post(url, data = {}, config = {}) {
  const res = await service.post(url, data, config)
  return res?.data
}
export async function put(url, data = {}, config = {}) {
  const res = await service.put(url, data, config)
  return res?.data
}
export async function del(url, config = {}) {
  const res = await service.delete(url, config)
  return res?.data
}

export default service
