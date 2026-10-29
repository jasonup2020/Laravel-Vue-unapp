// api/dict.js — C 端数据字典接口（契约 6.1 #10）
// C-end dictionary APIs per SHARED_CONVENTIONS §6.1

import { get } from '@/common/request.js'

/**
 * 按 type 拉取字典选项 / Get dictionary options by type
 * GET /api/dicts/{type}
 * @param {string} type 字典类型标识，例如 order_status / pay_channel
 * @returns {Promise<Array<{label:string, value:string, sort:number}>>}
 */
export function dictByType(type) {
  return get(`/dicts/${type}`)
}
