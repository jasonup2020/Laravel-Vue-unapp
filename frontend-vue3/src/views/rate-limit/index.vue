<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.rate.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.rate.create') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="rule_key" :label="t('ui.rate.rule_key')" />
      <el-table-column prop="max_attempts" :label="t('ui.rate.max_attempts')" width="120" />
      <el-table-column prop="decay_seconds" :label="t('ui.rate.decay_seconds')" width="120" />
      <el-table-column :label="t('ui.rate.enabled')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.enabled ? 'success' : 'info'">
            {{ row.enabled ? t('ui.enabled') : t('ui.disabled') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="remark" :label="t('ui.rate.remark')" />
      <el-table-column :label="t('ui.action')" width="180">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.edit') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.rate.create') : t('ui.edit')" width="480px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="t('ui.rate.rule_key')"><el-input v-model="form.rule_key" :placeholder="t('ui.rate.rule_key_ph')" /></el-form-item>
        <el-form-item :label="t('ui.rate.max_attempts')">
          <el-input-number v-model="form.max_attempts" :min="1" :max="1000" />
        </el-form-item>
        <el-form-item :label="t('ui.rate.decay_seconds')">
          <el-input-number v-model="form.decay_seconds" :min="10" :max="3600" />
        </el-form-item>
        <el-form-item :label="t('ui.rate.enabled')">
          <el-switch v-model="form.enabled" />
        </el-form-item>
        <el-form-item :label="t('ui.rate.remark')"><el-input v-model="form.remark" /></el-form-item>
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
import { get, post, put, del } from '@/api/request'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ rule_key: '', max_attempts: 60, decay_seconds: 60, enabled: true, remark: '' })

// 后端路由：/api/admin/rate-limit-rules
// GET /admin/rate-limit-rules?page=&page_size= → { list, total }
// POST /admin/rate-limit-rules
// PUT  /admin/rate-limit-rules/{id}
// DELETE /admin/rate-limit-rules/{id}
async function load() {
  loading.value = true
  try {
    const data = await get('/admin/rate-limit-rules', query)
    list.value = data?.list || []
    total.value = data?.total || 0
  } catch (e) {
    list.value = []; total.value = 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { rule_key: '', max_attempts: 60, decay_seconds: 60, enabled: true, remark: '' })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  Object.assign(form, {
    id: row.id,
    rule_key: row.rule_key,
    max_attempts: row.max_attempts,
    decay_seconds: row.decay_seconds,
    enabled: !!row.enabled,
    remark: row.remark || ''
  })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  if (dialog.mode === 'create') await post('/admin/rate-limit-rules', { ...form })
  else await put(`/admin/rate-limit-rules/${form.id}`, { ...form })
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.rate.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await del(`/admin/rate-limit-rules/${row.id}`)
  ElMessage.success(t('common.success')); load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
