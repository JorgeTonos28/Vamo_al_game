<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'

type SettingsTab = {
  name: string
  label: string
}

const route = useRoute()
const router = useRouter()

const props = withDefaults(
  defineProps<{
    tabs?: SettingsTab[]
    title?: string
    description?: string
    commandCenter?: boolean
  }>(),
  {
    tabs: () => [
      { name: 'settings-profile', label: 'Perfil' },
      { name: 'settings-security', label: 'Seguridad' },
      { name: 'settings-appearance', label: 'Apariencia' },
    ],
    title: 'Ajustes',
    description: 'Administra tu cuenta, seguridad y preferencias desde el mismo shell multi-tenant.',
    commandCenter: false,
  },
)

const activeRouteName = computed(() => route.name)

function isActive(name: string): boolean {
  return activeRouteName.value === name
}
</script>

<template>
  <div class="mobile-stack">
    <MobileAppTopbar
      :title="props.title"
      :description="props.description"
      :command-center="props.commandCenter"
    />

    <section class="settings-heading">
      <div class="settings-copy">
        <p class="app-kicker settings-kicker">Ajustes</p>
        <p class="settings-description">
          {{ props.description }}
        </p>
      </div>

      <nav class="settings-nav" aria-label="Settings">
        <button
          v-for="item in props.tabs"
          :key="item.name"
          :class="['app-tab-link settings-link', { 'is-active': isActive(item.name) }]"
          type="button"
          @click="router.push({ name: item.name })"
        >
          {{ item.label }}
        </button>
      </nav>
    </section>

    <section class="app-surface settings-surface">
      <slot />
    </section>
  </div>
</template>

<style scoped>
.settings-heading,
.settings-copy {
  display: flex;
  flex-direction: column;
}

.settings-heading {
  gap: 16px;
}

.settings-copy {
  gap: 8px;
}

.settings-kicker {
  color: #e5b849;
}

.settings-description {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: #94a3b8;
}

.settings-nav {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 2px;
  scrollbar-width: none;
}

.settings-nav::-webkit-scrollbar {
  display: none;
}

.settings-link {
  border: 0;
  background: transparent;
  padding-inline: 0;
}

.settings-surface {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
</style>
