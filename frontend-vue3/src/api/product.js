// 商品接口（契约 §6.2 #47-50）
import { get, post, put, del } from './request'

export const listProducts = (params) => get('/admin/products', params)
export const createProduct = (data) => post('/admin/products', data)
export const updateProduct = (id, data) => put(`/admin/products/${id}`, data)
export const deleteProduct = (id) => del(`/admin/products/${id}`)
