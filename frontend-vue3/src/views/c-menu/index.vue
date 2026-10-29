<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.cmenu.title') }}</span>
        <el-button type="primary" @click="openCreate(null)">{{ t('ui.cmenu.create') }}</el-button>
      </div>
    </template>

    <el-table :data="treeData" row-key="snow_id" border default-expand-all :tree-props="{ children: 'children' }">
      <el-table-column :label="t('ui.cmenu.title')">
        <template #default="{ row }">{{ titleOf(row) }}</template>
      </el-table-column>
      <el-table-column prop="icon" :label="t('ui.menu.icon')" width="120" />
      <el-table-column prop="path" :label="t('ui.cmenu.path')" />
      <el-table-column prop="url" :label="t('ui.menu.external_url')" show-overflow-tooltip />
      <el-table-column prop="sort" :label="t('ui.menu.sort')" width="80" />
      <el-table-column :label="t('ui.cmenu.is_show')" width="90">
        <template #default="{ row }">
          <el-switch v-model="row.is_show" @change="onToggleShow(row)" />
        </template>
      </el-table-column>
      <el-table-column :label="t('ui.action')" width="280">
        <template #default="{ row }">
          <el-button size="small" @click="openCreate(row)">{{ t('ui.cmenu.create_child') }}</el-button>
          <el-button size="small" @click="openEdit(row)">{{ t('ui.edit') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.cmenu.create') : t('ui.edit')" width="560px">
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
        <el-form-item :label="t('ui.cmenu.title')"><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="t('ui.cmenu.title_en')"><el-input v-model="form.title_en" /></el-form-item>
        <el-form-item :label="t('ui.menu.icon')"><el-input v-model="form.icon" placeholder="home / star / user..." /></el-form-item>
        <el-form-item :label="t('ui.cmenu.path')"><el-input v-model="form.path" placeholder="/pages/index/index" /></el-form-item>
        <el-form-item :label="t('ui.menu.external_url')"><el-input v-model="form.url" /></el-form-item>
        <el-form-item :label="t('ui.menu.sort')"><el-input-number v-model="form.sort" :min="0" /></el-form-item>
        <el-form-item :label="t('ui.cmenu.is_show')">
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
import { listCMenus, createCMenu, updateCMenu, deleteCMenu } from '@/api/cmenu'

const { t, locale } = useI18n()
const treeData = ref([])
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({
  snow_id: null, parent_id: 0, parent_title: '',
  title: '', title_en: '', icon: '', path: '', url: '',
  sort: 0, is_show: true
})

function titleOf(row) {
  const raw = row.title
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    return raw[locale.value] || raw['zh-CN'] || ''
  }
  if (locale.value === 'en' && row.title_en) return row.title_en
  return raw || ''
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
  const data = await listCMenus({ page: 1, page_size: 200 })
  // 后端可能返回 { list, total } 或直接数组；树形接口若返回 tree 也兼容
  treeData.value = data?.tree ? data.tree : (data?.list || (Array.isArray(data) ? data : []))
}
function openCreate(parent) {
  Object.assign(form, {
    snow_id: null,
    parent_id: parent ? parent.snow_id : 0,
    parent_title: parent ? titleOf(parent) : 'Root',
    title: '', title_en: '', icon: '', path: '', url: '',
    sort: 0, is_show: true
  })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) {
  Object.assign(form, row, { parent_title: row.parent_id ? 'Child' : 'Root' })
  dialog.mode = 'edit'; dialog.visible = true
}
async function onSave() {
  const payload = { ...form }; delete payload.snow_id; delete payload.parent_title
  if (dialog.mode === 'create') await createCMenu(payload)
  else await updateCMenu(form.snow_id, payload)
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.cmenu.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteCMenu(row.snow_id); ElMessage.success(t('common.success')); load()
}
async function onToggleShow(row) {
  await updateCMenu(row.snow_id, { is_show: row.is_show })
  ElMessage.success(t('common.success'))
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
