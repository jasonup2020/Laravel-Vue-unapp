<template>
  <el-container class="layout">
    <!-- 侧边栏 -->
    <el-aside :width="appStore.sidebarCollapsed ? '64px' : '220px'" class="aside">
      <div class="logo">
        <span v-if="!appStore.sidebarCollapsed">{{ t('ui.login.title') }}</span>
        <span v-else>L13</span>
      </div>
      <el-scrollbar>
        <menu-tree :tree="menuTree" :depth="0" :collapse="appStore.sidebarCollapsed" />
      </el-scrollbar>
    </el-aside>

    <el-container>
      <!-- 顶栏 -->
      <el-header class="header">
        <div class="left">
          <el-icon class="toggle" @click="appStore.toggleSidebar()">
            <Fold v-if="!appStore.sidebarCollapsed" />
            <Expand v-else />
          </el-icon>
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/dashboard' }">{{ t('ui.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item v-if="currentMenu || route.meta.title">{{ menuTitle(currentMenu ? currentMenu.title : route.meta.title) }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="right">
          <!-- 语言切换 -->
          <el-dropdown @command="onSwitchLang" style="margin-right: 16px">
            <el-button size="small">{{ currentLangLabel }}<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="zh-CN">{{ t('ui.lang_zh') }}</el-dropdown-item>
                <el-dropdown-item command="en">{{ t('ui.lang_en') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>

          <!-- 刷新：语言包 / 左边栏菜单 / 缓存 -->
          <el-dropdown @command="onRefreshCommand" style="margin-right: 16px">
            <el-button size="small" :loading="refreshing">
              <el-icon style="margin-right: 4px"><Refresh /></el-icon>
              {{ t('ui.refresh') }}<el-icon class="el-icon--right"><ArrowDown /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="locale">{{ t('ui.refresh_locale') }}</el-dropdown-item>
                <el-dropdown-item command="menu">{{ t('ui.refresh_menu') }}</el-dropdown-item>
                <el-dropdown-item command="cache">{{ t('ui.refresh_cache') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>

          <el-dropdown @command="onCommand">
            <span class="user">
              <el-avatar :size="28" :src="userStore.info?.avatar">
                {{ (userStore.info?.nickname || userStore.info?.username || 'A').slice(0, 1) }}
              </el-avatar>
              <span class="name">{{ userStore.info?.nickname || userStore.info?.username || t('ui.admin_fallback_name') }}</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">{{ t('ui.profile') }}</el-dropdown-item>
                <el-dropdown-item divided command="logout">{{ t('ui.logout') }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <!-- 内容区 -->
      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed, ref, reactive, provide, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { switchLocale, refreshCurrentLocale } from '@/i18n'
import { refreshLocaleCache } from '@/api/locale'
import MenuTree from './MenuTree.vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()
const { t, locale } = useI18n()

// 面包屑 title 兼容对象格式 { 'zh-CN': '...', en: '...' } 与字符串
function menuTitle(raw) {
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw[locale.value] || raw['zh-CN'] || ''
  }
  return raw
}

/** 从菜单树按 path 查当前菜单（面包屑用，取双语 title，与侧边栏一致） */
function findMenuByPath(nodes, path) {
  for (const n of nodes || []) {
    if (n.path === path) return n
    const hit = findMenuByPath(n.children, path)
    if (hit) return hit
  }
  return null
}
const currentMenu = computed(() => findMenuByPath(userStore.menuTree || [], route.path))

const menuTree = computed(() => userStore.menuTree || [])
const refreshing = ref(false)

/** 自定义菜单展开状态：顶层手风琴 + 嵌套目录独立展开 */
const menuTopOpen = ref(null)
const menuNestedOpen = reactive({})
function toggleTop(id) {
  menuTopOpen.value = menuTopOpen.value === id ? null : id
}
function toggleNested(id) {
  menuNestedOpen[id] = !menuNestedOpen[id]
}
provide('menuTopOpen', { open: menuTopOpen, toggle: toggleTop })
provide('menuNestedOpen', { open: menuNestedOpen, toggle: toggleNested })

/** 打开当前路由所在父级链：展开其顶层目录（手风琴）+ 逐级嵌套目录 */
function expandForPath() {
  const path = route.path
  const containsPath = (ns, p) =>
    (ns || []).some((n) => n.path === p || (n.children && n.children.length && containsPath(n.children, p)))
  const walkTop = (nodes) => {
    for (const n of nodes || []) {
      if (n.children && n.children.length) {
        if (containsPath(n.children, path)) return n
        const r = walkTop(n.children)
        if (r) return r
      }
    }
    return null
  }
  const top = walkTop(menuTree.value)
  menuTopOpen.value = top ? String(top.snow_id ?? top.id) : null
  const expandNested = (ns, chain) => {
    for (const n of ns || []) {
      if (n.path === path) {
        chain.forEach((id) => { menuNestedOpen[String(id)] = true })
        return true
      }
      if (n.children && n.children.length && expandNested(n.children, [...chain, n.snow_id ?? n.id])) return true
    }
    return false
  }
  expandNested(menuTree.value, [])
}
onMounted(expandForPath)
watch(() => route.path, expandForPath)
watch(menuTree, expandForPath, { deep: true })

const currentLangLabel = computed(() =>
  locale.value === 'en' ? t('ui.lang_en') : t('ui.lang_zh')
)

async function onSwitchLang(lang) {
  if (lang === locale.value) return
  try {
    await switchLocale(lang)
    ElMessage.success(t('ui.lang_switched'))
  } catch (e) {
    ElMessage.error(t('ui.lang_load_failed'))
  }
}

/** 刷新语言包：先调后端 admin 接口清缓存，再强拉当前语言包 */
async function onRefreshLocale() {
  refreshing.value = true
  try {
    try {
      await refreshLocaleCache()
    } catch (e) {
      console.warn('[i18n] 后端 locale/refresh 接口未就绪，仅前端强拉', e)
    }
    await refreshCurrentLocale(true)
    ElMessage.success(t('ui.updated'))
  } catch (e) {
    ElMessage.error(t('ui.refresh_failed'))
  } finally {
    refreshing.value = false
  }
}

/** 刷新下拉：语言包 / 左边栏菜单 / 缓存 */
async function onRefreshCommand(cmd) {
  refreshing.value = true
  try {
    if (cmd === 'locale') {
      try { await refreshLocaleCache() } catch (e) { console.warn('[i18n] 后端 locale/refresh 未就绪，仅前端强拉', e) }
      await refreshCurrentLocale(true)
    } else if (cmd === 'menu') {
      // 重新拉取左边栏菜单树并写入 localStorage，侧边栏实时刷新
      await userStore.fetchMenus()
    } else if (cmd === 'cache') {
      // 清空后端应用缓存（语言包/菜单/字典/C端菜单/限流规则等）
      const { flushCache } = await import('@/api/cache')
      await flushCache()
    }
    ElMessage.success(t('ui.updated'))
  } catch (e) {
    ElMessage.error(t('ui.refresh_failed'))
  } finally {
    refreshing.value = false
  }
}

async function onCommand(cmd) {
  if (cmd === 'profile') {
    router.push('/profile')
  } else if (cmd === 'logout') {
    await userStore.logout()
    router.replace('/login')
  }
}
</script>

<style scoped>
.layout { height: 100vh; }
.aside {
  background: #001529;
  transition: width 0.2s;
  overflow: hidden;
}
.logo {
  height: 56px;
  line-height: 56px;
  color: #fff;
  text-align: center;
  font-weight: 600;
  background: #002140;
  white-space: nowrap;
  overflow: hidden;
}
.header {
  background: #fff;
  border-bottom: 1px solid #eaeef3;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
}
.left { display: flex; align-items: center; gap: 16px; }
.toggle { font-size: 18px; cursor: pointer; }
.right { display: flex; align-items: center; gap: 8px; }
.right .user { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.name { font-size: 14px; }
.main { background: #f0f2f5; padding: 16px; overflow-x: auto; }

/* 窄屏（≤768px）响应式 */
@media (max-width: 768px) {
  .aside { width: 64px !important; }
  .header { padding: 0 8px; }
  .left .el-breadcrumb { display: none; }
  .right .name { display: none; }
  .right > .el-dropdown { margin-right: 8px; }
  .main { padding: 8px; }
  /* 表格横向滚动 */
  .main :deep(.el-table) { font-size: 12px; }
  .main :deep(.el-table .cell) { padding-left: 6px; padding-right: 6px; }
}
</style>
