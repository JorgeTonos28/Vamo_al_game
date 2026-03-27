<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueStats, type LeagueStatsPayload } from '@/services/league'

const payload = ref<LeagueStatsPayload | null>(null)
const isLoading = ref(false)

async function loadPage(): Promise<void> {
  isLoading.value = true
  try {
    payload.value = await fetchLeagueStats()
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

async function changeSession(event: Event): Promise<void> {
  const target = event.target as HTMLSelectElement
  const sessionId = Number(target.value)

  if (!Number.isFinite(sessionId) || sessionId <= 0) {
    return
  }

  isLoading.value = true
  try {
    payload.value = await fetchLeagueStats(sessionId)
  } finally {
    isLoading.value = false
  }
}

function sessionLabel(session: LeagueStatsPayload['session_selector']['sessions'][number]): string {
  const base = session.session_date ?? 'Sin fecha'
  const suffix = session.is_current ? 'actual' : session.status === 'completed' ? 'cerrada' : 'abierta'

  return `${base} · ${suffix}`
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="payload?.league.name ?? 'Stats'" description="Puntos y juegos completados en la jornada actual." />

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Estadisticas de jornada</p>
            <p class="body-copy">Puedes revisar la jornada actual o consultar dias anteriores.</p>
            <select class="sheet-input" :value="payload?.session_selector.selected_session_id" @change="changeSession">
              <option v-for="session in payload?.session_selector.sessions ?? []" :key="session.id" :value="session.id">
                {{ sessionLabel(session) }}
              </option>
            </select>
            <span class="member-chip member-chip--neutral">{{ payload?.stats.games_count ?? 0 }} juegos</span>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Puntos anotados</p>
            <p v-if="isLoading && !payload" class="body-copy">Cargando stats...</p>
            <p v-else-if="(payload?.stats.points_leaders.length ?? 0) === 0" class="body-copy">Sin datos todavia.</p>
            <article v-for="(row, index) in payload?.stats.points_leaders ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.games }} juegos · 1P {{ row.shots[1] }} · 2P {{ row.shots[2] }} · 3P {{ row.shots[3] }}</p>
              </div>
              <span class="member-chip member-chip--warning">{{ row.points }} pts</span>
            </article>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Juegos jugados</p>
            <p v-if="(payload?.stats.games_leaders.length ?? 0) === 0" class="body-copy">Sin datos todavia.</p>
            <article v-for="(row, index) in payload?.stats.games_leaders ?? []" :key="`${row.identity.name}-${index}`" class="data-row">
              <div>
                <p class="data-row__name">{{ row.identity.name }}</p>
                <p class="body-copy">{{ row.wins }}V - {{ row.losses }}D</p>
              </div>
              <span class="member-chip member-chip--positive">{{ row.games }} juegos</span>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack{display:flex;flex-direction:column;gap:12px}
.data-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.section-kicker{color:#e5b849}
.body-copy,.data-row__name{margin:0}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.data-row__name{font-size:15px;font-weight:700;color:#f8fafc}
.sheet-input{width:100%;min-height:48px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0e1628;padding:0 14px;color:#f8fafc}
.member-chip{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.member-chip--neutral{background:#131b2f;color:#f8fafc}
.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
</style>
