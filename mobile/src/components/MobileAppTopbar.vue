<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { menuOutline } from 'ionicons/icons'
import { computed, ref } from 'vue'
import BrandLogo from '@/components/BrandLogo.vue'
import LeagueSwitcherSheet from '@/components/LeagueSwitcherSheet.vue'
import MobileNavigationSheet from '@/components/MobileNavigationSheet.vue'
import { sessionState } from '@/state/session'

const props = defineProps<{
  title: string
  description: string
  commandCenter?: boolean
}>()

const isLeagueSheetOpen = ref(false)
const isMenuOpen = ref(false)

const initials = computed(() => {
  const name = sessionState.user?.name ?? 'VG'

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
})

const tenantLabel = computed(() => {
  if (sessionState.tenancy?.active_league) {
    const league = sessionState.tenancy.active_league
    return league.emoji ? `${league.emoji} ${league.name}` : league.name
  }

  return sessionState.tenancy?.guest_mode ? 'Modo invitado' : 'Sin liga activa'
})

const tenantMeta = computed(() => {
  if (!sessionState.tenancy) {
    return ''
  }

  if (sessionState.tenancy.guest_mode) {
    return 'Sin acceso a una liga completa'
  }

  return sessionState.tenancy.active_league?.is_active === false
    ? 'Acceso revocado'
    : sessionState.tenancy.active_league?.role_label ?? 'Tenant activo'
})
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

    <div v-if="!props.commandCenter && sessionState.user && !sessionState.user.is_general_admin" class="topbar__tenant">
      <button class="topbar__tenant-button" type="button" @click="isLeagueSheetOpen = true">
        <span class="topbar__tenant-label">{{ tenantLabel }}</span>
        <span class="topbar__tenant-meta">{{ tenantMeta }}</span>
      </button>
    </div>

    <div class="topbar__copy">
      <p class="app-kicker topbar__kicker">
        {{ props.commandCenter ? 'Centro de mando' : 'Panel operativo' }}
      </p>
      <h1 class="topbar__title">{{ props.title }}</h1>
      <p class="topbar__description">{{ props.description }}</p>
    </div>

    <LeagueSwitcherSheet v-model:is-open="isLeagueSheetOpen" />
    <MobileNavigationSheet v-model:is-open="isMenuOpen" :command-center="props.commandCenter" />
  </header>
</template>

<style scoped>
.topbar,
.topbar__copy,
.topbar__actions {
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

.topbar__tenant-button {
  width: 100%;
  min-height: 56px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 18px;
  background: #131b2f;
  padding: 14px 16px;
  text-align: left;
  transition:
    transform 0.1s ease-out,
    opacity 0.1s ease-out;
}

.topbar__tenant-button:active {
  transform: scale(0.97);
  opacity: 0.8;
}

.topbar__tenant-label,
.topbar__title,
.topbar__description {
  margin: 0;
}

.topbar__tenant-label {
  font-size: 14px;
  font-weight: 700;
  color: #f8fafc;
}

.topbar__tenant-meta {
  font-size: 12px;
  line-height: 1.4;
  color: #94a3b8;
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
</style>
