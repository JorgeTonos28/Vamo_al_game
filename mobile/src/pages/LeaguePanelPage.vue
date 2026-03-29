<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueHome  } from '@/services/league'
import type {LeagueHomePayload} from '@/services/league';

const router = useRouter()
const payload = ref<LeagueHomePayload | null>(null)
const isLoading = ref(false)

async function loadPage(): Promise<void> {
  isLoading.value = true

  try {
    const response = await fetchLeagueHome()
    payload.value = response

    if (response.mode !== 'operational') {
      await router.replace({ name: 'app-home' })
    }
  } finally {
    isLoading.value = false
  }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
  try {
    await loadPage()
  } finally {
    await (event.target as HTMLIonRefresherElement).complete()
  }
}

onIonViewWillEnter(loadPage)
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
          <MobileAppTopbar
            :title="payload?.league?.name ?? 'Panel de liga'"
            :description="payload?.role?.can_manage ? 'Acceso completo a la operación de la liga.' : 'Vista operativa para miembros de la liga.'"
          />

          <section class="app-surface summary-grid">
            <p v-if="isLoading" class="body-copy">Cargando panel...</p>

            <template v-else-if="payload?.summary">
              <article class="summary-card">
                <p class="app-kicker">Corte activo</p>
                <p class="summary-card__value">{{ payload.summary.cut_label }}</p>
                <p class="body-copy">
                  {{ payload.summary.is_past_due ? 'Corte vencido.' : 'Corte dentro del plazo.' }}
                </p>
              </article>

              <article class="summary-card">
                <p class="app-kicker">Miembros</p>
                <p class="summary-card__value">{{ payload.summary.players_count }}</p>
                <p class="body-copy">
                  {{ payload.summary.paid_players_count }} al día · {{ payload.summary.pending_players_count }} pendientes
                </p>
              </article>

              <article class="summary-card">
                <p class="app-kicker">Llegadas hoy</p>
                <p class="summary-card__value">{{ payload.summary.today_arrivals_count }}</p>
                <p class="body-copy">
                  {{ payload.summary.today_guests_count }} invitados cargados
                </p>
              </article>
            </template>
          </section>

          <section class="app-surface section-copy">
            <p class="app-kicker section-copy__kicker">Contexto</p>
            <p class="body-copy">
              Llegada ya construye la base de prioridad y cola que luego consumirá Juego.
              Gestión concentra pagos, gastos, directiva, referidos y configuración del corte.
            </p>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.summary-grid,
.summary-card,
.section-copy {
  display: flex;
  flex-direction: column;
}

.summary-grid {
  gap: 12px;
}

.summary-card {
  gap: 10px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.summary-card:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.summary-card__value,
.body-copy {
  margin: 0;
}

.summary-card__value {
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}

.body-copy {
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.section-copy {
  gap: 10px;
}

.section-copy__kicker {
  color: #e5b849;
}
</style>
