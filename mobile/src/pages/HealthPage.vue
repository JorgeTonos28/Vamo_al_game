<script setup lang="ts">
import { onIonViewWillEnter, IonButton, IonContent, IonPage, IonRefresher, IonRefresherContent } from '@ionic/vue'
import { ref } from 'vue'
import { handleMobileRefresher } from '@/services/app-refresh'
import { fetchHealth } from '@/services/auth'

const health = ref<Awaited<ReturnType<typeof fetchHealth>> | null>(null)
const isLoading = ref(false)

async function loadHealth(): Promise<void> {
  isLoading.value = true

  try {
    health.value = await fetchHealth()
  } finally {
    isLoading.value = false
  }
}

onIonViewWillEnter(loadHealth)

async function handleRefresh(event: CustomEvent): Promise<void> {
  await handleMobileRefresher(event, loadHealth)
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
        <div class="mobile-stack">
          <section class="app-surface health-card">
            <div>
              <p class="app-kicker">Health</p>
              <h1 class="app-display health-title">{{ health?.status ?? '...' }}</h1>
            </div>

            <dl class="health-list">
              <div>
                <dt class="app-kicker">Aplicacion</dt>
                <dd class="app-body-copy">{{ health?.app }}</dd>
              </div>
              <div>
                <dt class="app-kicker">API</dt>
                <dd class="app-body-copy">{{ health?.api_version }}</dd>
              </div>
              <div>
                <dt class="app-kicker">Timestamp</dt>
                <dd class="app-body-copy">{{ health?.timestamp }}</dd>
              </div>
            </dl>
          </section>

          <section class="app-surface">
            <IonButton expand="block" @click="loadHealth">
              {{ isLoading ? 'Consultando...' : 'Consultar health' }}
            </IonButton>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.health-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.health-title {
  margin: 8px 0 0;
  font-size: 44px;
  line-height: 0.92;
}

.health-list {
  display: grid;
  gap: 12px;
  margin: 0;
}

.health-list dt,
.health-list dd {
  margin: 0;
}
</style>
