// common/fingerprint.js — 客户端指纹 + 幂等 Key 生成
// Client fingerprint & idempotency key generator
//
// 说明：后端复合限流（SHARED_CONVENTIONS §6）在用户未登录时使用
// X-Client-Fingerprint 作为维度 key；幂等中间件需要前端为写请求生成
// X-Idempotency-Key（UUID/v4）。本模块负责：
//   1) 首次启动生成一个 UUID，持久化到本地存储，后续所有请求复用；
//   2) 每次写请求调用 newIdempotencyKey() 现场生成一个新 UUID。

const FP_STORAGE_KEY = 'client_fingerprint'

/**
 * 生成一个 RFC4122 v4 UUID / Generate a RFC4122 v4 UUID
 * @returns {string}
 */
export function uuidv4() {
  // 优先使用 crypto.randomUUID（H5 现代浏览器），否则降级到 Math.random
  // Prefer crypto.randomUUID on H5 modern browsers; fallback to Math.random
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

/**
 * 获取（或首次生成）持久化的客户端指纹
 * Get (or first-time generate) the persistent client fingerprint
 * @returns {string}
 */
export function getFingerprint() {
  let fp = uni.getStorageSync(FP_STORAGE_KEY)
  if (!fp) {
    fp = uuidv4()
    uni.setStorageSync(FP_STORAGE_KEY, fp)
  }
  return fp
}

/**
 * 确保本地已经有指纹（App onLaunch 调用一次即可）
 * Ensure local fingerprint exists (called once on App onLaunch)
 */
export function ensureFingerprint() {
  getFingerprint()
}

/**
 * 生成一次幂等 Key（写请求使用，每次请求一个新值）
 * Generate a one-off idempotency key (one per write request)
 * @returns {string}
 */
export function newIdempotencyKey() {
  return uuidv4()
}
