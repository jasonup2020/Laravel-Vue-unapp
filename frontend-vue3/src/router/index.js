import { createRouter, createWebHashHistory } from 'vue-router'
import { getToken } from '@/api/request'

/**
 * 路由策略：
 * - 登录页 /login
 * - 主布局 / 下挂业务页面（静态兜底，覆盖契约 §6.4 菜单树全部 path）
 * - 登录成功后从 /api/admin/menu/my-tree 拉 menuTree 存 Pinia，侧边栏按树渲染
 * - 真正"按后端 component 字符串动态 addRoute"的扩展点见文件末尾注释
 */
const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', public: true }
  },
  {
    path: '/',
    component: () => import('@/layout/index.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '工作台', icon: 'Odometer' }
      },
      {
        path: 'user',
        name: 'User',
        component: () => import('@/views/user/index.vue'),
        meta: { title: '用户管理', icon: 'User' }
      },
      {
        path: 'product',
        name: 'Product',
        component: () => import('@/views/product/index.vue'),
        meta: { title: '商品管理', icon: 'Goods' }
      },
      {
        path: 'dict',
        name: 'Dict',
        component: () => import('@/views/dict/index.vue'),
        meta: { title: '字典管理', icon: 'Collection' }
      },
      {
        path: 'admin',
        name: 'Admin',
        component: () => import('@/views/admin/index.vue'),
        meta: { title: '管理员管理', icon: 'Avatar' }
      },
      {
        path: 'role',
        name: 'Role',
        component: () => import('@/views/role/index.vue'),
        meta: { title: '角色管理', icon: 'Lock' }
      },
      {
        path: 'menu',
        name: 'Menu',
        component: () => import('@/views/menu/index.vue'),
        meta: { title: '菜单管理', icon: 'Menu' }
      },
      {
        path: 'permission',
        name: 'Permission',
        component: () => import('@/views/permission/index.vue'),
        meta: { title: '权限列表', icon: 'Key' }
      },
      {
        path: 'message',
        name: 'Message',
        component: () => import('@/views/message/index.vue'),
        meta: { title: '消息中心', icon: 'Bell' }
      },
      {
        path: 'operation-log',
        name: 'OperationLog',
        component: () => import('@/views/operation-log/index.vue'),
        meta: { title: '操作日志', icon: 'Document' }
      },
      {
        path: 'ip-blacklist',
        name: 'IpBlacklist',
        component: () => import('@/views/ip-blacklist/index.vue'),
        meta: { title: 'IP 黑名单', icon: 'Warning' }
      },
      {
        path: 'favorite',
        name: 'Favorite',
        component: () => import('@/views/favorite/index.vue'),
        meta: { title: '收藏管理', icon: 'Star' }
      },
      {
        path: 'recycle-bin',
        name: 'RecycleBin',
        component: () => import('@/views/recycle-bin/index.vue'),
        meta: { title: '回收站', icon: 'Delete' }
      },
      {
        path: 'rate-limit',
        name: 'RateLimit',
        component: () => import('@/views/rate-limit/index.vue'),
        meta: { title: '限流规则', icon: 'Lightning' }
      },
      {
        path: 'c-menu',
        name: 'CMenu',
        component: () => import('@/views/c-menu/index.vue'),
        meta: { title: 'C端菜单', icon: 'Grid' }
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/profile/index.vue'),
        meta: { title: '个人中心', icon: 'UserFilled' }
      }
    ]
  },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

// 全局前置守卫：未登录跳登录
router.beforeEach((to, from, next) => {
  const token = getToken()
  if (to.meta.public) {
    if (token && to.path === '/login') return next('/')
    return next()
  }
  if (!token) return next('/login')
  next()
})

/**
 * 【动态路由扩展点】
 * 若后端 my-tree 返回的 menu.component 形如 "product/index"，可在登录后通过
 * Vite 的 glob 动态导入 API（在构建期扫描 views 目录）按 component 字符串
 * 映射到对应 .vue 组件，再调用 router.addRoute('Layout', {...}) 注入路由。
 * 当前骨架已在上方静态注册全部页面，开箱即用。
 */

export default router
