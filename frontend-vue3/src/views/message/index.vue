<template>
  <el-card>
    <template #header>
      <div class="bar">
        <span>{{ t('ui.message.title') }}</span>
        <el-button type="primary" @click="openSend">{{ t('ui.message.send') }}</el-button>
      </div>
    </template>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="receiver_type" :label="t('ui.message.receiver_type')" width="120" />
      <el-table-column prop="receiver_id" :label="t('ui.message.receiver_id')" width="100" />
      <el-table-column prop="msg_type" :label="t('ui.message.msg_type')" width="100" />
      <el-table-column prop="title" :label="t('ui.message.title_col')" />
      <el-table-column prop="content" :label="t('ui.message.content')" show-overflow-tooltip />
      <el-table-column prop="created_at" :label="t('ui.created_at')" width="180" />
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />

    <el-dialog v-model="dialog.visible" :title="t('ui.message.send')" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item :label="t('ui.message.receiver_type')">
          <el-select v-model="form.receiver_type">
            <el-option :label="t('ui.message.r_user')" value="user" />
            <el-option :label="t('ui.message.r_admin')" value="admin" />
            <el-option :label="t('ui.message.r_all_user')" value="all_user" />
            <el-option :label="t('ui.message.r_all_admin')" value="all_admin" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.receiver_type === 'user' || form.receiver_type === 'admin'" :label="t('ui.message.receiver_id')">
          <el-input v-model="form.receiver_id" />
        </el-form-item>
        <el-form-item :label="t('ui.message.msg_type')">
          <el-select v-model="form.msg_type">
            <el-option :label="t('ui.message.t_announce')" value="announce" />
            <el-option :label="t('ui.message.t_biz')" value="biz" />
            <el-option :label="t('ui.message.t_warn')" value="warn" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ui.message.title_col')"><el-input v-model="form.title" /></el-form-item>
        <el-form-item :label="t('ui.message.content')"><el-input v-model="form.content" type="textarea" /></el-form-item>
        <el-form-item :label="t('ui.message.is_pop')">
          <el-switch v-model="form.is_pop" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('ui.cancel') }}</el-button>
        <el-button type="primary" @click="onSend">{{ t('ui.confirm') }}</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { listMessages, sendMessage } from '@/api/message'

const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15 })
const dialog = reactive({ visible: false })
const form = reactive({
  receiver_type: 'all_admin', receiver_id: null, msg_type: 'announce',
  title: '', content: '', is_pop: 0
})

async function load() {
  loading.value = true
  try {
    const data = await listMessages(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
function openSend() {
  Object.assign(form, {
    receiver_type: 'all_admin', receiver_id: null, msg_type: 'announce',
    title: '', content: '', is_pop: 0
  })
  dialog.visible = true
}
async function onSend() {
  await sendMessage({ ...form })
  ElMessage.success(t('ui.message.sent')); dialog.visible = false; load()
}
onMounted(load)
</script>

<style scoped>
.bar { display: flex; justify-content: space-between; align-items: center; }
</style>
