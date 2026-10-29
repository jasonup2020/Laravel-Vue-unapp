<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.product.title') }}</span>
        <el-button type="primary" @click="openCreate">{{ t('ui.product.create') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="name" :label="t('ui.product.name')" />
      <el-table-column prop="price" :label="t('ui.product.price')" width="120" />
      <el-table-column :label="t('ui.status')" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? t('ui.on') : t('ui.off') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="desc" :label="t('ui.product.desc')" show-overflow-tooltip />
      <el-table-column :label="t('ui.action')" width="200">
        <template #default="{ row }">
          <el-button size="small" @click="openEdit(row)">{{ t('ui.product.edit') }}</el-button>
          <el-button size="small" type="danger" @click="onDelete(row)">{{ t('ui.product.delete') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="dialog.mode === 'create' ? t('ui.product.create') : t('ui.product.edit')" width="520px">
      <el-form :model="form" label-width="80px">
        <el-form-item :label="t('ui.product.name')"><el-input v-model="form.name" /></el-form-item>
        <el-form-item :label="t('ui.product.price')">
          <el-input-number v-model="form.price" :precision="2" :min="0" />
        </el-form-item>
        <el-form-item :label="t('ui.status')">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item :label="t('ui.product.desc')"><el-input v-model="form.desc" type="textarea" /></el-form-item>
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
import { listProducts, createProduct, updateProduct, deleteProduct } from '@/api/product'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const dialog = reactive({ visible: false, mode: 'create' })
const form = reactive({ snow_id: null, name: '', price: 0, status: 1, desc: '' })

async function load() {
  loading.value = true
  try {
    const data = await listProducts(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
function openCreate() {
  Object.assign(form, { snow_id: null, name: '', price: 0, status: 1, desc: '' })
  dialog.mode = 'create'; dialog.visible = true
}
function openEdit(row) { Object.assign(form, row); dialog.mode = 'edit'; dialog.visible = true }
async function onSave() {
  if (dialog.mode === 'create') await createProduct({ ...form })
  else await updateProduct(form.snow_id, { ...form })
  ElMessage.success(t('common.success')); dialog.visible = false; load()
}
async function onDelete(row) {
  await ElMessageBox.confirm(t('ui.product.delete_confirm'), t('ui.confirm'), { type: 'warning' })
  await deleteProduct(row.snow_id); ElMessage.success(t('common.success')); load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
