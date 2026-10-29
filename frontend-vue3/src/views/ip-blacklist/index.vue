<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.ip.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.ip.create') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="ip" :label="t('ui.ip.ip')" />
      <el-table-column prop="remark" :label="t('ui.ip.remark')" />
      <el-table-column :label="t('ui.ip.expire_at')" width="180">
        <template #default="{ row }">{{ row.expire_at || t('ui.ip.permanent') }}</template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('ui.created_at')" width="180" />
      <el-table-column :label="t('ui.action')" width="120">
        <template #default="{ row }">
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.ip.unblock') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="t('ui.ip.create')" width="480px">
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('ui.ip.ip')"><el-input v-model="form.ip" /></el-form-item>
        <el-form-item :label="t('ui.ip.remark')"><el-input v-model="form.remark" /></el-form-item>
        <el-form-item :label="t('ui.ip.expire_at')">
          <el-date-picker v-model="form.expire_at" type="datetime" :placeholder="t('ui.ip.expire_placeholder')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onSave">{{ t('ui.save') }}</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { listIpBlacklist, createIpBlacklist, deleteIpBlacklist } from '@/api/ip'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const dialog = reactive({ visible: false })
const form = reactive({ ip: '', remark: '', expire_at: null })

async function load() {
  loading.value = true
  try {
    const data = await listIpBlacklist(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { ip: '', remark: '', expire_at: null })
  dialog.visible = true
}
async function onSave() {
  await createIpBlacklist({ ...form })
  ElMessage.success(t('ui.ip.added')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.ip.unblock_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteIpBlacklist(row.snow_id); ElMessage.success(t('ui.ip.removed')); load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
