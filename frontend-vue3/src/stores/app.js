import { defineStore } from 'pinia'

const SIDEBAR_KEY = 'admin_sidebar_collapsed'

export const useAppStore = defineStore('app', {
  state: () => ({
    sidebarCollapsed: localStorage.getItem(SIDEBAR_KEY) === '1'
  }),
  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
      localStorage.setItem(SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0')
    }
  }
})
