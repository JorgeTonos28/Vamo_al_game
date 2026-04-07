<script setup lang="ts">
import { IonIcon, useIonRouter } from '@ionic/vue'
import { menuOutline } from 'ionicons/icons'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BrandLogo from '@/components/BrandLogo.vue'
import MobileNavigationSheet from '@/components/MobileNavigationSheet.vue'
import { leagueNavItems } from '@/lib/league-navigation'
import { sessionState } from '@/state/session'

const MODULE_NAV_SCROLL_STORAGE_KEY = 'vag-mobile-module-nav-scroll-left'

const props = defineProps<{
  title: string
  description: string
  commandCenter?: boolean
}>()

const isMenuOpen = ref(false)
const moduleNavRef = ref<HTMLElement | null>(null)
const swipeSurfaceRef = ref<HTMLElement | null>(null)
const route = useRoute()
const router = useRouter()
const ionRouter = useIonRouter()
let touchStartX = 0
let touchStartY = 0
let touchActive = false
let navigationLocked = false

const initials = computed(() => {
  const name = sessionState.user?.name ?? 'VG'

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
})

const moduleItems = computed(() =>
  props.commandCenter
    ? []
    : leagueNavItems(sessionState.tenancy)
        .filter((item) => item.routeName !== 'settings-profile')
)

function isModuleActive(href: string, routeName: string): boolean {
  const routePath = route.path

  if (routePath === href || routePath.startsWith(`${href}/`)) {
    return true
  }

  if (href.includes('/league/modules/')) {
    return false
  }

  return route.name === routeName
}

function readSavedModuleNavScroll(): number {
  if (typeof window === 'undefined') {
    return 0
  }

  const value = window.sessionStorage.getItem(MODULE_NAV_SCROLL_STORAGE_KEY)
  const parsed = value ? Number.parseFloat(value) : Number.NaN

  return Number.isFinite(parsed) ? parsed : 0
}

function persistModuleNavScroll(scrollLeft?: number): void {
  if (typeof window === 'undefined') {
    return
  }

  const nextValue = scrollLeft ?? moduleNavRef.value?.scrollLeft ?? readSavedModuleNavScroll()
  window.sessionStorage.setItem(MODULE_NAV_SCROLL_STORAGE_KEY, String(nextValue))
}

function saveModuleNavScroll(): void {
  persistModuleNavScroll()
}

async function goToModule(href: string, event?: Event): Promise<void> {
  if (href === route.path) {
    return
  }

  navigationLocked = true
  const button = event?.currentTarget as HTMLElement | null
  const container = (button?.closest('.topbar__module-nav') as HTMLElement | null) ?? moduleNavRef.value

  persistModuleNavScroll(container?.scrollLeft)
  button?.blur()
  await router.push(href)
}

function resolveSwipeSurface(): HTMLElement | null {
  return moduleNavRef.value?.closest('.mobile-shell') as HTMLElement | null
}

function bindSwipeSurface(): void {
  const nextSurface = resolveSwipeSurface()

  if (swipeSurfaceRef.value === nextSurface) {
    return
  }

  if (swipeSurfaceRef.value) {
    swipeSurfaceRef.value.removeEventListener('touchstart', onTouchStart)
    swipeSurfaceRef.value.removeEventListener('touchend', onTouchEnd)
    swipeSurfaceRef.value.removeEventListener('touchcancel', onTouchCancel)
  }

  swipeSurfaceRef.value = nextSurface

  if (swipeSurfaceRef.value) {
    swipeSurfaceRef.value.addEventListener('touchstart', onTouchStart, { passive: true })
    swipeSurfaceRef.value.addEventListener('touchend', onTouchEnd, { passive: true })
    swipeSurfaceRef.value.addEventListener('touchcancel', onTouchCancel, { passive: true })
  }
}

function onTouchCancel(): void {
  touchActive = false
}

function onTouchStart(event: TouchEvent): void {
  const target = event.target as HTMLElement | null

  if (
    props.commandCenter ||
    navigationLocked ||
    moduleItems.value.length < 2 ||
    target?.closest('input, select, textarea, button, a, ion-input, ion-select, ion-textarea, .sheet-backdrop, .sheet-panel, .overlay, [data-no-module-swipe]')
  ) {
    touchActive = false

    return
  }

  const touch = event.touches[0] ?? event.changedTouches[0]
  touchStartX = touch.clientX
  touchStartY = touch.clientY
  touchActive = true
}

function onTouchEnd(event: TouchEvent): void {
  if (!touchActive || props.commandCenter) {
    return
  }

  touchActive = false

  const touch = event.changedTouches[0]
  const deltaX = touch.clientX - touchStartX
  const deltaY = touch.clientY - touchStartY

  if (Math.abs(deltaX) < 72 || Math.abs(deltaX) < Math.abs(deltaY) * 1.35) {
    return
  }

  const currentIndex = moduleItems.value.findIndex((item) => isModuleActive(item.href, item.routeName))

  if (currentIndex === -1) {
    return
  }

  const nextIndex = deltaX < 0 ? currentIndex + 1 : currentIndex - 1
  const nextItem = moduleItems.value[nextIndex]

  if (!nextItem || nextItem.href === route.path) {
    return
  }

  navigationLocked = true
  persistModuleNavScroll()
  ionRouter.navigate(nextItem.href, deltaX < 0 ? 'forward' : 'back')
}

onMounted(() => {
  void nextTick(() => {
    bindSwipeSurface()
  })
})

onBeforeUnmount(() => {
  if (swipeSurfaceRef.value) {
    swipeSurfaceRef.value.removeEventListener('touchstart', onTouchStart)
    swipeSurfaceRef.value.removeEventListener('touchend', onTouchEnd)
    swipeSurfaceRef.value.removeEventListener('touchcancel', onTouchCancel)
  }
})

watch(
  () => route.fullPath,
  async () => {
    navigationLocked = false
    touchActive = false
    await nextTick()

    if (moduleNavRef.value) {
      moduleNavRef.value.scrollLeft = readSavedModuleNavScroll()
    }

    bindSwipeSurface()
  },
  { immediate: true },
)
</script>

<template>
  <header class="topbar">
    <div class="topbar__row">
      <BrandLogo compact />
      <div class="topbar__actions">
        <div class="topbar__avatar">{{ initials }}</div>
        <button class="topbar__menu-button" type="button" @click="isMenuOpen = true">
          <IonIcon :icon="menuOutline" />
        </button>
      </div>
    </div>

    <div class="topbar__copy">
      <p class="app-kicker topbar__kicker">
        {{ props.commandCenter ? 'Centro de mando' : 'Panel operativo' }}
      </p>
      <h1 class="topbar__title">{{ props.title }}</h1>
      <p class="topbar__description">{{ props.description }}</p>
    </div>

    <div
      v-if="moduleItems.length > 0"
      ref="moduleNavRef"
      class="topbar__module-nav"
      data-no-module-swipe
      @scroll.passive="saveModuleNavScroll"
    >
      <button
        v-for="item in moduleItems"
        :key="`${item.routeName}-${item.href}`"
        :class="['topbar__module-chip', { 'is-active': isModuleActive(item.href, item.routeName) }]"
        type="button"
        @click="goToModule(item.href, $event)"
      >
        {{ item.label }}
      </button>
    </div>

    <MobileNavigationSheet v-model:is-open="isMenuOpen" :command-center="props.commandCenter" />
  </header>
</template>

<style scoped>
.topbar,
.topbar__copy,
.topbar__actions,
.topbar__module-nav {
  display: flex;
}

.topbar {
  flex-direction: column;
  gap: 18px;
}

.topbar__copy {
  flex-direction: column;
}

.topbar__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.topbar__actions {
  align-items: center;
  gap: 10px;
}

.topbar__avatar {
  display: flex;
  height: 42px;
  width: 42px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  background: #131b2f;
  font-size: 13px;
  font-weight: 700;
  color: #f8fafc;
}

.topbar__menu-button {
  display: flex;
  height: 42px;
  width: 42px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  background: #131b2f;
  color: #f8fafc;
  font-size: 18px;
  transition:
    transform 0.1s ease-out,
    opacity 0.1s ease-out;
}

.topbar__menu-button:active {
  transform: scale(0.97);
  opacity: 0.8;
}

.topbar__title,
.topbar__description {
  margin: 0;
}

.topbar__copy {
  gap: 10px;
}

.topbar__kicker {
  color: #e5b849;
}

.topbar__title {
  font-size: 42px;
  line-height: 0.92;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #f8fafc;
}

.topbar__description {
  font-size: 14px;
  line-height: 1.75;
  color: #94a3b8;
}

.topbar__module-nav {
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 2px;
  scrollbar-width: none;
}

.topbar__module-nav::-webkit-scrollbar {
  display: none;
}

.topbar__module-chip {
  min-height: 40px;
  flex: 0 0 auto;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  background: #131b2f;
  padding: 0 14px;
  font-size: 12px;
  font-weight: 700;
  color: #94a3b8;
  transition:
    transform 0.1s ease-out,
    opacity 0.1s ease-out,
    border-color 0.2s ease,
    color 0.2s ease,
    background 0.2s ease;
}

.topbar__module-chip.is-active {
  border-color: rgba(229, 184, 73, 0.28);
  background: rgba(229, 184, 73, 0.12);
  color: #f8fafc;
}

.topbar__module-chip:active {
  transform: scale(0.97);
  opacity: 0.8;
}
</style>
