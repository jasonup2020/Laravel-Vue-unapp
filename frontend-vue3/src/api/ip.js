// IP 黑名单接口（契约 §6.2 #59-61）
import { get, post, del } from './request'

export const listIpBlacklist = (params) => get('/admin/ip-blacklist', params)
export const createIpBlacklist = (data) => post('/admin/ip-blacklist', data)
export const deleteIpBlacklist = (id) => del(`/admin/ip-blacklist/${id}`)
