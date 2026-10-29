// 操作日志（契约 §6.2 #58）
import { get } from './request'

// GET /api/admin/operation-logs  分页
export const listOperationLogs = (params) => get('/admin/operation-logs', params)
