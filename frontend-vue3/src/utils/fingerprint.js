// 浏览器指纹 & 幂等 key 工具
// - X-Client-Fingerprint：首次访问生成 uuid 持久化到 localStorage，供后端复合限流使用
// - 写请求自动生成 X-Idempotency-Key（uuid v4），配合后端 idempotency 中间件防重复提交

const FP_KEY = 'admin_client_fingerprint'

function uuidv4() {
  // 优先原生 crypto.randomUUID，降级到手动生成
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

/** 获取或生成客户端指纹（持久化） */
export function getFingerprint() {
  try {
    let fp = localStorage.getItem(FP_KEY)
    if (!fp) {
      fp = uuidv4()
      localStorage.setItem(FP_KEY, fp)
    }
    return fp
  } catch (e) {
    // localStorage 不可用（隐私模式等），每次生成新的
    return uuidv4()
  }
}

/** 生成一次性幂等 key */
export function genIdempotencyKey() {
  return uuidv4()
}
