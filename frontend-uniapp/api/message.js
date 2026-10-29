// api/message.js — C 端站内消息接口（契约 6.1 #11/#12/#13/#14）
// C-end message APIs per SHARED_CONVENTIONS §6.1

import { get, put } from '@/common/request.js'

/**
 * 我的消息分页列表 / My messages paginated
 * GET /api/messages?page=1&page_size=15
 */
export function messageList(params = {}) {
  return get('/messages', params)
}

/**
 * 未读数 / Unread count
 * GET /api/messages/unread-count
 * @returns {Promise<{count:number}>}
 */
export function unreadCount() {
  return get('/messages/unread-count')
}

/**
 * 标记单条已读 / Mark one message read
 * PUT /api/messages/read/{id}
 * @param {number|string} id 消息 snow_id
 */
export function markRead(id) {
  return put(`/messages/read/${id}`)
}

/**
 * 全部已读 / Mark all as read
 * PUT /api/messages/read-all
 */
export function markAllRead() {
  return put('/messages/read-all')
}
