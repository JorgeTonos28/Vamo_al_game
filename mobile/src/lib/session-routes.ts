import { sessionState } from '@/state/session'

export function regularAppRouteName(): string {
  return sessionState.tenancy?.has_blocked_access ? 'app-unavailable' : 'app-home'
}

export function regularAppRoutePath(): string {
  return sessionState.tenancy?.has_blocked_access ? '/app/unavailable' : '/app/home'
}

export function defaultAuthenticatedRouteName(): string {
  return sessionState.user?.is_general_admin ? 'command-center-dashboard' : regularAppRouteName()
}

export function defaultAuthenticatedRoutePath(): string {
  return sessionState.user?.is_general_admin ? '/command-center/dashboard' : regularAppRoutePath()
}

export function isGeneralAdminSession(): boolean {
  return sessionState.user?.is_general_admin ?? false
}
