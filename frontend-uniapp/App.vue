<script>
// 全局应用入口 / Global application entry
import { ensureFingerprint } from '@/common/fingerprint.js'
import wsClient from '@/common/ws.js'
import { syncLocale, getLocale } from '@/common/i18n.js'

export default {
  onLaunch() {
    ensureFingerprint()
    console.log('[App] onLaunch, fingerprint =', uni.getStorageSync('client_fingerprint'))

    const token = uni.getStorageSync('token')
    const userId = uni.getStorageSync('user_id')
    if (token && userId) {
      wsClient.connect(token, userId)
    }
  },
  onShow() {
    // 回前台时静默校验语言包 md5（有更新才拉全量）
    syncLocale(getLocale()).catch(() => {})

    const token = uni.getStorageSync('token')
    const userId = uni.getStorageSync('user_id')
    if (token && userId && !wsClient.isConnected()) {
      wsClient.connect(token, userId)
    }
  },
  onHide() {
    // 切后台可按需断开，避免长连接空跑；此处保留连接，按业务自行调整
    // Keep connection alive on hide; adjust per business needs
  }
}
</script>

<style>
/* 全局基础样式 / Global base styles */
page {
  background-color: #f5f5f5;
  font-size: 28rpx;
  color: #333;
}

.btn-primary {
  background-color: #2979ff;
  color: #fff;
  border-radius: 8rpx;
}

.card {
  background-color: #fff;
  border-radius: 12rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
}

/* ===== PC / 桌面端适配（C 端兼容 PC）=====
   手机端（<768px）保持原移动版：底部 tabBar + 单列布局。
   PC 端（≥768px）：移除 750px 限宽，内容全宽铺满；隐藏底部 tabBar（由 index.html 的 #pc-nav 顶部导航替代）；
   rpx 基准锁 32px 防止宽屏字号过度放大。 */
@media screen and (min-width: 768px) {
  html {
    font-size: 32px !important;
  }
  /* PC 内容区全宽（不再限 750px 居中） */
  page,
  uni-page-wrapper,
  uni-page-body {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    padding: 24px 32px;
    box-sizing: border-box;
  }
  /* PC 隐藏手机底部 tabBar */
  uni-tabbar .uni-tabbar-bottom {
    display: none !important;
  }
}

</style>
