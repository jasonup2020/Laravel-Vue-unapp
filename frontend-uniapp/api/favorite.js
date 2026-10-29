// api/favorite.js — C 端收藏接口（契约 6.1 #15/#16/#17/#18）
// C-end favorite APIs per SHARED_CONVENTIONS §6.1

import { get, post, del } from '@/common/request.js'

/**
 * 收藏商品 / Favorite a product
 * POST /api/favorites   { product_id }   重复→409
 * @param {number|string} productId 商品 snow_id
 */
export function addFavorite(productId) {
  return post('/favorites', { product_id: productId })
}

/**
 * 我的收藏分页列表 / My favorites paginated
 * GET /api/favorites?page=1&page_size=15
 */
export function favoriteList(params = {}) {
  return get('/favorites', params)
}

/**
 * 查询某商品是否已收藏 / Check favorited status of a product
 * GET /api/favorites/status/{productId}
 * @returns {Promise<{favorited:boolean, favorite_id?:number|string}>}
 */
export function favoriteStatus(productId) {
  return get(`/favorites/status/${productId}`)
}

/**
 * 取消收藏 / Cancel favorite (soft delete)
 * DELETE /api/favorites/{id}   id = favorite 记录 snow_id
 */
export function removeFavorite(id) {
  return del(`/favorites/${id}`)
}
