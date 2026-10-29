<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.dict.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.dict.create') }}</el-button>
      </div>
    </template>

    <el-form :inline="true" :model="query" @submit.prevent="load">
      <el-form-item :label="t('ui.dict.type')">
        <el-input v-model="query.type" clearable />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="load">{{ t('ui.search') }}</el-button>
      </el-form-item>
    </el-form>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="type" :label="t('ui.dict.type')" />
      <el-table-column prop="label" :label="t('ui.dict.label')">
        <template #default="{ row }">{{ bnLabel(row.label) }}</template>
      </el-table-column>
      <el-table-column prop="value" :label="t('ui.dict.value')" />
      <el-table-column prop="sort" :label="t('ui.sort')" width="80" />
      <el-table-column :label="t('ui.status')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? t('ui.enabled') : t('ui.disabled') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.action')" width="180">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.dict.edit') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.dict.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.dict.create') : t('ui.dict.edit')" width="480px">
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('ui.dict.type')"><el-input v-model="form.type" /></el-form-item>
        <el-form-item :label="t('ui.dict.label')"><el-input v-model="form.label" /></el-form-item>
        <el-form-item :label="t('ui.dict.label_en')"><el-input v-model="form.label_en" /></el-form-item>
        <el-form-item :label="t('ui.dict.value')"><el-input v-model="form.value" /></el-form-item>
        <el-form-item :label="t('ui.sort')"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item :label="t('ui.status')">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
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
import { listDicts, createDict, updateDict, deleteDict } from '@/api/dict'

const { t, locale } = useI18n()

// 双语 label：对象 { 'zh-CN': '..', en: '..' } 按 locale 取值；字符串原样
function bnLabel(raw) {
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw[locale.value] || raw['zh-CN'] || ''
  }
  return raw
}
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15, type: '' })
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ snow_id: null, type: '', label: '', label_en: '', value: '', sort: 0, status: 1 })

async function load() {
  loading.value = true
  try {
    const data = await listDicts(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { snow_id: null, type: '', label: '', label_en: '', value: '', sort: 0, status: 1 })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  const rawLabel = row.label && typeof row.label === 'object' ? row.label : null
  Object.assign(form, {
    snow_id: row.snow_id, type: row.type, value: row.value, sort: row.sort, status: row.status,
    label: rawLabel ? (rawLabel['zh-CN'] || '') : (row.label || ''),
    label_en: rawLabel ? (rawLabel.en || '') : (row.label_en || ''),
  })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  if (dialog.mode === 'create') await createDict({ ...form })
  else await updateDict(form.snow_id, { ...form })
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.dict.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteDict(row.snow_id); ElMessage.success(t('common.success')); load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
