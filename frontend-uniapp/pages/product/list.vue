<template>
  <view class="container">
    <view v-if="list.length === 0 && !loading" class="empty">暂无商品</view>

    <view v-for="item in list" :key="item.snow_id" class="card" @click="goDetail(item)">
      <view class="name">{{ item.name }}</view>
      <view class="price">¥{{ item.price }}</view>
      <view class="desc">{{ item.desc }}</view>
    </view>

    <view class="loadmore" v-if="loading">加载中…</view>
    <view class="nomore" v-else-if="list.length >= total && total > 0">— 没有更多了 —</view>
  </view>
</template>

<script>
// pages/product/list.vue — 商品分页列表页
// Product paginated list page

import { productList } from '@/api/product.js'

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
  onLoad() {
    this.fetchList()
  },
  onReachBottom() {
    if (this.list.length < this.total) {
      this.page++
      this.fetchList()
    }
  },
  onPullDownRefresh() {
    this.page = 1
    this.list = []
    this.fetchList().finally(() => uni.stopPullDownRefresh())
  },
  methods: {
    async fetchList() {
      this.loading = true
      try {
        const data = await productList({ page: this.page, page_size: this.pageSize })
        this.list = this.page === 1 ? data.list : this.list.concat(data.list)
        this.total = data.total
      } catch (e) {
        // ignore
      } finally {
        this.loading = false
      }
    },
    goDetail(item) {
      uni.navigateTo({ url: `/pages/product/detail?id=${item.snow_id}` })
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.name { font-size: 30rpx; font-weight: 600; }
.price { color: #dd524d; margin: 10rpx 0; }
.desc { color: #999; font-size: 24rpx; }
.loadmore, .nomore { text-align: center; color: #999; padding: 20rpx; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
