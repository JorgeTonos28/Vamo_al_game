<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueQueue, type LeagueQueuePayload } from '@/services/league'

const payload = ref<LeagueQueuePayload | null>(null)
const isLoading = ref(false)

async function loadPage(): Promise<void> {
  isLoading.value = true
  try {
    payload.value = await fetchLeagueQueue()
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
          <MobileAppTopbar :title="payload?.league.name ?? 'Cola'" description="Vista viva de cancha, espera y rotacion actual." />

          <section class="summary-grid">
            <article class="app-surface summary-card">
              <p class="app-kicker">Juegos</p>
              <p class="summary-card__value">{{ payload?.queue.summary.games ?? 0 }}</p>
              <p class="body-copy">{{ payload?.queue.summary.streak_label ?? 'Sin racha' }}</p>
            </article>
            <article class="app-surface summary-card">
              <p class="app-kicker">Activos hoy</p>
              <p class="summary-card__value">{{ payload?.queue.summary.active_players ?? 0 }}</p>
              <p class="body-copy">{{ payload?.queue.summary.guests ?? 0 }} invitados incluidos</p>
            </article>
          </section>

          <section class="app-surface section-stack">
            <div class="section-head">
              <p class="app-kicker section-kicker">Jugadores en cancha</p>
              <span class="member-chip member-chip--neutral">{{ payload?.queue.on_court.length ?? 0 }}</span>
            </div>
            <p v-if="isLoading && !payload" class="body-copy">Cargando cola...</p>
            <article v-for="player in payload?.queue.on_court ?? []" :key="player.id" class="data-row">
              <div>
                <p class="data-row__name">{{ player.name }}</p>
                <p class="body-copy">{{ player.games_played }} juegos · {{ player.points_scored }} puntos</p>
              </div>
              <span :class="['member-chip', player.team_side === 'A' ? 'member-chip--positive' : 'member-chip--warning']">Eq. {{ player.team_side }}</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <div class="section-head">
              <p class="app-kicker section-kicker">Cola</p>
              <span class="member-chip member-chip--neutral">{{ payload?.queue.waiting.length ?? 0 }}</span>
            </div>
            <p v-if="(payload?.queue.waiting.length ?? 0) === 0" class="body-copy">Cola vacia.</p>
            <article v-for="player in payload?.queue.waiting ?? []" :key="player.id" class="data-row">
              <div>
                <p class="data-row__name">{{ player.name }}</p>
                <p class="body-copy">{{ player.games_played }} juegos · {{ player.points_scored }} puntos</p>
              </div>
              <span class="member-chip member-chip--neutral">#{{ player.position }}</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Resumen</p>
            <p class="body-copy">El modulo Juego sigue controlando el cierre de jornada. Aqui solo ves el estado vivo de cancha y espera.</p>
            <p v-if="payload?.queue.live_game" class="body-copy">Juego actual: #{{ payload.queue.live_game.game_number }} · {{ payload.queue.live_game.score }}</p>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.summary-card{display:flex;flex-direction:column}
.section-stack{gap:12px}
.section-head,.data-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.summary-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
.summary-card,.data-row{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.summary-card__value,.body-copy,.data-row__name{margin:0}
.summary-card__value,.data-row__name{font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.data-row__name{font-size:15px}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.section-kicker{color:#e5b849}
.member-chip{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.member-chip--neutral{background:#131b2f;color:#f8fafc}
.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
</style>
