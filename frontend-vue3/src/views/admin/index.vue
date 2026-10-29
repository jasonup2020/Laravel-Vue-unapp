<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.admin.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.admin.create') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="username" :label="t('ui.admin.username')" />
      <el-table-column prop="nickname" :label="t('ui.admin.nickname')" />
      <el-table-column prop="email" :label="t('ui.admin.email')" />
      <el-table-column :label="t('ui.status')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? t('ui.enabled') : t('ui.disabled') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.admin.roles')">
        <template #default="{ row }">{{ (row.roles || []).map(r => r.name).join(', ') }}</template>
      </el-table-column>
      <el-table-column :label="t('ui.action')" width="420">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.admin.edit') }}</el-button>
          <el-button size="small" type="warning" @click="openAssignRole(row)">{{ t('ui.admin.assign_role') }}</el-button>
          <el-button size="small" @click="openReset(row)">{{ t('ui.admin.reset_pwd') }}</el-button>
          <el-button size="small" @click="onToggle(row)">
            {{ row.status === 1 ? t('ui.disabled') : t('ui.enabled') }}
          </el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.admin.delete') }}</el-button>
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

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.admin.create') : t('ui.admin.edit')" width="480px">
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('ui.admin.username')">
          <el-input v-model="form.username" :disabled="dialog.mode === 'edit'" />
        </el-form-item>
        <el-form-item :label="t('ui.admin.nickname')"><el-input v-model="form.nickname" /></el-form-item>
        <el-form-item :label="t('ui.admin.email')"><el-input v-model="form.email" /></el-form-item>
        <el-form-item :label="t('ui.admin.roles')">
          <el-select v-model="form.role_ids" multiple filterable style="width: 100%">
            <el-option v-for="r in roles" :key="r.snow_id" :label="r.name" :value="r.snow_id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="dialog.mode === 'create'" :label="t('ui.admin.password')">
          <el-input v-model="form.password" type="password" show-password />
        </el-form-item>
        <el-form-item v-if="dialog.mode === 'create'" :label="t('ui.admin.password_confirm')">
          <el-input v-model="form.password_confirmation" type="password" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onSave">{{ t('ui.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="reset.visible" :title="t('ui.admin.reset_pwd')" width="420px">
      <el-form label-width="80px">
        <el-form-item :label="t('ui.admin.new_password')">
          <el-input v-model="reset.password" type="password" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reset.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onResetSave">{{ t('ui.confirm') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="assign.visible" :title="t('ui.admin.assign_role')" width="420px">
      <el-select v-model="assign.role_ids" multiple filterable style="width: 100%">
        <el-option v-for="r in roles" :key="r.snow_id" :label="r.name" :value="r.snow_id" />
      </el-select>
      <template #footer>
        <el-button @click="assign.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onAssignSave">{{ t('ui.confirm') }}</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { listAdmins, createAdmin, updateAdmin, resetAdminPassword, updateAdminStatus, deleteAdmin } from '@/api/user'
import { listRoles } from '@/api/role'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const roles = ref([])

const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ snow_id: null, username: '', nickname: '', email: '', password: '', password_confirmation: '', role_ids: [] })
const reset = reactive({ visible: false, snow_id: null, password: '' })
const assign = reactive({ visible: false, snow_id: null, role_ids: [] })

async function loadRoles() {
  const data = await listRoles({ page: 1, page_size: 100 })
  roles.value = data?.list || []
}

async function load() {
  loading.value = true
  try {
    const data = await listAdmins(query)
    list.value = data?.list || []
    total.value = data?.total || 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { snow_id: null, username: '', nickname: '', email: '', password: '', password_confirmation: '', role_ids: [] })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  Object.assign(form, {
    snow_id: row.snow_id, username: row.username, nickname: row.nickname, email: row.email,
    role_ids: (row.roles || []).map(r => r.snow_id),
  })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  const payload = {
    username: form.username, nickname: form.nickname, email: form.email,
    role_ids: form.role_ids || [],
  }
  if (dialog.mode === 'create') {
    if (form.password) { payload.password = form.password; payload.password_confirmation = form.password_confirmation }
    await createAdmin(payload)
  } else {
    await updateAdmin(form.snow_id, payload)
  }
  ElMessage.success(t('ui.admin.saved'))
  dialog.visible = false; load()
}
function openReset(row) { reset.snow_id = row.snow_id; reset.password = ''; reset.visible = true }
function openAssignRole(row) {
  assign.snow_id = row.snow_id
  assign.role_ids = (row.roles || []).map(r => r.snow_id)
  assign.visible = true
}
async function onAssignSave() {
  await updateAdmin(assign.snow_id, { role_ids: assign.role_ids || [] })
  ElMessage.success(t('common.success'))
  assign.visible = false; load()
}
async function onResetSave() {
  await resetAdminPassword(reset.snow_id, { password: reset.password })
  ElMessage.success(t('ui.admin.reset_done')); reset.visible = false
}
async function onToggle(row) {
  await updateAdminStatus(row.snow_id, { status: row.status === 1 ? 0 : 1 })
  ElMessage.success(t('common.success')); load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.admin.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteAdmin(row.snow_id)
  ElMessage.success(t('common.success')); load()
}
onMounted(() => { load(); loadRoles() })
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
