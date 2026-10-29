import { defineStore } from 'pinia'
import { adminLogin, adminLogout, getProfile } from '@/api/auth'
import { getMyTree } from '@/api/menu'
import { getToken, setToken, clearToken } from '@/api/request'

const USER_KEY = 'admin_user_info'
const MENUS_KEY = 'admin_menu_tree'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken(),
    // 个人信息 { id, username, nickname, avatar, roles:[] }
    info: JSON.parse(localStorage.getItem(USER_KEY) || 'null'),
    // 侧边栏菜单树（my-tree 返回）
    menuTree: JSON.parse(localStorage.getItem(MENUS_KEY) || '[]')
  }),
  actions: {
    async login(form) {
      const data = await adminLogin(form)
      // data 形如 { token, expires_in } 或 { admin: {...}, token, expires_in }
      this.token = data.token
      setToken(data.token)
      // 拉取个人信息与菜单
      await Promise.all([this.fetchProfile(), this.fetchMenus()])
      return data
    },
    async fetchProfile() {
      try {
        const profile = await getProfile()
        this.info = profile
        localStorage.setItem(USER_KEY, JSON.stringify(profile || null))
      } catch (e) {
        // 401 已在拦截器处理
      }
    },
    async fetchMenus() {
      const res = await getMyTree()
      // 后端可能返回 { tree: [...] } 或直接 [...]；解包为数组
      const tree = (res && Array.isArray(res.tree)) ? res.tree
        : (Array.isArray(res) ? res : [])
      this.menuTree = tree
      localStorage.setItem(MENUS_KEY, JSON.stringify(this.menuTree))
      return this.menuTree
    },
    async logout() {
      try {
        await adminLogout()
      } catch (e) {
        // 即使后端调用失败也本地清理
      }
      this.reset()
    },
    reset() {
      this.token = ''
      this.info = null
      this.menuTree = []
      clearToken()
      localStorage.removeItem(USER_KEY)
      localStorage.removeItem(MENUS_KEY)
    }
  }
})
