<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.permission.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.permission.create') }}</el-button>
      </div>
    </template>
    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column :label="t('ui.permission.name')">
        <template #default="{ row }">{{ bnLabel(row.label, row.name) }}</template>
      </el-table-column>
      <el-table-column prop="code" :label="t('ui.permission.code')" />
      <el-table-column prop="group" :label="t('ui.permission.group')" />
      <el-table-column prop="action" :label="t('ui.permission.action')" />
      <el-table-column :label="t('ui.action')" width="180">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.permission.edit') }}</el-button>
          <el-button size="small" type="danger" :disabled="Number(row.type) === 1"
            :title="Number(row.type) === 1 ? t('ui.permission.system_not_deletable') : ''"
            @click="onDelete(row)">{{ t('ui.permission.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.permission.create') : t('ui.permission.edit')" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item :label="t('ui.permission.name')"><el-input v-model="form.name" /></el-form-item>
        <el-form-item :label="t('ui.permission.label_en')"><el-input v-model="form.label_en" /></el-form-item>
        <el-form-item :label="t('ui.permission.code')">
          <el-input v-model="form.code" :disabled="dialog.mode === 'edit' && form.type === 1"
            :placeholder="t('ui.permission.code_ph')" />
        </el-form-item>
        <el-form-item :label="t('ui.permission.group')">
          <el-input v-model="form.group" :disabled="dialog.mode === 'edit' && form.type === 1"
            :placeholder="t('ui.permission.group')" />
          <div v-if="groupOptions.length" class="sug">
            <el-tag v-for="g in groupOptions" :key="g" size="small" class="sug-tag" @click="form.group = g">{{ g }}</el-tag>
          </div>
        </el-form-item>
        <el-form-item :label="t('ui.permission.action')">
          <el-input v-model="form.action" :disabled="dialog.mode === 'edit' && form.type === 1"
            :placeholder="t('ui.permission.action')" />
          <div v-if="actionOptions.length" class="sug">
            <el-tag v-for="a in actionOptions" :key="a" size="small" class="sug-tag" @click="form.action = a">{{ a }}</el-tag>
          </div>
        </el-form-item>
        <el-form-item :label="t('ui.permission.remark')"><el-input v-model="form.remark" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onSave">{{ t('ui.save') }}</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { listPermissions, createPermission, updatePermission, deletePermission } from '@/api/role'
const { t, locale } = useI18n()
const loading = ref(false)
const list = ref([])
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ snow_id: null, name: '', label_en: '', code: '', group: '', action: '', type: 2, remark: '' })

// 记录最近一次由 group/action 自动生成的 code，避免覆盖用户手动输入
const autoCode = ref('')

// 分组预设：优先取 ui.perm_group 的 key；语言包未就绪时回退到内置常用模块。可点击填入，也可手动输入自定义
const groupOptions = computed(() => {
  const g = t('ui.perm_group')
  const keys = (g && typeof g === 'object') ? Object.keys(g) : []
  return keys.length ? keys : ['admin', 'dict', 'product', 'user', 'role', 'menu', 'permission', 'message', 'log', 'ip', 'rate', 'favorite', 'recycle', 'system']
})
// 动作预设：常见 CRUD + 扩展动作，点击填入，也可手动输入自定义
const actionOptions = ref(['list', 'create', 'update', 'delete', 'export', 'audit', 'kick', 'restore', 'read', 'write'])

// 当 group/action 变化且 code 未自定义时，自动补齐 admin:{group}:{action}
function fillCode() {
  const g = form.group, a = form.action
  if (!g) return
  const next = a ? `admin:${g}:${a}` : `admin:${g}:`
  if (!form.code || form.code === autoCode.value) {
    form.code = next
    autoCode.value = next
  }
}
watch(() => form.group, fillCode)
watch(() => form.action, fillCode)

// label 双语对象优先；无 label 回退 name（字符串）
function bnLabel(label, name) {
  if (label && typeof label === 'object' && !Array.isArray(label)) {
    return label[locale.value] || label['zh-CN'] || name || ''
  }
  return label || name || ''
}

async function load() {
  loading.value = true
  try {
    const res = await listPermissions()
    list.value = (res && Array.isArray(res.list)) ? res.list : (Array.isArray(res) ? res : [])
  } finally { loading.value = false }
}

function openCreate() {
  Object.assign(form, { snow_id: null, name: '', label_en: '', code: '', group: '', action: '', type: 2, remark: '' })
  autoCode.value = ''
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  Object.assign(form, {
    snow_id: row.snow_id,
    name: row.name,
    label_en: (row.label && typeof row.label === 'object') ? (row.label.en || row.name) : (row.label_en || row.name),
    code: row.code,
    group: row.group,
    action: row.action,
    type: row.type ?? 2,
    remark: row.remark || ''
  })
  autoCode.value = row.code || ''
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  if (dialog.mode === 'create') await createPermission({ ...form })
  else await updatePermission(form.snow_id, {
    name: form.name, label_en: form.label_en,
    code: form.code, group: form.group, action: form.action,
    type: form.type, remark: form.remark
  })
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  if (Number(row.type) === 1) {
    ElMessage.warning(t('ui.permission.system_not_deletable'))
    return
  }
  await ElMessageBox.confirm(t('ui.permission.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deletePermission(row.snow_id); ElMessage.success(t('common.success')); load()
}

onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
.sug { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px; }
.sug-tag { cursor: pointer; }
</style>
