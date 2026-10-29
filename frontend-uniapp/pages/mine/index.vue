<template>
  <view class="container">
    <view class="card">
      <view class="avatar">U</view>
      <view class="info">
        <view class="name">{{ profile.name || t('mine.guest') }}</view>
        <view class="email">{{ profile.email || '' }}</view>
      </view>
    </view>

    <!-- 多语言区域 -->
    <view class="card">
      <text>{{ t('common.language') || '语言' }}</text>
      <picker :value="langIndex" :range="langOptions" @change="onLangChange">
        <view class="lang-picker">{{ langOptions[langIndex] }} ▾</view>
      </picker>
    </view>
    <view class="card" @click="onRefreshLocale">
      <text>{{ t('common.refreshLocale') || '刷新语言包' }}</text>
      <text class="arrow">↻</text>
    </view>

    <view class="card" @click="goFavorites">
      <text>{{ t('mine.favorites') }}</text>
      <text class="arrow">›</text>
    </view>

    <view class="card" @click="goProductList">
      <text>{{ t('mine.products') }}</text>
      <text class="arrow">›</text>
    </view>

    <button v-if="isLogin" class="btn-primary logout" @click="onLogout">
      {{ t('mine.logout') || '退出登录' }}
    </button>
    <button v-else class="btn-primary" @click="goLogin">{{ t('mine.go_login') }}</button>

    <view class="tip">
      {{ t('mine.tip') }}
    </view>
  </view>
</template>

<script>
// pages/mine/index.vue — 我的（tabBar）：用户信息 / 语言切换 / 退出登录
import { me, logout } from '@/api/auth.js'
import wsClient from '@/common/ws.js'
import { t, state, getLocale, switchLocale, refreshLocale, SUPPORT_LOCALES } from '@/common/i18n.js'

export default {
  data() {
    return {
      profile: {},
      langOptions: ['简体中文', 'English']
    }
  },
  computed: {
    isLogin() {
      return !!uni.getStorageSync('token')
    },
    langIndex() {
      return getLocale() === 'en' ? 1 : 0
    }
  },
  onShow() {
    if (this.isLogin) this.fetchProfile()
    else this.profile = {}
  },
  methods: {
    t,
    async fetchProfile() {
      try {
        this.profile = await me()
      } catch (e) {}
    },
    goLogin() {
      uni.navigateTo({ url: '/pages/login/index' })
    },
    goFavorites() {
      uni.navigateTo({ url: '/pages/favorite/index' })
    },
    goProductList() {
      uni.navigateTo({ url: '/pages/product/list' })
    },
    async onLangChange(e) {
      const idx = e.detail.value
      const lang = SUPPORT_LOCALES[idx]
      try {
        await switchLocale(lang)
        uni.showToast({ title: this.t('common.updated'), icon: 'success' })
      } catch (err) {
        uni.showToast({ title: this.t('mine.lang_fail'), icon: 'none' })
      }
    },
    async onRefreshLocale() {
      try {
        await refreshLocale()
        uni.showToast({ title: this.t('common.updated') || '语言已更新', icon: 'success' })
      } catch (e) {
        uni.showToast({ title: this.t('mine.refresh_fail'), icon: 'none' })
      }
    },
    async onLogout() {
      try { await logout() } catch (e) {}
      wsClient.disconnect()
      uni.removeStorageSync('token')
      uni.removeStorageSync('user_id')
      uni.removeStorageSync('user_info')
      uni.showToast({ title: this.t('mine.logged_out'), icon: 'success' })
      setTimeout(() => uni.reLaunch({ url: '/pages/login/index' }), 600)
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.card { display: flex; align-items: center; justify-content: space-between; padding: 24rpx; background: #fff; border-radius: 12rpx; margin-bottom: 20rpx; }
.avatar { width: 80rpx; height: 80rpx; border-radius: 50%; background: #2979ff; color: #fff; text-align: center; line-height: 80rpx; margin-right: 20rpx; }
.info { flex: 1; }
.name { font-size: 32rpx; font-weight: 600; }
.email { color: #999; font-size: 24rpx; }
.arrow { color: #999; }
.lang-picker { color: #2979ff; }
.logout { margin-top: 40rpx; }
.tip { color: #999; font-size: 22rpx; padding: 30rpx 20rpx; line-height: 1.6; }
</style>
