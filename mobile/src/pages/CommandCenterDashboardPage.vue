<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchCommandCenterDashboard } from '@/services/command-center'
import type { CommandCenterMetrics } from '@/types/api'

const metrics = ref<CommandCenterMetrics | null>(null)
const isLoading = ref(false)

const cards: Array<{ key: keyof CommandCenterMetrics; title: string }> = [
  { key: 'total_users', title: 'Usuarios totales' },
  { key: 'active_leagues', title: 'Ligas activas' },
  { key: 'league_admins', title: 'Admins de ligas' },
  { key: 'members', title: 'Miembros' },
  { key: 'guests', title: 'Invitados' },
  { key: 'inactive_leagues', title: 'Ligas inactivas' },
]

async function loadDashboard(): Promise<void> {
  isLoading.value = true

  try {
    const response = await fetchCommandCenterDashboard()
    metrics.value = response.metrics
  } finally {
    isLoading.value = false
  }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
  try {
    await loadDashboard()
  } finally {
    await (event.target as HTMLIonRefresherElement).complete()
  }
}

onIonViewWillEnter(loadDashboard)
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar
            command-center
            title="Centro de mando"
            description="Supervisa cuentas, ligas activas y estado general del ecosistema desde un solo punto."
          />

          <section class="card-grid">
            <article v-for="card in cards" :key="card.key" class="app-surface stat-card">
              <p class="app-kicker stat-card__kicker">{{ card.title }}</p>
              <p class="stat-card__value">{{ isLoading ? '...' : metrics?.[card.key] ?? 0 }}</p>
            </article>
          </section>

          <section class="card-grid">
            <article class="app-surface detail-card">
              <p class="app-kicker stat-card__kicker">Invitaciones pendientes</p>
              <p class="detail-card__value">{{ isLoading ? '...' : metrics?.pending_invitations ?? 0 }}</p>
              <p class="detail-card__description">
                Usuarios creados por un admin general que aun no completan el onboarding.
              </p>
            </article>

            <article class="app-surface detail-card">
              <p class="app-kicker stat-card__kicker">Estado de ligas</p>
              <div class="status-row">
                <span class="status-pill status-pill--positive">
                  {{ isLoading ? '...' : metrics?.active_leagues ?? 0 }} activas
                </span>
                <span class="status-pill status-pill--negative">
                  {{ isLoading ? '...' : metrics?.inactive_leagues ?? 0 }} inactivas
                </span>
              </div>
              <p class="detail-card__description">
                Puedes revocar o restaurar el acceso de una liga desde el modulo de ligas.
              </p>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.card-grid {
  display: grid;
  gap: 12px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.detail-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.stat-card__kicker {
  color: #e5b849;
}

.stat-card__value {
  margin: 0;
  font-size: 40px;
  line-height: 0.95;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
  color: #f8fafc;
}

.detail-card__value,
.detail-card__description {
  margin: 0;
}

.detail-card__value {
  font-size: 32px;
  line-height: 0.95;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
  color: #f8fafc;
}

.detail-card__description {
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.status-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
}

.status-pill--positive {
  background: rgba(74, 222, 128, 0.12);
  color: #4ade80;
}

.status-pill--negative {
  background: rgba(248, 113, 113, 0.12);
  color: #fca5a5;
}
</style>
