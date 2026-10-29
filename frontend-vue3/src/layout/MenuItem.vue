<template>
  <!-- 外链菜单 -->
  <template v-if="item.is_external">
    <el-menu-item :index="item.external_url || item.path">
      <el-icon v-if="item.icon"><component :is="item.icon" /></el-icon>
      <template #title>{{ displayTitle(item) }}</template>
    </el-menu-item>
  </template>

  <!-- 目录（有 children 且自身非外链） -->
  <el-sub-menu v-else-if="item.children && item.children.length" :index="String(item.id)">
    <template #title>
      <el-icon v-if="item.icon"><component :is="item.icon" /></el-icon>
      <span>{{ displayTitle(item) }}</span>
    </template>
    <menu-item v-for="child in item.children" :key="child.id" :item="child" />
  </el-sub-menu>

  <!-- 普通菜单项 -->
  <el-menu-item v-else :index="item.path">
    <el-icon v-if="item.icon"><component :is="item.icon" /></el-icon>
    <template #title>{{ displayTitle(item) }}</template>
  </el-menu-item>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
defineProps({
  item: { type: Object, required: true }
})
const { t, te, locale } = useI18n()

/**
 * 菜单 title 渲染约定：
 * 1. title 为对象 { 'zh-CN': '中文', en: 'English' } → 按当前 locale 取值，回退 zh-CN
 * 2. title 为字符串且含点号（如 menu.dashboard）→ 按 i18n key 查；查不到原样返回
 * 3. 普通中文字符串 → 原样显示
 */
function displayTitle(item) {
  const raw = item.title
  // 对象格式：{ 'zh-CN': '...', en: '...' }
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    const cur = locale.value || 'zh-CN'
    return raw[cur] || raw['zh-CN'] || ''
  }
  const str = raw || ''
  if (str.includes('.')) {
    return te(str) ? t(str) : str
  }
  return str
}
</script>
