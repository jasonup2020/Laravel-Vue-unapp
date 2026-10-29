// vite.config.js — uni-app (vue3) H5 构建配置
// Standard uni-app vue3 vite config. Dependencies resolve from the
// node_modules junction that points to HBuilderX's uniapp-cli-vite plugin.
import { defineConfig } from 'vite'
import uni from '@dcloudio/vite-plugin-uni'

export default defineConfig({
  plugins: [uni()]
})
