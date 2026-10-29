<template>
  <el-card>
    <template #header><span>{{ t('ui.recycle.title') }}</span></template>

    <el-form :inline="true">
      <el-form-item :label="t('ui.recycle.resource')">
        <el-select v-model="resource" @change="load" style="width: 200px">
          <el-option v-for="r in resources" :key="r.value" :label="t(r.label)" :value="r.value" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" :disabled="!selection.length" @click="onBatchRestore">
          {{ t('ui.recycle.batch_restore') }}({{ selection.length }})
        </el-button>
      </el-form-item>
    </el-form>

    <el-table :data="list" v-loading="loading" border @selection-change="(rows) => (selection = rows)">
      <el-table-column type="selection" width="50" />
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="name" :label="t('ui.product.name')" />
      <el-table-column prop="deleted_at" :label="t('ui.recycle.deleted_at')" width="180" />
      <el-table-column :label="t('ui.action')" width="120">
        <template #default="{ row }">
          <el-button size="small" type="primary" @click="onRestore(row)">{{ t('ui.recycle.restore') }}</el-button>
        </template>
      </el-table-column>
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
import { ElMessage } from 'element-plus'
import { listRecycle, restoreRecycle, restoreBatchRecycle } from '@/api/recycle'

const { t } = useI18n()
const resources = [
  { label: 'ui.recycle.products', value: 'products' },
  { label: 'ui.recycle.users', value: 'users' },
  { label: 'ui.recycle.admins', value: 'admins' },
  { label: 'ui.recycle.menus', value: 'admin_menus' },
  { label: 'ui.recycle.roles', value: 'roles' },
  { label: 'ui.recycle.messages', value: 'messages' },
  { label: 'ui.recycle.favorites', value: 'user_favorites' }
]

const resource = ref('products')
const loading = ref(false)
const list = ref([])
const total = ref(0)
const selection = ref([])
const query = reactive({ page: 1, page_size: 15 })

async function load() {
  loading.value = true
  try {
    const data = await listRecycle(resource.value, query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
async function onRestore(row) {
  await restoreRecycle(resource.value, row.snow_id)
  ElMessage.success(t('ui.recycle.restored')); load()
}
async function onBatchRestore() {
  const ids = selection.value.map((r) => r.snow_id)
  await restoreBatchRecycle(resource.value, { ids })
  ElMessage.success(t('ui.recycle.batch_restored')); load()
}
onMounted(load)
</script>
