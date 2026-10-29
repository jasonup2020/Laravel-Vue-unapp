<template>
  <view class="container">
    <view class="header">
      <text class="hello">{{ t('home.title') }}</text>
      <view class="entry" @click="goDict">{{ t('home.dict_entry') }}</view>
    </view>

    <!-- 功能入口卡片网格（从 GET /api/menus 加载） -->
    <view class="menu-grid" v-if="menus.length">
      <view v-for="m in menus" :key="m.snow_id" class="menu-item" @click="goMenu(m)">
        <view class="menu-icon">{{ m.icon || '▦' }}</view>
        <view class="menu-name">{{ menuTitle(m) }}</view>
      </view>
    </view>

    <view v-if="list.length === 0 && !loading" class="empty">{{ t('home.empty') }}</view>

    <view class="product-list">
      <view v-for="item in list" :key="item.snow_id" class="card" @click="goDetail(item)">
        <view class="name">{{ item.name }}</view>
        <view class="price">¥{{ item.price }}</view>
        <view class="desc">{{ item.desc }}</view>
      </view>
    </view>

    <view class="loadmore" v-if="loading">…</view>
  </view>
</template>

<script>
// pages/index/index.vue — 首页（tabBar）：功能入口网格 + 商品分页列表
import { productList } from '@/api/product.js'
import { get } from '@/common/request.js'
import { t, getLocale } from '@/common/i18n.js'

const MENU_CACHE = 'c_menus_cache'

export default {
  data() {
    return {
      list: [],
      page: 1,
      pageSize: 10,
      total: 0,
      loading: false,
      menus: []
    }
  },
  onShow() {
    this.reload()
    this.loadMenus()
  },
  onPullDownRefresh() {
    Promise.all([this.reload(), this.loadMenus(true)]).finally(() => uni.stopPullDownRefresh())
  },
  onReachBottom() {
    if (this.list.length < this.total) {
      this.page++
      this.fetchList()
    }
  },
  methods: {
    t,
    reload() {
      this.page = 1
      this.list = []
      this.fetchList()
    },
    async fetchList() {
      this.loading = true
      try {
        const data = await productList({ page: this.page, page_size: this.pageSize })
        this.list = this.page === 1 ? data.list : this.list.concat(data.list)
        this.total = data.total
      } catch (e) {
        // toast 已在 request 内处理
      } finally {
        this.loading = false
      }
    },
    // 加载 C 端菜单（本地缓存优先 + 远程刷新）
    async loadMenus(force) {
      if (!force) {
        try {
          const cached = uni.getStorageSync(MENU_CACHE)
          if (cached) this.menus = JSON.parse(cached)
        } catch (e) {}
      }
      try {
        const data = await get('/menus')
        const arr = Array.isArray(data) ? data : (data?.list || [])
        this.menus = arr
        uni.setStorageSync(MENU_CACHE, JSON.stringify(arr))
      } catch (e) {
        // 远程失败用缓存
      }
    },
    // 双语标题：title 为对象 {zh-CN,en} 按 locale 取；字符串 + title_en 字段按 locale 拼
    menuTitle(m) {
      const cur = getLocale()
      if (m.title && typeof m.title === 'object' && !Array.isArray(m.title)) {
        return m.title[cur] || m.title['zh-CN'] || ''
      }
      if (cur === 'en' && m.title_en) return m.title_en
      return m.title || ''
    },
    goMenu(m) {
      // 后端不返回 is_external：有 http(s):// url 按外链处理，否则按 path 跳转
      if (m.url && /^https?:\/\//i.test(m.url)) {
        // #ifdef H5
        window.open(m.url, '_blank')
        // #endif
        // #ifndef H5
        uni.setClipboardData({ data: m.url })
        // #endif
        return
      }
      const path = m.path
      if (!path) return
      uni.navigateTo({
        url: path,
        fail: () => uni.showToast({ title: this.t('home.page_not_found'), icon: 'none' })
      })
    },
    goDetail(item) {
      uni.navigateTo({ url: `/pages/product/detail?id=${item.snow_id}` })
    },
    goDict() {
      uni.navigateTo({ url: '/pages/dict/index' })
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.header { display: flex; justify-content: space-between; align-items: center; padding: 20rpx; }
.hello { font-size: 32rpx; font-weight: 600; }
.entry { color: #2979ff; font-size: 26rpx; }

.menu-grid { display: flex; flex-wrap: wrap; padding: 10rpx; }
.menu-item { width: 25%; box-sizing: border-box; padding: 16rpx; text-align: center; }
.menu-icon { font-size: 48rpx; line-height: 1; }
.menu-name { font-size: 22rpx; margin-top: 8rpx; color: #333; }

/* PC 多列布局：功能入口 6 列，商品 4 列网格 */
@media screen and (min-width: 768px) {
  .menu-item { width: 16%; }
  .product-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .product-list .card { margin-bottom: 0; }
}

.name { font-size: 30rpx; font-weight: 600; }
.price { color: #dd524d; margin: 10rpx 0; }
.desc { color: #999; font-size: 24rpx; }
.loadmore { text-align: center; color: #999; padding: 20rpx; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
