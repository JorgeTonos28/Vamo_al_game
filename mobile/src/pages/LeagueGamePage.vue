<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { computed, reactive, ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import {
  addLeaguePlayerPoint,
  addLeagueTeamPoint,
  draftLeagueGame,
  endLeagueSession,
  fetchLeagueGame,
  finishLeagueGame,
  removeLeagueGamePlayer,
  resetLeagueGame,
  revertLeaguePlayerPoint,
  type LeagueGamePayload,
  type LeagueTeamPlayer,
  undoLeagueGameAction,
} from '@/services/league'

type TeamSide = 'A' | 'B'

type ScoreFlashState = {
  key: number
  label: string
  side: TeamSide
}

const payload = ref<LeagueGamePayload | null>(null)
const isLoading = ref(false)
const draftMode = ref<'auto' | 'arrival' | 'manual'>('auto')
const manualAssignments = reactive<Record<number, 'A' | 'B'>>({})
const selectedPlayer = ref<LeagueTeamPlayer | null>(null)
const revertPlayer = ref<LeagueTeamPlayer | null>(null)
const playerToRemove = ref<LeagueTeamPlayer | null>(null)
const finishOpen = ref(false)
const scoreFlash = ref<ScoreFlashState | null>(null)
const scoreBumpSide = ref<TeamSide | null>(null)

const canManage = computed(() => payload.value?.role.can_manage ?? false)
const teamACount = computed(() => Object.values(manualAssignments).filter((team) => team === 'A').length)
const teamBCount = computed(() => Object.values(manualAssignments).filter((team) => team === 'B').length)
const streakLabel = computed(() => {
  const streak = payload.value?.game.current?.streak
  return streak?.team ? `EQ.${streak.team} - ${streak.count}` : 'Sin racha'
})
let scoreFeedbackNonce = 0
let scoreFlashTimer: ReturnType<typeof setTimeout> | null = null
let scoreBumpTimer: ReturnType<typeof setTimeout> | null = null

function teamSideForPlayer(entryId: number): TeamSide | null {
  if (payload.value?.game.current?.team_a.some((player) => player.id === entryId)) return 'A'
  if (payload.value?.game.current?.team_b.some((player) => player.id === entryId)) return 'B'
  return null
}

function triggerScoreFeedback(teamSide: TeamSide, points: number): void {
  scoreFeedbackNonce += 1
  scoreFlash.value = { key: scoreFeedbackNonce, label: `+${points}`, side: teamSide }
  scoreBumpSide.value = teamSide

  if (scoreFlashTimer !== null) clearTimeout(scoreFlashTimer)
  if (scoreBumpTimer !== null) clearTimeout(scoreBumpTimer)

  scoreFlashTimer = setTimeout(() => {
    scoreFlash.value = null
  }, 520)

  scoreBumpTimer = setTimeout(() => {
    scoreBumpSide.value = null
  }, 180)
}

async function loadPage(): Promise<void> {
  isLoading.value = true
  try {
    payload.value = await fetchLeagueGame()
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

function setAssignment(entryId: number, team: 'A' | 'B'): void {
  manualAssignments[entryId] = team
}

async function submitDraft(): Promise<void> {
  if (!canManage.value) return
  payload.value = await draftLeagueGame(
    draftMode.value === 'manual'
      ? { mode: draftMode.value, assignments: manualAssignments }
      : { mode: draftMode.value },
  )
}

async function addTeamPoint(teamSide: TeamSide): Promise<void> {
  if (!canManage.value) return
  payload.value = await addLeagueTeamPoint(teamSide)
  triggerScoreFeedback(teamSide, 1)
}

async function addPlayerPoint(points: 1 | 2 | 3): Promise<void> {
  if (!selectedPlayer.value || !canManage.value) return
  const teamSide = teamSideForPlayer(selectedPlayer.value.id)
  payload.value = await addLeaguePlayerPoint(selectedPlayer.value.id, points)
  if (teamSide !== null) triggerScoreFeedback(teamSide, points)
  selectedPlayer.value = null
}

async function revertPlayerPoint(points: 1 | 2 | 3): Promise<void> {
  if (!revertPlayer.value || !canManage.value) return
  payload.value = await revertLeaguePlayerPoint(revertPlayer.value.id, points)
  revertPlayer.value = null
}

function openRemovePlayerModal(player: LeagueTeamPlayer): void {
  if (!canManage.value) return
  playerToRemove.value = player
}

async function confirmRemovePlayer(): Promise<void> {
  if (!canManage.value || !playerToRemove.value) return
  payload.value = await removeLeagueGamePlayer(playerToRemove.value.id)
  playerToRemove.value = null
}

async function undoAction(): Promise<void> {
  if (!canManage.value) return
  payload.value = await undoLeagueGameAction()
}

async function finishGame(winnerSide?: 'A' | 'B'): Promise<void> {
  if (!canManage.value) return
  payload.value = await finishLeagueGame(winnerSide)
  finishOpen.value = false
}

async function endSession(): Promise<void> {
  if (!canManage.value || !window.confirm('Cerrar la jornada del dia?')) return
  payload.value = await endLeagueSession()
}

async function resetGame(): Promise<void> {
  if (!canManage.value || !window.confirm('Limpiar por completo el juego actual?')) return
  payload.value = await resetLeagueGame()
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
          <MobileAppTopbar :title="payload?.league.name ?? 'Juego'" description="Draft, marcador, historial y cierre de la jornada." />

          <section class="app-surface section-stack">
            <div class="section-head">
              <div>
                <p class="app-kicker section-kicker">Juego actual</p>
                <p class="section-title">Jornada en cancha</p>
              </div>
              <div class="summary-pills">
                <span class="member-chip member-chip--warning">{{ payload?.game.summary.games ?? 0 }} juegos</span>
                <span class="member-chip member-chip--positive">{{ streakLabel }}</span>
              </div>
            </div>
            <p class="body-copy">Administra el draft inicial, la anotacion, las salidas y el cierre de cada juego.</p>
          </section>

          <section v-if="isLoading && !payload" class="app-surface section-stack">
            <p class="body-copy">Cargando juego...</p>
          </section>

          <template v-else-if="payload?.game.state === 'draft'">
            <section class="app-surface section-stack">
              <div class="section-head">
                <div>
                  <p class="app-kicker section-kicker">Draft pendiente</p>
                  <p class="section-title">{{ payload?.game.draft.entries.length ?? 0 }} listos</p>
                </div>
              </div>

              <div class="action-grid action-grid--three">
                <button class="action-button" :class="draftMode === 'auto' ? 'action-button--primary' : 'action-button--secondary'" type="button" @click="draftMode = 'auto'">Auto</button>
                <button class="action-button" :class="draftMode === 'arrival' ? 'action-button--warning' : 'action-button--secondary'" type="button" @click="draftMode = 'arrival'">Llegada</button>
                <button class="action-button" :class="draftMode === 'manual' ? 'action-button--danger' : 'action-button--secondary'" type="button" @click="draftMode = 'manual'">Manual</button>
              </div>

              <article v-for="entry in payload?.game.draft.entries ?? []" :key="entry.id" class="data-row">
                <div>
                  <p class="data-row__name">{{ entry.name }}</p>
                  <p class="body-copy">Llegada #{{ entry.arrival_order }}<span v-if="entry.jersey_number !== null"> · #{{ entry.jersey_number }}</span></p>
                </div>
                <div v-if="draftMode === 'manual'" class="draft-actions">
                  <button class="member-chip" :class="manualAssignments[entry.id] === 'A' ? 'member-chip--positive' : 'member-chip--neutral'" type="button" @click="setAssignment(entry.id, 'A')">A</button>
                  <button class="member-chip" :class="manualAssignments[entry.id] === 'B' ? 'member-chip--warning' : 'member-chip--neutral'" type="button" @click="setAssignment(entry.id, 'B')">B</button>
                </div>
              </article>

              <div v-if="draftMode === 'manual'" class="summary-grid summary-grid--two">
                <article class="summary-card">
                  <p class="app-kicker">Equipo A</p>
                  <p class="summary-card__value">{{ teamACount }}/5</p>
                </article>
                <article class="summary-card">
                  <p class="app-kicker">Equipo B</p>
                  <p class="summary-card__value">{{ teamBCount }}/5</p>
                </article>
              </div>

              <button class="action-button action-button--warning" type="button" :disabled="draftMode === 'manual' && (teamACount !== 5 || teamBCount !== 5)" @click="submitDraft">Confirmar draft</button>
            </section>
          </template>

          <template v-else-if="payload?.game.current">
            <section class="app-surface section-stack">
              <div class="scoreboard">
                <div class="scoreboard__team">
                  <p class="app-kicker">Equipo A</p>
                  <p class="scoreboard__value scoreboard__value--green" :class="{ 'scoreboard__value--bump': scoreBumpSide === 'A' }">{{ payload.game.current.score.team_a }}</p>
                </div>
                <div class="scoreboard__center">
                  <span class="scoreboard__badge">Juego #{{ payload.game.current.game_number }}</span>
                  <div class="scoreboard__vs">Vs</div>
                  <p class="scoreboard__streak">{{ streakLabel }}</p>
                </div>
                <div class="scoreboard__team">
                  <p class="app-kicker">Equipo B</p>
                  <p class="scoreboard__value scoreboard__value--gold" :class="{ 'scoreboard__value--bump': scoreBumpSide === 'B' }">{{ payload.game.current.score.team_b }}</p>
                </div>
              </div>
            </section>

            <div v-if="scoreFlash" :key="scoreFlash.key" class="score-flash" :class="scoreFlash.side === 'A' ? 'score-flash--a' : 'score-flash--b'">
              {{ scoreFlash.label }}
            </div>

            <section class="team-card team-card--a">
              <div class="section-head">
                <div>
                  <p class="app-kicker section-kicker team-a-copy">Equipo A</p>
                  <p class="body-copy">Jugadores en cancha</p>
                </div>
                <button v-if="canManage" class="member-chip member-chip--positive" type="button" @click="addTeamPoint('A')">+1 equipo</button>
              </div>
              <article v-for="player in payload.game.current.team_a" :key="player.id" class="data-row">
                <div>
                  <p class="data-row__name">{{ player.name }}</p>
                  <p class="body-copy">{{ player.points }} pts · 1P {{ player.shots[1] }} · 2P {{ player.shots[2] }} · 3P {{ player.shots[3] }}</p>
                </div>
                <div class="player-actions">
                  <button v-if="canManage" class="member-chip member-chip--positive" type="button" @click="selectedPlayer = player">+</button>
                  <button v-if="canManage && player.points > 0" class="member-chip member-chip--warning" type="button" @click="revertPlayer = player">Revertir</button>
                  <button v-if="canManage" class="member-chip member-chip--negative" type="button" @click="openRemovePlayerModal(player)">Salida</button>
                </div>
              </article>
            </section>

            <section class="team-card team-card--b">
              <div class="section-head">
                <div>
                  <p class="app-kicker section-kicker team-b-copy">Equipo B</p>
                  <p class="body-copy">Jugadores en cancha</p>
                </div>
                <button v-if="canManage" class="member-chip member-chip--warning" type="button" @click="addTeamPoint('B')">+1 equipo</button>
              </div>
              <article v-for="player in payload.game.current.team_b" :key="player.id" class="data-row">
                <div>
                  <p class="data-row__name">{{ player.name }}</p>
                  <p class="body-copy">{{ player.points }} pts · 1P {{ player.shots[1] }} · 2P {{ player.shots[2] }} · 3P {{ player.shots[3] }}</p>
                </div>
                <div class="player-actions">
                  <button v-if="canManage" class="member-chip member-chip--warning" type="button" @click="selectedPlayer = player">+</button>
                  <button v-if="canManage && player.points > 0" class="member-chip member-chip--warning" type="button" @click="revertPlayer = player">Revertir</button>
                  <button v-if="canManage" class="member-chip member-chip--negative" type="button" @click="openRemovePlayerModal(player)">Salida</button>
                </div>
              </article>
            </section>

            <section class="app-surface section-stack">
              <p class="app-kicker section-kicker">Acciones</p>
              <div class="action-grid">
                <button v-if="canManage" class="action-button action-button--warning" type="button" @click="finishOpen = true">Marcar fin de juego</button>
                <button v-if="canManage" class="action-button action-button--secondary" type="button" @click="undoAction">Deshacer ultima accion</button>
                <button v-if="canManage" class="action-button action-button--danger" type="button" @click="resetGame">Limpiar juego actual</button>
                <button v-if="canManage" class="action-button action-button--primary" type="button" @click="endSession">Dar fin a la jornada</button>
              </div>
            </section>

            <section class="app-surface section-stack">
              <p class="app-kicker section-kicker">Historial</p>
              <p v-if="payload.game.history.length === 0" class="body-copy">Sin juegos finalizados todavia.</p>
              <article v-for="row in payload.game.history" :key="row.id" class="data-row">
                <div>
                  <p class="data-row__name">Juego #{{ row.game_number }}</p>
                  <p class="body-copy">{{ row.summary }}</p>
                </div>
                <span class="member-chip member-chip--neutral">{{ row.score }}</span>
              </article>
            </section>
          </template>

          <section v-else class="app-surface section-stack">
            <p class="app-kicker section-kicker">Sin juego activo</p>
            <p class="body-copy">Prepara la jornada desde Llegada o confirma el draft pendiente para abrir el primer juego.</p>
          </section>
        </div>
      </div>

      <div v-if="selectedPlayer !== null" class="overlay" @click.self="selectedPlayer = null">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">{{ selectedPlayer?.name }}</p>
          <p class="body-copy">Selecciona cuantos puntos quieres agregarle a este jugador.</p>
          <div class="action-grid action-grid--three">
            <button class="action-button action-button--primary" type="button" @click="addPlayerPoint(1)">+1</button>
            <button class="action-button action-button--warning" type="button" @click="addPlayerPoint(2)">+2</button>
            <button class="action-button action-button--danger" type="button" @click="addPlayerPoint(3)">+3</button>
          </div>
        </section>
      </div>

      <div v-if="revertPlayer !== null" class="overlay" @click.self="revertPlayer = null">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">{{ revertPlayer?.name }}</p>
          <p class="body-copy">Revierte una jugada registrada en el juego actual.</p>
          <div class="action-grid action-grid--three">
            <button class="action-button action-button--secondary" type="button" :disabled="!revertPlayer || revertPlayer.shots[1] < 1" @click="revertPlayerPoint(1)">-1</button>
            <button class="action-button action-button--secondary" type="button" :disabled="!revertPlayer || revertPlayer.shots[2] < 1" @click="revertPlayerPoint(2)">-2</button>
            <button class="action-button action-button--secondary" type="button" :disabled="!revertPlayer || revertPlayer.shots[3] < 1" @click="revertPlayerPoint(3)">-3</button>
          </div>
        </section>
      </div>

      <div v-if="playerToRemove !== null" class="overlay" @click.self="playerToRemove = null">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker overlay__kicker--danger">Jugador se va</p>
          <p class="body-copy">Confirma la salida de {{ playerToRemove?.name }}. Si hay cola activa, el siguiente jugador entrara segun la prioridad de la jornada.</p>
          <div class="action-grid action-grid--two">
            <button class="action-button action-button--danger" type="button" @click="confirmRemovePlayer">Si, se fue</button>
            <button class="action-button action-button--secondary" type="button" @click="playerToRemove = null">Cancelar</button>
          </div>
        </section>
      </div>

      <div v-if="finishOpen" class="overlay" @click.self="finishOpen = false">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">Fin de juego</p>
          <p class="body-copy">Confirma el ganador para aplicar la rotacion de cola y abrir el siguiente juego.</p>
          <div class="action-grid">
            <button class="action-button action-button--primary" type="button" @click="finishGame('A')">Gano A</button>
            <button class="action-button action-button--warning" type="button" @click="finishGame('B')">Gano B</button>
            <button class="action-button action-button--secondary" type="button" @click="finishGame()">Automatico</button>
          </div>
        </section>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.summary-card,.overlay__panel,.summary-pills,.draft-actions,.player-actions{display:flex;flex-direction:column}
.section-stack,.overlay__panel,.summary-pills{gap:12px}
.section-head,.data-row,.scoreboard,.action-grid,.player-actions,.draft-actions{display:flex;gap:12px}
.section-head,.data-row{align-items:center;justify-content:space-between}
.section-kicker,.overlay__kicker,.team-a-copy,.team-b-copy{color:#e5b849}
.team-a-copy{color:#4ade80}
.team-b-copy{color:#e5b849}
.section-title,.body-copy,.data-row__name,.summary-card__value{margin:0}
.section-title,.data-row__name{font-size:16px;font-weight:700;color:#f8fafc}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.summary-pills{align-items:flex-end}
.summary-grid{display:grid;gap:12px}
.summary-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}
.action-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}
.action-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
.summary-card,.data-row,.team-card{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.summary-card__value{margin-top:10px;font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.team-card{display:flex;flex-direction:column;gap:12px}
.team-card--a{border-color:rgba(74,222,128,.18)}
.team-card--b{border-color:rgba(229,184,73,.18)}
.scoreboard{align-items:center;justify-content:space-between;border:1px solid rgba(255,255,255,.06);border-radius:20px;background:radial-gradient(circle at top, rgba(229,184,73,.14), rgba(14,22,40,.98) 48%),linear-gradient(180deg, rgba(19,27,47,.98), rgba(10,15,29,1));padding:14px}
.scoreboard__team,.scoreboard__center{text-align:center}
.scoreboard__team{flex:1}
.scoreboard__center{display:flex;min-width:120px;flex-direction:column;align-items:center;gap:8px}
.scoreboard__badge{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.08);border-radius:999px;background:rgba(19,27,47,.9);padding:8px 12px;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#94a3b8}
.scoreboard__vs{display:flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(255,255,255,.08);border-radius:999px;background:rgba(19,27,47,.9);font-family:'Bebas Neue',sans-serif;font-size:28px;line-height:1;color:#94a3b8}
.scoreboard__streak{margin:0;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#94a3b8}
.scoreboard__value{margin:8px 0 0;font-family:'Bebas Neue',sans-serif;font-size:62px;line-height:1;color:#f8fafc}
.scoreboard__value--green{color:#4ade80}
.scoreboard__value--gold{color:#e5b849}
.scoreboard__value--bump{animation:scoreboard-bump .18s ease-out}
.action-grid{display:grid;gap:12px}
.action-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
.draft-actions,.player-actions{flex-direction:row;flex-wrap:wrap;justify-content:flex-end}
.member-chip,.action-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.action-button{width:100%}
.member-chip--neutral,.action-button--secondary{background:#131b2f;color:#f8fafc}
.member-chip--positive,.action-button--primary{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning,.action-button--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
.member-chip--negative,.action-button--danger{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
.score-flash{position:fixed;top:38%;left:50%;z-index:1001;transform:translate(-50%,-50%);font-family:'Bebas Neue',sans-serif;font-size:72px;line-height:1;pointer-events:none;animation:score-flash .5s forwards}
.score-flash--a{color:#4ade80}
.score-flash--b{color:#e5b849}
.overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(3,7,18,.72);padding:16px}
.overlay__panel{width:min(100%,480px);border:1px solid rgba(255,255,255,.06);border-radius:28px 28px 20px 20px;background:#1a243a;padding:18px 16px 20px}
.overlay__kicker--danger{color:#f87171}
@keyframes score-flash{0%{opacity:.9;transform:translate(-50%,-50%) scale(1)}100%{opacity:0;transform:translate(-50%,-50%) scale(2.4)}}
@keyframes scoreboard-bump{0%,100%{transform:scale(1)}40%{transform:scale(1.18)}}
</style>
