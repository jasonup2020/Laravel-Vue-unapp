<template>
  <view class="container">
    <view class="toolbar">
      <text class="unread">{{ t('message.unread') }}{{ unread }}</text>
      <text class="all-read" @click="onMarkAll">{{ t('message.mark_all') }}</text>
    </view>

    <view v-if="list.length === 0 && !loading" class="empty">{{ t('message.empty') }}</view>

    <view
      v-for="item in list"
      :key="item.snow_id"
      class="card"
      :class="{ unread: !item.is_read }"
      @click="onTap(item)"
    >
      <view class="title-row">
        <text class="title">{{ item.title }}</text>
        <text v-if="!item.is_read" class="dot">●</text>
      </view>
      <view class="content">{{ item.content }}</view>
      <view class="time">{{ item.created_at }}</view>
    </view>

    <view class="loadmore" v-if="loading">加载中…</view>
    <view class="nomore" v-else-if="list.length >= total && total > 0">— 没有更多了 —</view>
  </view>
</template>

<script>
// pages/message/index.vue — 消息列表（tabBar）：未读角标 / 标记已读 / 下拉历史分页
// Message list (tabBar): unread badge, mark-read, pull-down history pagination

import { messageList, unreadCount, markRead, markAllRead } from '@/api/message.js'
import { t } from '@/common/i18n.js'

export default {
  data() {
    return {
      list: [],
      page: 1,
      pageSize: 15,
      total: 0,
      unread: 0,
      loading: false
    }
  },
  onShow() {
    // 每次显示刷新未读数（包括从其他 tab 回来）
    this.refreshUnread()
  },
  onLoad() {
    this.reload()
  },
  onPullDownRefresh() {
    // 下拉刷新：加载最新一页（历史向前分页）
    this.reload().finally(() => uni.stopPullDownRefresh())
  },
  onReachBottom() {
    if (this.list.length < this.total) {
      this.page++
      this.fetchList()
    }
  },
  methods: {
    t,
    async reload() {
      this.page = 1
      this.list = []
      await this.fetchList()
      await this.refreshUnread()
    },
    async fetchList() {
      this.loading = true
      try {
        const data = await messageList({ page: this.page, page_size: this.pageSize })
        this.list = this.page === 1 ? data.list : this.list.concat(data.list)
        this.total = data.total
      } catch (e) {
        // ignore
      } finally {
        this.loading = false
      }
    },
    async refreshUnread() {
      try {
        const data = await unreadCount()
        this.unread = data.count || 0
        // tabBar 角标（App/小程序支持；H5 忽略报错）
        uni.setTabBarBadge({ index: 1, text: String(this.unread) }).catch(() => {})
        if (this.unread === 0) {
          uni.removeTabBarBadge({ index: 1 }).catch(() => {})
        }
      } catch (e) {
        // 未登录时 401，忽略
      }
    },
    async onTap(item) {
      if (!item.is_read) {
        try {
          await markRead(item.snow_id)
          item.is_read = true
          this.unread = Math.max(0, this.unread - 1)
          uni.setTabBarBadge({ index: 1, text: String(this.unread) }).catch(() => {})
          if (this.unread === 0) {
            uni.removeTabBarBadge({ index: 1 }).catch(() => {})
          }
        } catch (e) { /* ignore */ }
      }
    },
    async onMarkAll() {
      try {
        await markAllRead()
        this.list.forEach((m) => { m.is_read = true })
        this.unread = 0
        uni.removeTabBarBadge({ index: 1 }).catch(() => {})
        uni.showToast({ title: this.t('message.mark_read'), icon: 'success' })
      } catch (e) { /* ignore */ }
    }
  }
}
</script>

<style scoped>
.container { padding: 20rpx; }
.toolbar { display: flex; justify-content: space-between; padding: 20rpx; }
.unread { color: #dd524d; }
.all-read { color: #2979ff; }
.title-row { display: flex; align-items: center; }
.title { font-size: 30rpx; font-weight: 600; flex: 1; }
.dot { color: #dd524d; font-size: 20rpx; margin-left: 10rpx; }
.content { color: #666; margin: 10rpx 0; }
.time { color: #999; font-size: 22rpx; }
.unread { border-left: 6rpx solid #dd524d; }
.loadmore, .nomore { text-align: center; color: #999; padding: 20rpx; }
.empty { text-align: center; color: #999; padding: 80rpx 0; }
</style>
