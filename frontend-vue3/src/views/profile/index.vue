<template>
  <el-card>
    <template #header><span>{{ t('ui.profile') }}</span></template>
    <div class="head">
      <el-avatar :size="72" :src="userStore.info?.avatar">{{ initial }}</el-avatar>
      <h2 class="name">{{ userStore.info?.nickname || userStore.info?.username || t('ui.admin_fallback_name') }}</h2>
    </div>
    <el-descriptions :column="1" border>
      <el-descriptions-item :label="t('ui.profile_username')">{{ userStore.info?.username || '-' }}</el-descriptions-item>
      <el-descriptions-item :label="t('ui.profile_nickname')">{{ userStore.info?.nickname || '-' }}</el-descriptions-item>
      <el-descriptions-item :label="t('ui.profile_email')">{{ userStore.info?.email || '-' }}</el-descriptions-item>
      <el-descriptions-item :label="t('ui.profile_roles')">
        <template v-if="roles.length">
          <el-tag v-for="r in roles" :key="r" style="margin-right:6px">{{ r }}</el-tag>
        </template>
        <span v-else>-</span>
      </el-descriptions-item>
    </el-descriptions>
  </el-card>
</template>

<script setup>
// 个人中心：展示当前登录管理员的账号信息与角色
// Profile: display the currently logged-in admin account info and roles
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/stores/user'

const { t } = useI18n()
const userStore = useUserStore()

// roles 可能是数组或单值；元素可能是字符串或 {name/code} 对象，统一为字符串数组
// roles may be an array or a single value; elements may be strings or {name/code}
// objects; normalize to a string array
const roles = computed(() => {
  const r = userStore.info?.roles
  const list = Array.isArray(r) ? r : (r ? [r] : [])
  return list
    .map((x) => (typeof x === 'string' ? x : (x?.name || x?.code || '')))
    .filter(Boolean)
})
const initial = computed(() =>
  (userStore.info?.nickname || userStore.info?.username || 'A').slice(0, 1)
)
</script>

<style scoped>
.head { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
.name { margin: 0; }
</style>
