<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.menu.title') }}</span>
        <el-button type="primary" @click="openCreate(null)">{{ t('ui.menu.create_root') }}</el-button>
      </div>
    </template>

    <el-table :data="treeData" row-key="snow_id" border default-expand-all :tree-props="{ children: 'children' }">
      <el-table-column :label="t('ui.menu.title_col')">
        <template #default="{ row }">{{ titleOf(row) }}</template>
      </el-table-column>
      <el-table-column prop="path" :label="t('ui.menu.path')" />
      <el-table-column prop="icon" :label="t('ui.menu.icon')" width="120" />
      <el-table-column prop="sort" :label="t('ui.menu.sort')" width="80" />
      <el-table-column :label="t('ui.menu.visible')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_show ? 'success' : 'info'">{{ row.is_show ? t('ui.show') : t('ui.hide') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.menu.external')" width="80">
        <template #default="{ row }">
          <el-tag v-if="row.is_external" type="warning">{{ t('ui.menu.external') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.action')" width="280">
        <template #default="{ row }">
          <el-button size="small" @click="openCreate(row)">{{ t('ui.menu.create_child') }}</el-button>
          <el-button size="small" @click="openEdit(row)">{{ t('ui.menu.edit') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.menu.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.menu.create_root') : t('ui.menu.edit')" width="560px">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="t('ui.menu.parent')">
          <el-tree-select
            v-model="form.parent_id"
            :data="parentOptions"
            :props="{ label: 'label', children: 'children' }"
            node-key="value"
            check-strictly
            :render-after-expand="false"
            placeholder="Root"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item :label="t('ui.menu.title_col')"><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="t('ui.menu.path')"><el-input v-model="form.path" /></el-form-item>
        <el-form-item :label="t('ui.menu.component')"><el-input v-model="form.component" /></el-form-item>
        <el-form-item :label="t('ui.menu.icon')"><el-input v-model="form.icon" /></el-form-item>
        <el-form-item :label="t('ui.menu.perm_code')"><el-input v-model="form.permission_code" /></el-form-item>
        <el-form-item :label="t('ui.menu.sort')"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item :label="t('ui.menu.is_external')">
          <el-switch v-model="form.is_external" />
        </el-form-item>
        <el-form-item v-if="form.is_external" :label="t('ui.menu.external_url')">
          <el-input v-model="form.external_url" />
        </el-form-item>
        <el-form-item :label="t('ui.menu.is_show')">
          <el-switch v-model="form.is_show" />
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAllTree, createMenu, updateMenu, deleteMenu } from '@/api/menu'

const { t, locale } = useI18n()
const treeData = ref([])
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({
  snow_id: null, parent_id: 0, parent_title: '', title: '', path: '', component: '',
  icon: '', permission_code: '', sort: 0, is_show: true, is_external: false, external_url: ''
})

// title 兼容对象 { 'zh-CN': '...', en: '...' }
function titleOf(row) {
  const raw = row.title
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw[locale.value] || raw['zh-CN'] || ''
  }
  return raw
}

// 父级下拉选项：Root(0) + 当前树（编辑时禁用自身及其后代，防自引用/环）
const parentOptions = computed(() => {
  const build = (nodes, depth) => {
    return (nodes || [])
      .filter((n) => n.snow_id !== form.snow_id)
      .map((n) => ({
        value: n.snow_id,
        label: (depth ? '　'.repeat(depth) : '') + titleOf(n),
        disabled: false,
        children: build(n.children, depth + 1)
      }))
  }
  return [{ value: 0, label: 'Root' }, ...build(treeData.value, 0)]
})

async function load() {
  const res = await getAllTree()
  // 后端可能返回 { tree: [...] } 或直接 [...]
  treeData.value = (res && Array.isArray(res.tree)) ? res.tree : (Array.isArray(res) ? res : [])
}
function openCreate(parent) {
  Object.assign(form, {
    snow_id: null,
    parent_id: parent ? parent.snow_id : 0,
    parent_title: parent ? titleOf(parent) : 'Root',
    title: '', path: '', component: '', icon: '', permission_code: '',
    sort: 0, is_show: true, is_external: false, external_url: ''
  })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  // The tree API returns title as a bilingual object {zh-CN, en}; the form and the
  // backend expect a plain string, so map it back before editing.
  // 树接口返回的 title 是双语对象 {zh-CN, en}，表单与后端契约是纯字符串，编辑前需还原
  const titleStr = typeof row.title === 'string' ? row.title : (row.title && row.title['zh-CN']) || ''
  Object.assign(form, row, {
    parent_title: row.parent_id ? 'Child' : 'Root',
    title: titleStr,
  })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  const payload = { ...form }
  delete payload.snow_id; delete payload.parent_title
  if (dialog.mode === 'create') await createMenu(payload)
  else await updateMenu(form.snow_id, payload)
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.menu.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteMenu(row.snow_id); ElMessage.success(t('common.success')); load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
