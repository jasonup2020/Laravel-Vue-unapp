// 数据字典接口（契约 §6.2 #51-54）
import { get, post, put, del } from './request'

export const listDicts = (params) => get('/admin/dicts', params)
export const createDict = (data) => post('/admin/dicts', data)
export const updateDict = (id, data) => put(`/admin/dicts/${id}`, data)
export const deleteDict = (id) => del(`/admin/dicts/${id}`)
