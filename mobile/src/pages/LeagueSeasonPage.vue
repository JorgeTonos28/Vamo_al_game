<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueSeason  } from '@/services/league'
import type {LeagueSeasonPayload} from '@/services/league';

const payload = ref<LeagueSeasonPayload | null>(null)
const isLoading = ref(false)

function money(amountCents: number): string {
  return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(amountCents / 100)
}

function compactDate(value: string | null | undefined): string {
  if (!value) {
return 'Sin fecha'
}

  return new Intl.DateTimeFormat('es-DO', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
}

async function loadPage(): Promise<void> {
  isLoading.value = true

  try {
    payload.value = await fetchLeagueSeason()
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
      <template v-slot:fixed>
<IonRefresher  @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>
</template>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="payload?.league.name ?? 'Temporada'" :description="payload?.season.season.label ?? 'Resumen acumulado de jornadas y líderes.'" />

          <section class="summary-grid">
            <article class="app-surface summary-card">
              <p class="app-kicker">Jornadas</p>
              <p class="summary-card__value">{{ payload?.season.season.sessions_count ?? 0 }}</p>
              <p class="body-copy">Desde {{ compactDate(payload?.season.season.starts_on) }}</p>
            </article>
            <article class="app-surface summary-card">
              <p class="app-kicker">Juegos</p>
              <p class="summary-card__value">{{ payload?.season.season.totals.games ?? 0 }}</p>
            </article>
            <article class="app-surface summary-card">
              <p class="app-kicker">Puntos</p>
              <p class="summary-card__value summary-card__value--warning">{{ payload?.season.season.totals.points ?? 0 }}</p>
            </article>
            <article v-if="payload?.season.season.totals.show_revenue" class="app-surface summary-card">
              <p class="app-kicker">Recaudado</p>
              <p class="summary-card__value summary-card__value--positive">{{ money(payload?.season.season.totals.revenue_cents ?? 0) }}</p>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Líderes en puntos</p>
            <p v-if="isLoading && !payload" class="body-copy">Cargando temporada...</p>
            <article v-for="(row, index) in payload?.season.leaders.points ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.points_per_game }} pts por juego</p>
              </div>
              <span class="member-chip member-chip--warning">{{ row.points }}</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Líderes en victorias</p>
            <article v-for="(row, index) in payload?.season.leaders.wins ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.games }} juegos · {{ row.win_rate }}%</p>
              </div>
              <span class="member-chip member-chip--positive">{{ row.wins }}V</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Jornadas registradas</p>
            <article v-for="sessionRow in payload?.season.sessions ?? []" :key="sessionRow.id" class="data-row">
              <div>
                <p class="data-row__name">{{ compactDate(sessionRow.date) }}</p>
                <p class="body-copy">{{ sessionRow.total_games }} juegos · {{ sessionRow.players }} jugadores · {{ sessionRow.total_points }} pts</p>
              </div>
               <span class="member-chip member-chip--neutral">{{ sessionRow.top_scorer?.name ?? 'Sin líder' }}</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Perfiles acumulados</p>
            <article v-for="(row, index) in payload?.season.profiles ?? []" :key="`${row.identity.name}-${index}`" class="data-row data-row--stack">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.games }} juegos · {{ row.wins }}V - {{ row.losses }}D · {{ row.sessions_attended }} jornadas</p>
                <p class="body-copy">1P {{ row.shots[1] }} · 2P {{ row.shots[2] }} · 3P {{ row.shots[3] }} · {{ row.points_per_game }} pts/juego</p>
              </div>
              <span class="member-chip member-chip--warning">{{ row.points }} pts</span>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.summary-card{display:flex;flex-direction:column}
.section-stack{gap:12px}
.summary-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
.summary-card,.data-row{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.data-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.data-row--stack{align-items:flex-start}
.summary-card__value,.body-copy,.data-row__name{margin:0}
.summary-card__value,.data-row__name{font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.data-row__name{font-size:15px}
.summary-card__value--warning{color:#e5b849}
.summary-card__value--positive{color:#4ade80}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.section-kicker{color:#e5b849}
.member-chip{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.member-chip--neutral{background:#131b2f;color:#f8fafc}
.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
</style>
