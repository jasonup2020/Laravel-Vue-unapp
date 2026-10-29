<template>
  <div>
    <el-row :gutter="16">
      <el-col :span="6" v-for="card in cards" :key="card.label">
        <el-card shadow="hover">
          <div class="num">{{ card.value }}</div>
          <div class="label">{{ card.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card style="margin-top: 16px">
      <template #header><span>{{ t('ui.dashboard.health') }}</span></template>
      <el-alert
        title="GET /api/health"
        type="info"
        :closable="false"
        :description="t('ui.dashboard.health_tip')"
      />
    </el-card>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
// 卡片标签需随语言切换实时刷新，故用 computed（普通数组只在初始化求值，切换语言不更新）
const cards = computed(() => [
  { label: t('ui.admin.title'), value: '-' },
  { label: t('ui.user.title'), value: '-' },
  { label: t('ui.product.title'), value: '-' },
  { label: t('ui.message.title'), value: '-' }
])
</script>

<style scoped>
.num { font-size: 28px; font-weight: 600; color: #303133; }
.label { color: #909399; margin-top: 4px; }
</style>
