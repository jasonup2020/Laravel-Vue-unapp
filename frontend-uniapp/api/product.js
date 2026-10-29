// api/product.js — C 端商品只读接口（契约 6.1 #8/#9）
// C-end product read-only APIs per SHARED_CONVENTIONS §6.1

import { get } from '@/common/request.js'

/**
 * 商品分页列表 / Product paginated list
 * GET /api/products?page=1&page_size=15
 * @param {{page?:number, page_size?:number}} params
 * @returns {Promise<{list:Array, total:number, page:number, page_size:number}>}
 */
export function productList(params = {}) {
  return get('/products', params)
}

/**
 * 商品详情 / Product detail
 * GET /api/products/{snow_id}
 * @param {number|string} id 商品 snow_id（后端不暴露自增 id）
 */
export function productDetail(id) {
  return get(`/products/${id}`)
}
