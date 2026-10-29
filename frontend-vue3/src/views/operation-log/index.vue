<template>
  <el-card>
    <template #header><span>{{ t('ui.log.title') }}</span></template>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="id" :label="t('ui.id')" width="80" />
      <el-table-column prop="admin_id" :label="t('ui.log.admin_id')" width="100" />
      <el-table-column prop="url" :label="t('ui.log.url')" show-overflow-tooltip />
      <el-table-column prop="method" :label="t('ui.log.method')" width="90" />
      <el-table-column prop="ip" :label="t('ui.log.ip')" width="130" />
      <el-table-column prop="cost_ms" :label="t('ui.log.cost_ms')" width="100" />
      <el-table-column :label="t('ui.log.high_risk')" width="80">
        <template #default="{ row }">
          <el-tag v-if="row.is_high_risk" type="danger">{{ t('ui.log.high_risk') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('ui.created_at')" width="180" />
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { listOperationLogs } from '@/api/log'
const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
async function load() {
  loading.value = true
  try {
    const data = await listOperationLogs(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
onMounted(load)
</script>
