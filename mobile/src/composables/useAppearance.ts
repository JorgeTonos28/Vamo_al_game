import type { ComputedRef, Ref } from 'vue'
import { computed, onMounted, ref } from 'vue'

export type Appearance = 'light' | 'dark' | 'system'
export type ResolvedAppearance = 'light' | 'dark'

export type UseAppearanceReturn = {
  appearance: Ref<Appearance>
  resolvedAppearance: ComputedRef<ResolvedAppearance>
  updateAppearance: (value: Appearance) => void
}

export function updateTheme(value: Appearance): void {
  if (typeof window === 'undefined') {
    return
  }

  if (value === 'system') {
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    document.documentElement.classList.toggle('dark', systemTheme === 'dark')

    return
  }

  document.documentElement.classList.toggle('dark', value === 'dark')
}

export function initializeTheme(): void {
  if (typeof window === 'undefined') {
    return
  }

  const savedAppearance = localStorage.getItem('appearance') as Appearance | null
  updateTheme(savedAppearance ?? 'system')
}

const appearance = ref<Appearance>('system')

export function useAppearance(): UseAppearanceReturn {
  onMounted(() => {
    const savedAppearance = localStorage.getItem('appearance') as Appearance | null

    if (savedAppearance) {
      appearance.value = savedAppearance
    }
  })

  const resolvedAppearance = computed<ResolvedAppearance>(() => {
    if (appearance.value === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    }

    return appearance.value
  })

  function updateAppearance(value: Appearance) {
    appearance.value = value
    localStorage.setItem('appearance', value)
    updateTheme(value)
  }

  return {
    appearance,
    resolvedAppearance,
    updateAppearance,
  }
}
