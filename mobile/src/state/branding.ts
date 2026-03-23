import { reactive } from 'vue'
import { fetchBranding } from '@/services/branding'
import type { Branding } from '@/types/api'

export const brandingState = reactive<{
  ready: boolean
  branding: Branding | null
}>({
  ready: false,
  branding: null,
})

let hydrationPromise: Promise<void> | null = null

export async function hydrateBrandingState(force = false): Promise<void> {
  if (brandingState.ready && !force) {
    return
  }

  if (!hydrationPromise || force) {
    hydrationPromise = (async () => {
      try {
        brandingState.branding = await fetchBranding()
      } catch {
        brandingState.branding = null
      } finally {
        brandingState.ready = true
      }
    })()
  }

  await hydrationPromise
}

export function setBranding(branding: Branding): void {
  brandingState.branding = branding
  brandingState.ready = true
}
