<template>
  <view class="container">
    <view class="card">
      <!-- 语言选择 -->
      <view class="lang-row">
        <picker :value="langIndex" :range="langOptions" @change="onLangChange">
          <view class="lang-picker">{{ langOptions[langIndex] }} ▾</view>
        </picker>
      </view>

      <view class="title">{{ t('login.title') }}</view>

      <input v-model="email" class="input" :placeholder="t('login.username')" type="text" />
      <input v-model="password" class="input" :placeholder="t('login.password')" type="password" />

      <!-- 验证码 -->
      <view class="captcha-row" v-if="captcha.image">
        <input v-model="captchaCode" class="input captcha-input" :placeholder="t('login.captcha')" type="text" />
        <image class="captcha-img" :src="captcha.image" @click="refreshCaptcha" mode="aspectFit" />
      </view>
      <view class="captcha-row" v-else>
        <text class="captcha-ph" @click="refreshCaptcha">{{ t('login.captcha_load') }}</text>
      </view>

      <button class="btn-primary" :loading="loading" @click="onSubmit">{{ t('login.submit') }}</button>
    </view>
  </view>
</template>

<script>
// pages/login/index.vue — 登录页（含验证码 + 语言选择）
import { login, me } from '@/api/auth.js'
import { get } from '@/common/request.js'
import wsClient from '@/common/ws.js'
import { t, getLocale, switchLocale, SUPPORT_LOCALES } from '@/common/i18n.js'

export default {
  data() {
    return {
      email: '',
      password: '',
      captcha_id: '',
      captchaCode: '',
      captcha: { captcha_id: '', image: '' },
      loading: false,
      langOptions: ['简体中文', 'English']
    }
  },
  computed: {
    langIndex() { return getLocale() === 'en' ? 1 : 0 }
  },
  onLoad() {
    this.refreshCaptcha()
  },
  methods: {
    t,
    async refreshCaptcha() {
      try {
        const data = await get('/captcha')
        this.captcha = { captcha_id: data.captcha_id, image: data.image }
        this.captchaCode = ''
      } catch (e) {
        this.captcha = { captcha_id: '', image: '' }
      }
    },
    async onLangChange(e) {
      const idx = e.detail.value
      await switchLocale(SUPPORT_LOCALES[idx])
    },
    async onSubmit() {
      if (!this.email || !this.password) {
        return uni.showToast({ title: this.t('login.required'), icon: 'none' })
      }
      this.loading = true
      try {
        const data = await login({
          email: this.email,
          password: this.password,
          captcha_id: this.captcha.captcha_id || undefined,
          captcha_code: this.captchaCode || undefined
        })
        uni.setStorageSync('token', data.token)
        const profile = await me()
        uni.setStorageSync('user_id', profile.id || profile.snow_id)
        uni.setStorageSync('user_info', profile)
        wsClient.connect(data.token, profile.id || profile.snow_id, {
          onMessage: () => uni.showToast({ title: this.t('common.new_message'), icon: 'none' })
        })
        uni.showToast({ title: this.t('login.success'), icon: 'success' })
        setTimeout(() => uni.reLaunch({ url: '/pages/index/index' }), 600)
      } catch (e) {
        this.refreshCaptcha()
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style scoped>
.container { padding: 40rpx; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1f3c88 0%, #001529 100%); }
.card { width: 100%; background: #fff; border-radius: 16rpx; padding: 40rpx; box-sizing: border-box; }
.lang-row { text-align: right; margin-bottom: 20rpx; }
.lang-picker { font-size: 24rpx; color: #2979ff; }
.title { font-size: 40rpx; font-weight: 600; margin-bottom: 30rpx; text-align: center; }
.input { border-bottom: 1rpx solid #eee; padding: 20rpx 0; margin-bottom: 20rpx; font-size: 28rpx; }
.captcha-row { display: flex; align-items: center; gap: 16rpx; margin-bottom: 20rpx; }
.captcha-input { flex: 1; margin-bottom: 0; }
.captcha-img { width: 200rpx; height: 72rpx; border: 1rpx solid #eee; border-radius: 8rpx; }
.captcha-ph { color: #999; font-size: 24rpx; padding: 16rpx 24rpx; border: 1rpx dashed #ddd; border-radius: 8rpx; }
.btn-primary { margin-top: 30rpx; }
</style>
