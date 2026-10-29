<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.role.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.role.create') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="name" :label="t('ui.role.name')" />
      <el-table-column prop="code" :label="t('ui.role.code')" />
      <el-table-column prop="remark" :label="t('ui.role.remark')" />
      <el-table-column :label="t('ui.action')" width="360">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.role.edit') }}</el-button>
          <el-button size="small" @click="openPerm(row)">{{ t('ui.role.assign_perm') }}</el-button>
          <el-button size="small" @click="openMenu(row)">{{ t('ui.role.assign_menu') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.role.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.role.create') : t('ui.role.edit')" width="480px">
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('ui.role.name')"><el-input v-model="form.name" /></el-form-item>
        <el-form-item :label="t('ui.role.code')">
          <el-input v-model="form.code" :disabled="dialog.mode === 'edit'" />
        </el-form-item>
        <el-form-item :label="t('ui.role.remark')"><el-input v-model="form.remark" type="textarea" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onSave">{{ t('ui.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="perm.visible" :title="t('ui.role.assign_perm')" width="580px">
      <div v-if="perm.visible" class="perm-wrap">
        <div class="perm-section">
          <div class="perm-sec-title">{{ t('ui.role.perm_common') }}</div>
          <el-checkbox-group v-model="perm.selectedCodes">
            <el-checkbox v-for="p in perm.common" :key="p.code" :value="p.code">{{ p.label }}</el-checkbox>
          </el-checkbox-group>
        </div>
        <div class="perm-other">
          <div class="perm-other-title">{{ t('ui.role.perm_other') }}</div>
          <div class="perm-section" v-for="g in perm.groups" :key="g.group">
            <div class="perm-sec-title">{{ g.groupLabel }}</div>
            <el-checkbox-group v-model="perm.selectedCodes">
              <el-checkbox v-for="p in g.items" :key="p.code" :value="p.code">{{ p.label }}</el-checkbox>
            </el-checkbox-group>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="perm.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onPermSave">{{ t('ui.confirm') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="menu.visible" :title="t('ui.role.assign_menu')" width="520px">
      <el-tree v-if="menu.visible" ref="menuTreeRef" :data="menuTreeData" show-checkbox node-key="snow_id"
        :props="{ label: 'name', children: 'children' }" :default-checked-keys="menu.checked" />
      <template #footer>
        <el-button @click="menu.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onMenuSave">{{ t('ui.confirm') }}</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { listRoles, createRole, updateRole, deleteRole, assignRolePermissions, assignRoleMenus, listPermissions } from '@/api/role'
import { getAllTree } from '@/api/menu'

const { t, locale, te } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ snow_id: null, name: '', code: '', remark: '' })
// 分配权限状态：selectedCodes=已勾选权限码；common=公共权限项；groups=其它按模块分组的权限
const perm = reactive({ visible: false, snow_id: null, selectedCodes: [], byCode: {}, common: [], groups: [] })
const menu = reactive({ visible: false, snow_id: null, checked: [] })
const menuTreeData = ref([])
const menuTreeRef = ref(null)

// title 兼容双语对象 { 'zh-CN': '...', en: '...' } / 字符串
function titleOf(row) {
  const raw = row?.title
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw[locale.value] || raw['zh-CN'] || ''
  }
  return raw ?? ''
}
// 为 el-tree 生成 locale 化 name 标签（保留 snow_id/children 供回显与提交）
function mapMenuTree(nodes) {
  return (nodes || []).map((n) => ({
    ...n,
    name: titleOf(n),
    children: n.children ? mapMenuTree(n.children) : []
  }))
}

async function load() {
  loading.value = true
  try {
    const data = await listRoles(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { snow_id: null, name: '', code: '', remark: '' })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  Object.assign(form, { snow_id: row.snow_id, name: row.name, code: row.code, remark: row.remark || '' })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  if (dialog.mode === 'create') await createRole({ ...form })
  else await updateRole(form.snow_id, { name: form.name, remark: form.remark })
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.role.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteRole(row.snow_id); ElMessage.success(t('common.success')); load()
}
async function openPerm(row) {
  perm.snow_id = row.snow_id
  const res = await listPermissions()
  const perms = (res && Array.isArray(res.list)) ? res.list : (Array.isArray(res) ? res : [])
  const sections = buildPermSections(perms)
  perm.common = sections.common
  perm.groups = sections.groups
  perm.byCode = {}
  perms.forEach((p) => { if (p.code) perm.byCode[p.code] = p.snow_id })
  perm.selectedCodes = (row.permissions || []).map((p) => p.code).filter(Boolean)
  perm.visible = true
}
// 把权限拆成两部分：公共权限（group=common）+ 其它权限（按模块分组）。label 双语按 locale 取值
function buildPermSections(list) {
  const leafLabel = (p) => (p.label && typeof p.label === 'object' && !Array.isArray(p.label))
    ? (p.label[locale.value] || p.label['zh-CN'] || p.name || p.code)
    : (p.name || p.code)
  const common = []
  const groupsMap = {}
  list.forEach((p) => {
    const item = { ...p, label: leafLabel(p) }
    const g = p.group || 'Other'
    if (g === 'common' || g === 'Common') { common.push(item); return }
    if (!groupsMap[g]) {
      // 分组标题：优先 i18n key ui.perm_group.{g}，缺失回退原名
      const gKey = 'ui.perm_group.' + g
      groupsMap[g] = { group: g, groupLabel: te(gKey) ? t(gKey) : g, items: [] }
    }
    groupsMap[g].items.push(item)
  })
  return { common, groups: Object.values(groupsMap).sort((a, b) => a.group.localeCompare(b.group)) }
}

// locale 切换时若分配权限弹窗打开，重建两部分即时刷新英文
watch(locale, () => {
  if (perm.visible && perm.snow_id) {
    listPermissions().then((res) => {
      const perms = (res && Array.isArray(res.list)) ? res.list : (Array.isArray(res) ? res : [])
      const s = buildPermSections(perms)
      perm.common = s.common
      perm.groups = s.groups
    })
  }
})
async function onPermSave() {
  // 按勾选的权限码映射为 permission snow_id 提交
  const ids = (perm.selectedCodes || []).map((c) => perm.byCode[c]).filter((v) => v != null)
  await assignRolePermissions(perm.snow_id, { permission_ids: ids })
  ElMessage.success(t('ui.role.assigned')); perm.visible = false; load()
}
async function openMenu(row) {
  menu.snow_id = row.snow_id
  const res = await getAllTree()
  const tree = (res && Array.isArray(res.tree)) ? res.tree : (Array.isArray(res) ? res : [])
  menuTreeData.value = mapMenuTree(tree)
  menu.checked = (row.menus || []).map((m) => m.snow_id).filter(Boolean)
  menu.visible = true
  // default-checked-keys 初始化不可靠（曾出现缺勾选），显式按后端数据设置勾选
  await nextTick()
  menuTreeRef.value?.setCheckedKeys(menu.checked)
}
async function onMenuSave() {
  // 树按 snow_id 勾选，后端契约接收 menu snow_id 数组
  const ids = menuTreeRef.value.getCheckedKeys() || []
  await assignRoleMenus(menu.snow_id, { menu_ids: ids })
  ElMessage.success(t('ui.role.menu_assigned')); menu.visible = false; load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
.perm-wrap { max-height: 60vh; overflow-y: auto; }
.perm-section { margin-bottom: 12px; }
.perm-sec-title { font-weight: 600; color: #303133; margin-bottom: 6px; }
.perm-other { border-top: 1px solid #ebeef5; padding-top: 10px; }
.perm-other-title { font-weight: 600; color: #909399; margin-bottom: 8px; }
</style>
