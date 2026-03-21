<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const tabs = [
  { name: 'settings-profile', label: 'Profile' },
  { name: 'settings-security', label: 'Security' },
  { name: 'settings-appearance', label: 'Appearance' },
]

const activeRouteName = computed(() => route.name)

function isActive(name: string): boolean {
  return activeRouteName.value === name
}
</script>

<template>
  <div class="mobile-stack">
    <section class="settings-heading">
      <div class="settings-copy">
        <p class="app-kicker settings-kicker">Ajustes</p>
        <p class="settings-description">
          Controla tu cuenta, seguridad y preferencias dentro del mismo shell mobile-first.
        </p>
      </div>

      <nav class="settings-nav" aria-label="Settings">
        <button
          v-for="item in tabs"
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
