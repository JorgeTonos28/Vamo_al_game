<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent } from '@ionic/vue'
import { Monitor, Moon, Sun } from 'lucide-vue-next'
import SettingsLayout from '@/components/SettingsLayout.vue'
import { useAppearance } from '@/composables/useAppearance'
import type { Appearance } from '@/composables/useAppearance'
import { handleMobileRefresher } from '@/services/app-refresh'

const { appearance, updateAppearance } = useAppearance()

const tabs: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
  { value: 'light', label: 'Claro', icon: Sun },
  { value: 'dark', label: 'Oscuro', icon: Moon },
  { value: 'system', label: 'Sistema', icon: Monitor },
]

async function handleRefresh(event: CustomEvent): Promise<void> {
  await handleMobileRefresher(event)
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <template #fixed>
        <IonRefresher @ionRefresh="handleRefresh">
          <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
        </IonRefresher>
      </template>

      <div class="mobile-shell">
        <SettingsLayout>
          <section class="appearance-section">
            <div class="appearance-copy">
              <p class="app-kicker">Apariencia</p>
              <p class="app-body-copy">Ajusta la preferencia visual del shell movil.</p>
            </div>

            <div class="appearance-tabs">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                :class="['appearance-tab', { 'is-active': appearance === tab.value }]"
                type="button"
                @click="updateAppearance(tab.value)"
              >
                <component :is="tab.icon" class="appearance-icon" />
                <span>{{ tab.label }}</span>
              </button>
            </div>
          </section>
        </SettingsLayout>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.appearance-section,
.appearance-copy {
  display: flex;
  flex-direction: column;
}

.appearance-section {
  gap: 24px;
}

.appearance-copy {
  gap: 8px;
}

.appearance-tabs {
  display: inline-flex;
  gap: 4px;
  border-radius: 12px;
  background: #0e1628;
  padding: 4px;
}

.appearance-tab {
  display: inline-flex;
  min-height: 48px;
  align-items: center;
  gap: 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  padding: 0 14px;
  color: #94a3b8;
}

.appearance-tab.is-active {
  background: #1e293b;
  color: #f8fafc;
}

.appearance-icon {
  height: 16px;
  width: 16px;
}
</style>
