<template>
  <div class="login-wrap">
    <el-card class="login-card">
      <div class="lang-bar">
        <el-dropdown @command="onSwitchLang">
          <el-button size="small" text>
            {{ currentLangLabel }}<el-icon class="el-icon--right"><ArrowDown /></el-icon>
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="zh-CN">{{ t('ui.lang_zh') }}</el-dropdown-item>
              <el-dropdown-item command="en">{{ t('ui.lang_en') }}</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>

      <h2 class="title">{{ t('ui.login.title') }}</h2>
      <el-form ref="formRef" :model="form" :rules="rules" @submit.prevent="onSubmit">
        <el-form-item prop="username">
          <el-input v-model="form.username" :placeholder="t('ui.login.username')" prefix-icon="User" size="large" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" :placeholder="t('ui.login.password')" prefix-icon="Lock" size="large" show-password />
        </el-form-item>
        <el-form-item prop="captcha_code">
          <div class="captcha-row">
            <el-input v-model="form.captcha_code" :placeholder="t('ui.login.captcha')" prefix-icon="Key" size="large" />
            <div class="captcha-img" @click="refreshCaptcha" v-if="captcha.image">
              <img :src="captcha.image" :alt="t('ui.login.captcha')" />
            </div>
            <div class="captcha-ph" v-else @click="refreshCaptcha">
              <el-icon><Picture /></el-icon>
              <span>{{ t('ui.login.captcha_load') }}</span>
            </div>
          </div>
        </el-form-item>
        <el-button type="primary" size="large" :loading="loading" style="width: 100%" @click="onSubmit">
          {{ t('ui.login.submit') }}
        </el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { getCaptcha } from '@/api/captcha'
import { switchLocale } from '@/i18n'

const router = useRouter()
const userStore = useUserStore()
const { t, locale } = useI18n()

const formRef = ref(null)
const loading = ref(false)
const form = reactive({
  username: '', password: '',
  captcha_id: '', captcha_code: ''
})
const rules = {
  username: [{ required: true, trigger: 'blur' }],
  password: [{ required: true, trigger: 'blur' }]
}
const captcha = reactive({ captcha_id: '', image: '' })

const currentLangLabel = computed(() => locale.value === 'en' ? t('ui.lang_en') : t('ui.lang_zh'))

async function refreshCaptcha() {
  try {
    const data = await getCaptcha()
    captcha.captcha_id = data?.captcha_id || ''
    captcha.image = data?.image || ''
    form.captcha_code = ''
  } catch (e) {
    // 后端验证码接口未就绪时静默降级（占位图显示）
    captcha.image = ''
  }
}

async function onSubmit() {
  await formRef.value.validate()
  loading.value = true
  try {
    await userStore.login({
      username: form.username,
      password: form.password,
      captcha_id: captcha.captcha_id || undefined,
      captcha_code: form.captcha_code || undefined
    })
    ElMessage.success(t('ui.login.success'))
    router.replace('/')
  } catch (e) {
    refreshCaptcha()
  } finally {
    loading.value = false
  }
}

async function onSwitchLang(lang) {
  if (lang === locale.value) return
  await switchLocale(lang)
}

onMounted(refreshCaptcha)
</script>

<style scoped>
.login-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1f3c88 0%, #001529 100%);
  padding: 16px;
}
.login-card { width: 400px; padding: 12px; max-width: 100%; }
.lang-bar { text-align: right; margin-bottom: -8px; }
.title { text-align: center; margin: 8px 0 24px; }
.captcha-row { display: flex; gap: 8px; width: 100%; align-items: center; }
.captcha-row .el-input { flex: 1; }
.captcha-img {
  width: 120px; height: 40px; flex-shrink: 0; cursor: pointer;
  border: 1px solid #dcdfe6; border-radius: 4px; overflow: hidden; background: #f5f7fa;
}
.captcha-img img { width: 100%; height: 100%; object-fit: cover; }
.captcha-ph {
  width: 120px; height: 40px; flex-shrink: 0; cursor: pointer;
  border: 1px dashed #dcdfe6; border-radius: 4px;
  display: flex; align-items: center; justify-content: center; gap: 4px;
  color: #909399; font-size: 12px; background: #f5f7fa;
}

/* 手机窄屏适配 */
@media (max-width: 480px) {
  .login-card { padding: 8px; }
  .captcha-img, .captcha-ph { width: 100px; height: 36px; }
  .title { font-size: 20px; }
}
</style>
