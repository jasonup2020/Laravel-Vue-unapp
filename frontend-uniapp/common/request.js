// common/request.js — uni.request 统一封装
// Unified uni.request wrapper
//
// 约定（与 SHARED_CONVENTIONS §5.3 / §6 / §7 对齐）：
//   - baseURL = '/api'（H5 由 manifest.json 中 devServer.proxy 代理到后端）
//   - 请求头统一携带：
//       Authorization: Bearer <token>   （存在 token 时）
//       Accept-Language: zh-CN          （后端 LangSwitch 中间件解析）
//       X-Client-Fingerprint: <uuid>    （后端复合限流游客维度）
//       X-Idempotency-Key: <uuid>       （仅 POST/PUT/DELETE 写请求自动附加）
//   - 统一解包响应 {success, code, msg, data}：
//       * success=true  → resolve(data)
//       * success=false → toast(msg) 后 reject；code=401 时清 token 跳登录
//   - 分页接口返回 data 结构为 { list, total, page, page_size }
//
// 导出：get/post/put/del 四个方法。

import { getFingerprint, newIdempotencyKey } from './fingerprint.js'
import { getLocale } from './i18n.js'

// 后端 API 前缀 / backend API prefix
// H5 下通过 manifest.json devServer.proxy 代理到 http://localhost:8000；
// 小程序/App 下请改为完整 https 域名（例如 https://api.example.com）。
const BASE_URL = '/api'

const TOKEN_KEY = 'token'
const USER_ID_KEY = 'user_id'

/**
 * 内部底层请求函数 / Low-level request wrapper
 * @param {Object} options
 * @param {string} options.url        相对 BASE_URL 的路径，例如 /products
 * @param {string} [options.method]   GET/POST/PUT/DELETE
 * @param {Object} [options.data]     请求体/查询串
 * @param {Object} [options.header]   额外请求头
 * @param {number} [options.timeout]  毫秒
 * @returns {Promise<any>} 成功 resolve(data)，失败 reject(Error)
 */
function request({ url, method = 'GET', data = {}, header = {}, timeout = 15000 }) {
  return new Promise((resolve, reject) => {
    // 组装默认头 / assemble default headers
    const finalHeader = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Accept-Language': getLocale() || 'zh-CN',
      'X-Client-Fingerprint': getFingerprint(),
      ...header
    }

    // 登录后携带 Bearer Token / attach Bearer token when available
    const token = uni.getStorageSync(TOKEN_KEY)
    if (token) {
      finalHeader['Authorization'] = 'Bearer ' + token
    }

    // 写请求自动附加幂等 Key / auto attach idempotency key for write requests
    const upperMethod = String(method).toUpperCase()
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(upperMethod)) {
      finalHeader['X-Idempotency-Key'] = newIdempotencyKey()
    }

    uni.request({
      url: BASE_URL + url,
      method: upperMethod,
      data,
      header: finalHeader,
      timeout,
      success: (res) => {
        const body = res.data || {}
        // 后端 HTTP 一律 200，按 body.code 判断；code=0 成功
        if (typeof body !== 'object' || body.code === undefined) {
          uni.showToast({ title: '网络异常', icon: 'none' })
          return reject(new Error('bad response'))
        }

        // 业务失败 / business failure
        if (body.code !== 0) {
          const code = body.code
          const msg = body.msg || '请求失败'

          // 401：未登录 / token 过期 / 被踢下线 → 清登录态跳登录
          if (code === 401) {
            uni.removeStorageSync(TOKEN_KEY)
            uni.removeStorageSync(USER_ID_KEY)
            uni.showToast({ title: msg || '登录已失效', icon: 'none' })
            setTimeout(() => {
              uni.reLaunch({ url: '/pages/login/index' })
            }, 800)
            return reject(new Error(msg))
          }

          uni.showToast({ title: msg, icon: 'none' })
          return reject(new Error(msg))
        }

        // 成功解包出 data
        resolve(body.data)
      },
      fail: (err) => {
        uni.showToast({ title: '网络请求失败', icon: 'none' })
        reject(err)
      }
    })
  })
}

export function get(url, data = {}, header = {}) {
  return request({ url, method: 'GET', data, header })
}

export function post(url, data = {}, header = {}) {
  return request({ url, method: 'POST', data, header })
}

export function put(url, data = {}, header = {}) {
  return request({ url, method: 'PUT', data, header })
}

export function del(url, data = {}, header = {}) {
  return request({ url, method: 'DELETE', data, header })
}

export default { get, post, put, del, BASE_URL }
