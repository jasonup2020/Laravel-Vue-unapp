<template>
  <view class="container">
    <view v-if="product" class="card">
      <view class="name">{{ product.name }}</view>
      <view class="price">¥{{ product.price }}</view>
      <view class="desc">{{ product.desc }}</view>

      <button
        class="fav-btn"
        :class="{ faved: favorited }"
        :loading="favLoading"
        @click="onToggleFavorite"
      >
        {{ favorited ? t('product.favorited') : t('product.favorite') }}
      </button>
    </view>
    <view v-else class="empty">加载中…</view>
  </view>
</template>

<script>
// pages/product/detail.vue — 商品详情 + 收藏/取消收藏
// Product detail + favorite/unfavorite

import { productDetail } from '@/api/product.js'
import { addFavorite, removeFavorite, favoriteStatus } from '@/api/favorite.js'
import { t } from '@/common/i18n.js'

export default {
  data() {
    return {
      productId: 0,
      product: null,
      favorited: false,
      favoriteId: null,
      favLoading: false
    }
  },
  onLoad(query) {
    this.productId = query.id
    this.init()
  },
  methods: {
    t,
    async init() {
      try {
        this.product = await productDetail(this.productId)
        // 查询是否已收藏（需要登录；未登录会 401，request 内会自动跳登录）
        try {
          const s = await favoriteStatus(this.productId)
          this.favorited = !!s.favorited
          this.favoriteId = s.favorite_id || null
        } catch (e) {
          // 未登录场景下忽略
        }
      } catch (e) {
        // toast 已统一
      }
    },
    async onToggleFavorite() {
      this.favLoading = true
      try {
        if (this.favorited && this.favoriteId) {
          await removeFavorite(this.favoriteId)
          this.favorited = false
          this.favoriteId = null
          uni.showToast({ title: this.t('favorite.removed'), icon: 'none' })
        } else {
          const res = await addFavorite(this.productId)
          this.favorited = true
          // 后端 store 可返回新收藏记录 snow_id；若未返回则下次进详情时 status 接口自动补全
          this.favoriteId = (res && res.favorite_id) || null
          uni.showToast({ title: this.t('favorite.added'), icon: 'success' })
        }
      } catch (e) {
        // request 内已 toast
      } finally {
        this.favLoading = false
      }
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.name { font-size: 36rpx; font-weight: 600; }
.price { color: #dd524d; font-size: 32rpx; margin: 16rpx 0; }
.desc { color: #666; margin-bottom: 30rpx; }
.fav-btn { background: #eee; color: #333; }
.fav-btn.faved { background: #dd524d; color: #fff; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
