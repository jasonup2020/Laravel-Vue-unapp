<template>
  <el-card>
    <template #header><span>{{ t('ui.user.title') }}</span></template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="name" :label="t('ui.user.name')" />
      <el-table-column prop="email" :label="t('ui.user.email')" />
      <el-table-column :label="t('ui.status')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? t('ui.enabled') : t('ui.disabled') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.action')" width="280">
        <template #default="{ row }">
          <el-button size="small" @click="onToggle(row)">
            {{ row.status === 1 ? t('ui.disabled') : t('ui.enabled') }}
          </el-button>
          <el-button size="small" @click="onOnline(row)">{{ t('ui.user.online') }}</el-button>
          <el-button size="small" type="danger" @click="onKick(row)">{{ t('ui.user.kick') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page"
      v-model:page-size="query.page_size"
      :total="total"
      layout="total, prev, pager, next"
      @current-change="load"
    />
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { listUsers, updateUserStatus, getUserOnline, kickUser } from '@/api/user'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })

async function load() {
  loading.value = true
  try {
    const data = await listUsers(query)
    list.value = data?.list || []
    total.value = data?.total || 0
  } finally { loading.value = false }
}

async function onToggle(row) {
  await updateUserStatus(row.snow_id, { status: row.status === 1 ? 0 : 1 })
  ElMessage.success(t('common.success'))
  load()
}
async function onOnline(row) {
  const data = await getUserOnline(row.snow_id)
  ElMessageBox.alert(`${t('ui.user.online_count')}：${data?.count ?? data ?? 0}`, t('ui.user.online'))
}
async function onKick(row) {
  await ElMessageBox.confirm(t('ui.user.kick_confirm'), t('ui.confirm'), { type: 'warning' })
  await kickUser(row.snow_id)
  ElMessage.success(t('device.kicked_all'))
}
onMounted(load)
</script>
