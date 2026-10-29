<template>
  <view class="container">
    <view v-if="list.length === 0 && !loading" class="empty">{{ t('favorite.empty') }}</view>

    <view v-for="item in list" :key="item.snow_id" class="card">
      <view class="row" @click="goDetail(item.product)">
        <view class="name">{{ item.product && item.product.name }}</view>
        <view class="price">¥{{ item.product && item.product.price }}</view>
      </view>
      <view class="actions">
        <text class="cancel" @click="onCancel(item)">{{ t('favorite.remove') }}</text>
      </view>
    </view>

    <view class="loadmore" v-if="loading">加载中…</view>
  </view>
</template>

<script>
// pages/favorite/index.vue — 我的收藏列表
// My favorites list

import { favoriteList, removeFavorite } from '@/api/favorite.js'
import { t } from '@/common/i18n.js'

export default {
  data() {
    return {
      list: [],
      page: 1,
      pageSize: 15,
      total: 0,
      loading: false
    }
  },
  onShow() {
    this.reload()
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
        const data = await favoriteList({ page: this.page, page_size: this.pageSize })
        this.list = this.page === 1 ? data.list : this.list.concat(data.list)
        this.total = data.total
      } catch (e) { /* ignore */ } finally {
        this.loading = false
      }
    },
    goDetail(product) {
      if (product && product.snow_id) {
        uni.navigateTo({ url: `/pages/product/detail?id=${product.snow_id}` })
      }
    },
    async onCancel(item) {
      try {
        await removeFavorite(item.snow_id)
        this.list = this.list.filter((i) => i.snow_id !== item.snow_id)
        uni.showToast({ title: this.t('favorite.removed'), icon: 'none' })
      } catch (e) { /* ignore */ }
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.row { display: flex; justify-content: space-between; align-items: center; }
.name { font-size: 30rpx; font-weight: 600; }
.price { color: #dd524d; }
.actions { margin-top: 16rpx; text-align: right; }
.cancel { color: #dd524d; font-size: 26rpx; }
.loadmore { text-align: center; color: #999; padding: 20rpx; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
