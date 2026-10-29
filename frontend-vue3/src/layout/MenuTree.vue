<template>
  <nav class="cm" :class="{ 'is-collapse': collapse }">
    <template v-for="node in tree" :key="node.snow_id ?? node.id">
      <!-- 外链 -->
      <a
        v-if="node.is_external"
        class="cm-item cm-leaf"
        :class="{ active: isActive(node) }"
        :href="node.external_url || node.path"
        target="_blank"
        rel="noopener"
        @click.stop
      >
        <el-icon v-if="node.icon" class="cm-ic"><component :is="node.icon" /></el-icon>
        <span v-if="!collapse" class="cm-txt">{{ displayTitle(node) }}</span>
      </a>

      <!-- 目录（有 children 且非外链） -->
      <div v-else-if="isDir(node)" class="cm-dir">
        <div
          class="cm-item cm-dir-title"
          :class="{ open: isOpen(node), active: dirActive(node) }"
          @click="toggle(node)"
        >
          <el-icon v-if="node.icon" class="cm-ic"><component :is="node.icon" /></el-icon>
          <span v-if="!collapse" class="cm-txt">{{ displayTitle(node) }}</span>
          <el-icon v-if="!collapse" class="cm-arrow" :class="{ open: isOpen(node) }"><ArrowDown /></el-icon>
        </div>
        <div v-show="isOpen(node) && !collapse" class="cm-sub" :style="{ paddingLeft: subPad }">
          <menu-tree :tree="node.children" :depth="depth + 1" />
        </div>
      </div>

      <!-- 普通叶子 -->
      <div v-else class="cm-item cm-leaf" :class="{ active: isActive(node) }" @click="go(node)">
        <el-icon v-if="node.icon" class="cm-ic"><component :is="node.icon" /></el-icon>
        <span v-if="!collapse" class="cm-txt">{{ displayTitle(node) }}</span>
      </div>
    </template>
  </nav>
</template>

<script setup>
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'

defineOptions({ name: 'MenuTree' })
const props = defineProps({
  tree: { type: Array, default: () => [] },
  depth: { type: Number, default: 0 },
  collapse: { type: Boolean, default: false },
})

const router = useRouter()
const route = useRoute()
const { t, te, locale } = useI18n()

// 展开状态由 layout 统一提供（顶层手风琴 + 嵌套独立）
const topCtl = inject('menuTopOpen', null)      // { open: ref<string|null>, toggle(id) }
const nestedCtl = inject('menuNestedOpen', null) // { open: reactive<{}>, toggle(id) }

const isDir = (node) => node.children && node.children.length && !node.is_external

/** 当前路由是否命中该节点（叶子高亮） */
const isActive = (node) => node.path === route.path

/** 目录是否含 active 子级（用于目录高亮/引导） */
const dirActive = (node) => {
  const walk = (ns) =>
    (ns || []).some((n) => (n.path === route.path) || (isDir(n) && walk(n.children)))
  return walk(node.children)
}

const nodeKey = (node) => String(node.snow_id ?? node.id)

/** 展开状态：顶层目录走手风琴 topCtl.open，嵌套目录走 nestedCtl.open */
const isOpen = (node) =>
  props.depth === 0
    ? !!topCtl && topCtl.open.value === nodeKey(node)
    : !!nestedCtl && !!nestedCtl.open[nodeKey(node)]

/** 切换：顶层手风琴（互斥），嵌套独立 toggle */
const toggle = (node) => {
  if (props.depth === 0) {
    if (topCtl) topCtl.toggle(nodeKey(node))
  } else if (nestedCtl) {
    nestedCtl.toggle(nodeKey(node))
  }
}

const go = (node) => {
  if (!node.path) return
  if (node.is_external) {
    window.open(node.path, '_blank', 'noopener')
    return
  }
  router.push(node.path)
}

const subPad = computed(() => (props.depth > 0 ? `${(props.depth) * 16}px` : '0'))

/**
 * 菜单 title 渲染：对象 { 'zh-CN', en } 按 locale；含点号按 i18n key；否则原样。
 */
function displayTitle(item) {
  const raw = item.title
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    const cur = locale.value || 'zh-CN'
    return raw[cur] || raw['zh-CN'] || ''
  }
  const str = raw || ''
  if (str.includes('.')) return te(str) ? t(str) : str
  return str
}
</script>

<style scoped>
.cm {
  background: #001529;
  color: #cfd8dc;
  user-select: none;
}
.cm-item {
  display: flex;
  align-items: center;
  height: 44px;
  padding: 0 16px;
  cursor: pointer;
  text-decoration: none;
  color: #cfd8dc;
  transition: background 0.2s, color 0.2s;
}
.cm-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
}
.cm-item.active {
  color: #409eff;
  background: rgba(64, 158, 255, 0.1);
}
.cm-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: #409eff;
}
.cm-item {
  position: relative;
}
.cm-ic {
  width: 20px;
  height: 20px;
  margin-right: 8px;
  flex-shrink: 0;
}
.cm-txt {
  flex: 1;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 14px;
}
.cm-arrow {
  margin-left: 8px;
  font-size: 12px;
  flex-shrink: 0;
  transition: transform 0.2s;
}
.cm-arrow.open {
  transform: rotate(180deg);
}
.cm-dir-title.open {
  color: #fff;
}
.cm-sub {
  overflow: hidden;
}
/* 折叠态：只保留图标 */
.cm.is-collapse .cm-item {
  justify-content: center;
  padding: 0;
}
.cm.is-collapse .cm-ic {
  margin-right: 0;
}
</style>
