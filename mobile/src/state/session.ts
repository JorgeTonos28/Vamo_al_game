import { Preferences } from '@capacitor/preferences'
import { reactive } from 'vue'
import type { ApiUser, TenancyContext } from '@/types/api'

const TOKEN_KEY = 'vamo-al-game.token'

export const sessionState = reactive<{
  ready: boolean
  token: string | null
  user: ApiUser | null
  tenancy: TenancyContext | null
}>({
  ready: false,
  token: null,
  user: null,
  tenancy: null,
})

let hydrationPromise: Promise<void> | null = null

export async function hydrateSessionState(): Promise<void> {
  if (sessionState.ready) {
    return
  }

  if (!hydrationPromise) {
    hydrationPromise = (async () => {
      const { value } = await Preferences.get({ key: TOKEN_KEY })
      sessionState.token = value
      sessionState.ready = true
    })()
  }

  await hydrationPromise
}

export async function setSessionToken(token: string): Promise<void> {
  sessionState.token = token
  await Preferences.set({ key: TOKEN_KEY, value: token })
}

export function setSessionUser(user: ApiUser | null): void {
  sessionState.user = user
}

export function setSessionTenancy(tenancy: TenancyContext | null): void {
  sessionState.tenancy = tenancy
}

export async function clearSessionState(): Promise<void> {
  sessionState.ready = true
  sessionState.token = null
  sessionState.user = null
  sessionState.tenancy = null
  await Preferences.remove({ key: TOKEN_KEY })
}
