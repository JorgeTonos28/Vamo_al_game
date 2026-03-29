import { createRouter, createWebHistory } from '@ionic/vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { defaultAuthenticatedRouteName, isGeneralAdminSession, regularAppRouteName } from '@/lib/session-routes'
import AppearancePage from '@/pages/AppearancePage.vue'
import AppUnavailablePage from '@/pages/AppUnavailablePage.vue'
import CommandCenterDashboardPage from '@/pages/CommandCenterDashboardPage.vue'
import CommandCenterLeaguesPage from '@/pages/CommandCenterLeaguesPage.vue'
import CommandCenterProfilePage from '@/pages/CommandCenterProfilePage.vue'
import CommandCenterSecurityPage from '@/pages/CommandCenterSecurityPage.vue'
import CommandCenterSettingsPage from '@/pages/CommandCenterSettingsPage.vue'
import CommandCenterTabsPage from '@/pages/CommandCenterTabsPage.vue'
import CommandCenterUsersPage from '@/pages/CommandCenterUsersPage.vue'
import GoogleAuthCallbackPage from '@/pages/GoogleAuthCallbackPage.vue'
import HealthPage from '@/pages/HealthPage.vue'
import HomePage from '@/pages/HomePage.vue'
import LandingPage from '@/pages/LandingPage.vue'
import LeagueArrivalPage from '@/pages/LeagueArrivalPage.vue'
import LeagueGamePage from '@/pages/LeagueGamePage.vue'
import LeagueManagementPage from '@/pages/LeagueManagementPage.vue'
import LeagueModulePlaceholderPage from '@/pages/LeagueModulePlaceholderPage.vue'
import LeaguePanelPage from '@/pages/LeaguePanelPage.vue'
import LeagueQueuePage from '@/pages/LeagueQueuePage.vue'
import LeagueScoutPage from '@/pages/LeagueScoutPage.vue'
import LeagueSeasonPage from '@/pages/LeagueSeasonPage.vue'
import LeagueStatsPage from '@/pages/LeagueStatsPage.vue'
import LeagueTablePage from '@/pages/LeagueTablePage.vue'
import LoginPage from '@/pages/LoginPage.vue'
import ProfilePage from '@/pages/ProfilePage.vue'
import RegisterPage from '@/pages/RegisterPage.vue'
import SecurityPage from '@/pages/SecurityPage.vue'
import StarterPage from '@/pages/StarterPage.vue'
import TabsPage from '@/pages/TabsPage.vue'
import TwoFactorChallengePage from '@/pages/TwoFactorChallengePage.vue'
import { fetchCurrentUser } from '@/services/auth'
import { hydrateBrandingState } from '@/state/branding'
import { hydrateSessionState, sessionState } from '@/state/session'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'starter',
    component: StarterPage,
  },
  {
    path: '/landing',
    name: 'landing',
    component: LandingPage,
    meta: {
      guestOnly: true,
    },
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
    path: '/auth/google/callback',
    name: 'google-callback',
    component: GoogleAuthCallbackPage,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterPage,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/two-factor-challenge',
    name: 'two-factor-challenge',
    component: TwoFactorChallengePage,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/app/',
    component: TabsPage,
    meta: {
      requiresAuth: true,
      regularAppOnly: true,
    },
    children: [
      {
        path: '',
        redirect: '/app/home',
      },
      {
        path: 'home',
        name: 'app-home',
        component: HomePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/panel',
        name: 'league-panel',
        component: LeaguePanelPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/arrival',
        name: 'league-arrival',
        component: LeagueArrivalPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/game',
        name: 'league-game',
        component: LeagueGamePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/queue',
        name: 'league-queue',
        component: LeagueQueuePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/stats',
        name: 'league-stats',
        component: LeagueStatsPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/table',
        name: 'league-table',
        component: LeagueTablePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/season',
        name: 'league-season',
        component: LeagueSeasonPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/scout',
        name: 'league-scout',
        component: LeagueScoutPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/management',
        name: 'league-management',
        component: LeagueManagementPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'league/modules/:module',
        name: 'league-placeholder',
        component: LeagueModulePlaceholderPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'settings',
        redirect: '/app/settings/profile',
      },
      {
        path: 'settings/profile',
        name: 'settings-profile',
        component: ProfilePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'settings/security',
        name: 'settings-security',
        component: SecurityPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'settings/appearance',
        name: 'settings-appearance',
        component: AppearancePage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
      {
        path: 'health',
        name: 'health',
        component: HealthPage,
        meta: {
          requiresAuth: true,
          regularAppOnly: true,
        },
      },
    ],
  },
  {
    path: '/app/unavailable',
    name: 'app-unavailable',
    component: AppUnavailablePage,
    meta: {
      requiresAuth: true,
      regularAppOnly: true,
      blockedAccessOnly: true,
      allowBlocked: true,
    },
  },
  {
    path: '/command-center/',
    component: CommandCenterTabsPage,
    meta: {
      requiresAuth: true,
      generalAdminOnly: true,
    },
    children: [
      {
        path: '',
        redirect: '/command-center/dashboard',
      },
      {
        path: 'dashboard',
        name: 'command-center-dashboard',
        component: CommandCenterDashboardPage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
        },
      },
      {
        path: 'users',
        name: 'command-center-users',
        component: CommandCenterUsersPage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
        },
      },
      {
        path: 'leagues',
        name: 'command-center-leagues',
        component: CommandCenterLeaguesPage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
        },
      },
      {
        path: 'settings',
        redirect: '/command-center/settings/profile',
      },
      {
        path: 'settings/profile',
        name: 'command-center-settings-profile',
        component: CommandCenterProfilePage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
        },
      },
      {
        path: 'settings/security',
        name: 'command-center-settings-security',
        component: CommandCenterSecurityPage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
        },
      },
      {
        path: 'settings/appearance',
        name: 'command-center-settings-appearance',
        component: CommandCenterSettingsPage,
        meta: {
          requiresAuth: true,
          generalAdminOnly: true,
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
  await hydrateBrandingState()

  if (to.meta.requiresAuth && !sessionState.token) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && sessionState.token) {
    if (!await ensureCurrentUser()) {
      return { name: 'login' }
    }

    return { name: defaultAuthenticatedRouteName() }
  }

  if (to.meta.requiresAuth && sessionState.token) {
    if (!await ensureCurrentUser()) {
      return { name: 'login' }
    }
  }

  if (to.meta.generalAdminOnly && !isGeneralAdminSession()) {
    return { name: regularAppRouteName() }
  }

  if (to.meta.regularAppOnly && isGeneralAdminSession()) {
    return { name: 'command-center-dashboard' }
  }

  if (to.meta.blockedAccessOnly && !sessionState.tenancy?.has_blocked_access) {
    return { name: 'app-home' }
  }

  if (
    to.meta.regularAppOnly &&
    !to.meta.allowBlocked &&
    sessionState.tenancy?.has_blocked_access
  ) {
    return { name: 'app-unavailable' }
  }

  return true
})

async function ensureCurrentUser(): Promise<boolean> {
  if (!sessionState.token) {
    return false
  }

  if (
    sessionState.user &&
    (sessionState.user.is_general_admin || sessionState.tenancy)
  ) {
    return true
  }

  try {
    await fetchCurrentUser()

    return true
  } catch {
    return false
  }
}

export default router
