// uni-app Vue3 入口 / uni-app Vue3 entry
import { createSSRApp } from 'vue'
import App from './App.vue'
import { bootstrapI18n } from '@/common/i18n.js'

export function createApp() {
  const app = createSSRApp(App)
  // 启动时拉取语言包（失败用兜底文案，不阻塞）
  bootstrapI18n()
  return {
    app
  }
}
