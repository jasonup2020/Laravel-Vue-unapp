import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'

import * as ElementPlusIconsVue from '@element-plus/icons-vue'

import App from './App.vue'
import router from './router'
import request from '@/api/request'
import i18n, { bootstrapI18n } from '@/i18n'

const app = createApp(App)

// 注册全部 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.use(createPinia())
app.use(router)
app.use(i18n)

// Element Plus 组件文案由 App.vue 的 <el-config-provider :locale> 随语言切换
app.use(ElementPlus)

// 全局 axios 实例
app.config.globalProperties.$http = request

// 启动：先拉取语言包再挂载，避免首屏文案跳动；失败用兜底文案
bootstrapI18n().finally(() => {
  app.mount('#app')
})
// build cache bust: trigger vite full reload on entry change
