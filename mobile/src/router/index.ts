import { createRouter, createWebHistory } from '@ionic/vue-router'
import type { RouteRecordRaw } from 'vue-router'
import HealthPage from '@/pages/HealthPage.vue'
import HomePage from '@/pages/HomePage.vue'
import LoginPage from '@/pages/LoginPage.vue'
import ProfilePage from '@/pages/ProfilePage.vue'
import TabsPage from '@/pages/TabsPage.vue'
import { fetchCurrentUser } from '@/services/auth'
import { hydrateSessionState, sessionState } from '@/state/session'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: '/tabs/home',
  },
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/tabs/',
    component: TabsPage,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        redirect: '/tabs/home',
      },
      {
        path: 'home',
        name: 'home',
        component: HomePage,
        meta: {
          requiresAuth: true,
        },
      },
      {
        path: 'profile',
        name: 'profile',
        component: ProfilePage,
        meta: {
          requiresAuth: true,
        },
      },
      {
        path: 'health',
        name: 'health',
        component: HealthPage,
        meta: {
          requiresAuth: true,
        },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  await hydrateSessionState()

  if (to.meta.requiresAuth && !sessionState.token) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && sessionState.token) {
    return { name: 'home' }
  }

  if (to.meta.requiresAuth && sessionState.token && !sessionState.user) {
    try {
      await fetchCurrentUser()
    } catch {
      return { name: 'login' }
    }
  }

  return true
})

export default router
