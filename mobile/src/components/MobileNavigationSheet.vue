<script setup lang="ts">
import { IonIcon, useIonRouter } from '@ionic/vue'
import { logOutOutline, settingsOutline, shieldOutline, speedometerOutline, peopleOutline, menuOutline } from 'ionicons/icons'
import { computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { regularAppRouteName } from '@/lib/session-routes'
import { logout } from '@/services/auth'
import { sessionState } from '@/state/session'

const props = defineProps<{
  isOpen: boolean
  commandCenter?: boolean
}>()

const emit = defineEmits<{
  (event: 'update:isOpen', value: boolean): void
}>()

const route = useRoute()
const router = useRouter()
const ionRouter = useIonRouter()

const navItems = computed(() => {
  if (props.commandCenter) {
    return [
      { label: 'Panel', routeName: 'command-center-dashboard', href: '/command-center/dashboard', icon: speedometerOutline },
      { label: 'Usuarios', routeName: 'command-center-users', href: '/command-center/users', icon: peopleOutline },
      { label: 'Ligas', routeName: 'command-center-leagues', href: '/command-center/leagues', icon: shieldOutline },
      { label: 'Ajustes', routeName: 'command-center-settings-profile', href: '/command-center/settings/profile', icon: settingsOutline },
    ]
  }

  return [
    { label: 'Panel', routeName: regularAppRouteName(), href: regularAppRouteName() === 'app-unavailable' ? '/app/unavailable' : '/app/home', icon: speedometerOutline },
    { label: 'Ajustes', routeName: 'settings-profile', href: '/app/settings/profile', icon: settingsOutline },
  ]
})

function close(): void {
  emit('update:isOpen', false)
}

async function goTo(href: string): Promise<void> {
  close()
  await nextTick()
  await router.push(href)
}

async function handleLogout(): Promise<void> {
  close()
  await nextTick()
  await logout()
  ionRouter.navigate('/login', 'root', 'replace')
}

function isActive(routeName: string): boolean {
  return route.name === routeName
}
</script>

<template>
  <Teleport to="body">
    <div v-if="props.isOpen" class="menu-backdrop" @click.self="close">
      <section class="menu-panel">
        <div class="menu-handle" />

        <div class="menu-header">
          <p class="app-kicker menu-kicker">{{ props.commandCenter ? 'Centro de mando' : 'Navegacion' }}</p>
          <h2 class="menu-title">{{ sessionState.user?.name ?? 'Usuario' }}</h2>
          <p class="menu-description">
            {{ sessionState.user?.email }}
          </p>
        </div>

        <div class="menu-list">
          <button
            v-for="item in navItems"
            :key="item.routeName"
            :class="['menu-option', { 'is-active': isActive(item.routeName) }]"
            type="button"
            @click="goTo(item.href)"
          >
            <span class="menu-option__icon">
              <IonIcon :icon="item.icon" />
            </span>
            <span class="menu-option__label">{{ item.label }}</span>
          </button>
        </div>

        <button class="menu-option menu-option--logout" type="button" @click="handleLogout">
          <span class="menu-option__icon">
            <IonIcon :icon="logOutOutline" />
          </span>
          <span class="menu-option__label">Cerrar sesion</span>
        </button>

        <button class="menu-close" type="button" @click="close">
          <IonIcon :icon="menuOutline" />
          <span>Cerrar menu</span>
        </button>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.menu-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(3, 7, 18, 0.72);
  padding: 16px;
}

.menu-panel,
.menu-header,
.menu-list {
  display: flex;
  flex-direction: column;
}

.menu-panel {
  width: min(100%, 480px);
  gap: 18px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 28px 28px 20px 20px;
  background: #1a243a;
  padding: 14px 16px 20px;
  animation: menu-enter 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.menu-handle {
  width: 52px;
  height: 5px;
  margin: 0 auto;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
}

.menu-header,
.menu-list {
  gap: 10px;
}

.menu-kicker {
  color: #e5b849;
}

.menu-title,
.menu-description {
  margin: 0;
}

.menu-title {
  font-size: 22px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}

.menu-description {
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.menu-option,
.menu-close {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 52px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  padding: 0 16px;
  transition:
    transform 0.1s ease-out,
    opacity 0.1s ease-out,
    border-color 0.2s ease;
}

.menu-option,
.menu-close {
  background: #0e1628;
  color: #f8fafc;
}

.menu-option:active,
.menu-close:active {
  transform: scale(0.97);
  opacity: 0.8;
}

.menu-option.is-active {
  border-color: rgba(229, 184, 73, 0.28);
  background: rgba(229, 184, 73, 0.12);
}

.menu-option--logout {
  background: rgba(248, 113, 113, 0.1);
  color: #fecaca;
}

.menu-option__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.menu-option__label {
  font-size: 14px;
  font-weight: 700;
}

.menu-close {
  justify-content: center;
}

@keyframes menu-enter {
  from {
    transform: translate3d(0, 32px, 0);
    opacity: 0;
  }

  to {
    transform: translate3d(0, 0, 0);
    opacity: 1;
  }
}
</style>
