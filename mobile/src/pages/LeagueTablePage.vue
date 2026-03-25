<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueTable, type LeagueTablePayload } from '@/services/league'

const payload = ref<LeagueTablePayload | null>(null)
const isLoading = ref(false)

async function loadPage(): Promise<void> {
  isLoading.value = true
  try {
    payload.value = await fetchLeagueTable()
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
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="payload?.league.name ?? 'Tabla'" description="Lideres de la jornada por victorias, puntos y volumen de juego." />

          <section class="summary-grid">
            <article class="app-surface summary-card">
              <p class="app-kicker">Juegos</p>
              <p class="summary-card__value">{{ payload?.table.banner.games ?? 0 }}</p>
            </article>
            <article class="app-surface summary-card">
              <p class="app-kicker">Puntos</p>
              <p class="summary-card__value summary-card__value--warning">{{ payload?.table.banner.points ?? 0 }}</p>
            </article>
            <article class="app-surface summary-card">
              <p class="app-kicker">Jugadores</p>
              <p class="summary-card__value">{{ payload?.table.banner.players ?? 0 }}</p>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Tabla general</p>
            <p v-if="isLoading && !payload" class="body-copy">Cargando tabla...</p>
            <p v-else-if="(payload?.table.standings.length ?? 0) === 0" class="body-copy">Sin juegos terminados todavia.</p>
            <article v-for="(row, index) in payload?.table.standings ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">#{{ index + 1 }} · {{ row.identity.name }}</p>
                <p class="body-copy">{{ row.games }} juegos · {{ row.wins }}V - {{ row.losses }}D</p>
              </div>
              <span class="member-chip member-chip--positive">{{ row.win_rate }}%</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Top anotadores</p>
            <article v-for="(row, index) in payload?.table.top_scorers ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.points_per_game }} pts por juego</p>
              </div>
              <span class="member-chip member-chip--warning">{{ row.points }} pts</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Mas usados</p>
            <article v-for="(row, index) in payload?.table.top_games ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.wins }}V - {{ row.losses }}D</p>
              </div>
              <span class="member-chip member-chip--neutral">{{ row.games }} juegos</span>
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
.summary-grid{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
.summary-card,.data-row{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.data-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.summary-card__value,.body-copy,.data-row__name{margin:0}
.summary-card__value,.data-row__name{font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.data-row__name{font-size:15px}
.summary-card__value--warning{color:#e5b849}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.section-kicker{color:#e5b849}
.member-chip{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.member-chip--neutral{background:#131b2f;color:#f8fafc}
.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
</style>
