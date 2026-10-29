<template>
  <el-card>
    <template #header><span>{{ t('ui.favorite.title') }}</span></template>

    <el-form :inline="true" :model="query" @submit.prevent="load">
      <el-form-item :label="t('ui.favorite.user_id')">
        <el-input v-model="query.user_id" clearable />
      </el-form-item>
      <el-form-item :label="t('ui.favorite.product_id')">
        <el-input v-model="query.product_id" clearable />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="load">{{ t('ui.search') }}</el-button>
      </el-form-item>
    </el-form>

    <el-table :data="list" v-loading="loading" border>
      <el-table-column prop="snow_id" :label="t('ui.snow_id')" width="160" />
      <el-table-column prop="user_id" :label="t('ui.favorite.user_id')" width="120" />
      <el-table-column prop="product_id" :label="t('ui.favorite.product_id')" width="120" />
      <el-table-column prop="product_name" :label="t('ui.favorite.product_name')" />
      <el-table-column prop="created_at" :label="t('ui.favorite.fav_time')" width="180" />
    </el-table>

    <el-pagination
      style="margin-top: 12px; justify-content: flex-end"
      v-model:current-page="query.page" v-model:page-size="query.page_size"
      :total="total" layout="total, prev, pager, next" @current-change="load"
    />
  </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { listFavorites } from '@/api/favorite'
const { t } = useI18n()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, page_size: 15, user_id: '', product_id: '' })
async function load() {
  loading.value = true
  try {
    const data = await listFavorites(query)
    list.value = data?.list || []; total.value = data?.total || 0
  } finally { loading.value = false }
}
onMounted(load)
</script>
