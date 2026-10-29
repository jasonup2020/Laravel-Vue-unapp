<template>
  <view class="container">
    <view class="row">
      <text>{{ t('dict.type') }}</text>
      <input v-model="type" class="type-input" :placeholder="t('dict.placeholder')" />
      <button class="btn-primary" size="mini" @click="load">{{ t('dict.load') }}</button>
    </view>

    <view v-if="options.length === 0" class="empty">{{ t('dict.empty') }}</view>
    <view v-for="opt in options" :key="opt.value" class="card">
      <text>{{ labelText(opt) }}</text>
      <text class="value">{{ opt.value }}</text>
    </view>
  </view>
</template>

<script>
// pages/dict/index.vue — 字典示例：按 type 加载选项
// Dict sample page: load options by type

import { dictByType } from '@/api/dict.js'
import { t, getLocale } from '@/common/i18n.js'

export default {
  data() {
    return {
      type: 'order_status',
      options: []
    }
  },
  onLoad() {
    this.load()
  },
  methods: {
    t,
    // 字典项 label 为双语对象 {zh-CN,en}，按当前语言取文本
    // Dict label is a bilingual object; render by current locale
    labelText(opt) {
      const l = opt && opt.label
      if (!l) return ''
      if (typeof l === 'string') return l
      const lang = getLocale() || 'zh-CN'
      return l[lang] || l['zh-CN'] || ''
    },
    async load() {
      if (!this.type) return
      try {
        // 接口返回 {list:[...]}（分页包装）；兼容直接数组两种形态
        // API returns {list:[...]} wrapper; also accept a bare array
        const res = await dictByType(this.type)
        this.options = Array.isArray(res) ? res : (res && Array.isArray(res.list) ? res.list : [])
      } catch (e) {
        this.options = []
      }
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.row { display: flex; align-items: center; padding: 20rpx 0; }
.type-input { flex: 1; border: 1rpx solid #eee; padding: 10rpx 20rpx; margin: 0 20rpx; }
.value { color: #999; margin-left: auto; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
