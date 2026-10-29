// 站内消息接口（契约 §6.2 #55-56）
import { get, post } from './request'

// GET /api/admin/messages  支持 receiver_type / msg_type 筛选
export const listMessages = (params) => get('/admin/messages', params)

// POST /api/admin/messages  body: { receiver_type, receiver_id?, msg_type, title, content, is_pop? }
export const sendMessage = (data) => post('/admin/messages', data)
