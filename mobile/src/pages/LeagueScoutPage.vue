<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { BarChart3, Brain, CircleDot, Flame, Handshake, Shield, Star, Tag, Target, Zap, type LucideIcon } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchLeagueScout, type LeagueScoutPayload, updateLeagueScoutPlayer } from '@/services/league'

type ScoutTip = { icon: LucideIcon; title: string; body_html: string; tag: string; tone: 'manual' | 'auto' | 'hybrid' }
const payload = ref<LeagueScoutPayload | null>(null)
const isLoading = ref(false)
const searchTerm = ref('')
const tip = ref<ScoutTip | null>(null)
const filteredPlayers = computed(() => !searchTerm.value.trim() ? payload.value?.scout.players ?? [] : (payload.value?.scout.players ?? []).filter((row) => row.player.name.toLowerCase().includes(searchTerm.value.trim().toLowerCase())))

const tipMap: Record<string, ScoutTip> = {
  rating: { icon: Star, title: 'Rating General', body_html: 'El rating evoluciona con la experiencia del jugador:<br><br><strong>Menos de 5 juegos:</strong> 80% tu percepci&oacute;n / 20% estad&iacute;sticas<br><strong>5-15 juegos:</strong> 60% percepci&oacute;n / 40% estad&iacute;sticas<br><strong>M&aacute;s de 15 juegos:</strong> 40% percepci&oacute;n / 60% estad&iacute;sticas<br><br>As&iacute; el sistema respeta tu conocimiento al inicio pero los n&uacute;meros van tomando control con el tiempo.', tag: 'HIBRIDO - EVOLUCIONA CON JORNADAS', tone: 'hybrid' },
  role: { icon: Tag, title: 'Rol', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema lo ajusta autom&aacute;ticamente al final de cada jornada: si anota 30% m&aacute;s que el promedio &rarr; <strong>Anotador</strong>. Si permite 20% menos puntos que el promedio &rarr; <strong>Defensivo</strong>. Si hace ambos &rarr; <strong>Equilibrado</strong>.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  offensive_consistency: { icon: BarChart3, title: 'Consistencia Ofensiva', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema revisa si gana m&aacute;s del 55% de sus juegos Y anota al menos el 70% del promedio de la liga. Si cumple ambas condiciones &rarr; <strong>Constante</strong>. Si falla en alguna &rarr; <strong>Inconsistente</strong>.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  speed_rating: { icon: Zap, title: 'Velocidad', body_html: '<strong>100% manual</strong> &mdash; Solo t&uacute; puedes cambiar este valor bas&aacute;ndote en lo que ves en cancha. Mide qu&eacute; tan r&aacute;pido se mueve el jugador con y sin el bal&oacute;n, tanto en ataque como en repliegue defensivo.', tag: 'MANUAL', tone: 'manual' },
  dribbling_rating: { icon: CircleDot, title: 'Dribbling', body_html: '<strong>100% manual</strong> &mdash; Solo t&uacute; puedes cambiar este valor. Mide el control del bal&oacute;n: manejo bajo presi&oacute;n, cambios de direcci&oacute;n y habilidad para penetrar sin perder el bal&oacute;n.', tag: 'MANUAL', tone: 'manual' },
  scoring_rating: { icon: Target, title: 'Anotacion', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Cada jornada el sistema compara sus <strong>puntos por juego</strong> contra el promedio de la liga. Si anota consistentemente por encima del promedio la estrella sube. Si anota por debajo baja hasta 1 estrella m&aacute;ximo por jornada.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  team_play_rating: { icon: Handshake, title: 'Juego en Equipo', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Se ajusta seg&uacute;n el <strong>% de victorias</strong> del jugador. Si gana m&aacute;s del 55% de sus juegos y anota al menos el 70% del promedio de la liga, se considera constante y su estrella sube.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  court_knowledge_rating: { icon: Brain, title: 'Conocimiento', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema mide la <strong>diversidad de tiros</strong> usando entrop&iacute;a de Shannon &mdash; qu&eacute; tan balanceado es entre tiros libres (1PT), tiros de campo (2PT) y triples (3PT). Un jugador que solo hace un tipo obtiene puntuaci&oacute;n baja.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  defense_rating: { icon: Shield, title: 'Defensa', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema calcula los <strong>puntos permitidos por juego</strong> cuando este jugador est&aacute; en cancha. Si su equipo recibe menos puntos que el promedio de la liga su estrella sube. Los juegos son hasta 16 o 21 puntos, as&iacute; que el contexto importa.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  triples_rating: { icon: Flame, title: 'Triples', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Se ajusta seg&uacute;n el <strong>porcentaje de triples</strong> del jugador &mdash; cu&aacute;ntos de sus tiros son de 3 puntos comparado con el jugador que m&aacute;s triples anota en la liga. Un especialista de triple obtiene la m&aacute;xima puntuaci&oacute;n.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
}

const ratingFields = [
  { key: 'speed_rating', label: 'Velocidad' },
  { key: 'dribbling_rating', label: 'Dribbling' },
  { key: 'scoring_rating', label: 'Anotacion' },
  { key: 'team_play_rating', label: 'Juego en equipo' },
  { key: 'court_knowledge_rating', label: 'Conocimiento' },
  { key: 'defense_rating', label: 'Defensa' },
  { key: 'triples_rating', label: 'Triples' },
] as const

async function loadPage(): Promise<void> { isLoading.value = true; try { payload.value = await fetchLeagueScout() } finally { isLoading.value = false } }
async function handleRefresh(event: CustomEvent): Promise<void> { try { await loadPage() } finally { await (event.target as HTMLIonRefresherElement).complete() } }
onIonViewWillEnter(loadPage)

async function patchPlayer(row: LeagueScoutPayload['scout']['players'][number], updates: Partial<LeagueScoutPayload['scout']['players'][number]['profile']>): Promise<void> {
  if (!payload.value?.role.can_manage) return
  payload.value = await updateLeagueScoutPlayer(row.player.id, {
    position: updates.position ?? row.profile.position ?? null,
    role: updates.role ?? row.profile.role ?? null,
    offensive_consistency: updates.offensive_consistency ?? row.profile.offensive_consistency ?? null,
    speed_rating: updates.speed_rating ?? row.profile.speed_rating,
    dribbling_rating: updates.dribbling_rating ?? row.profile.dribbling_rating,
    scoring_rating: updates.scoring_rating ?? row.profile.scoring_rating,
    team_play_rating: updates.team_play_rating ?? row.profile.team_play_rating,
    court_knowledge_rating: updates.court_knowledge_rating ?? row.profile.court_knowledge_rating,
    defense_rating: updates.defense_rating ?? row.profile.defense_rating,
    triples_rating: updates.triples_rating ?? row.profile.triples_rating,
  })
}

async function toggleChoice(row: LeagueScoutPayload['scout']['players'][number], key: 'position' | 'role' | 'offensive_consistency', value: string): Promise<void> {
  await patchPlayer(row, { [key]: row.profile[key] === value ? null : value } as Partial<typeof row.profile>)
}
async function toggleRating(row: LeagueScoutPayload['scout']['players'][number], key: (typeof ratingFields)[number]['key'], value: number): Promise<void> {
  await patchPlayer(row, { [key]: row.profile[key] === value ? 0 : value } as Partial<typeof row.profile>)
}
function statValue(row: LeagueScoutPayload['scout']['players'][number], key: (typeof ratingFields)[number]['key']): number | null {
  if (!row.stat_rating) return null
  if (key === 'scoring_rating') return row.stat_rating.scoring
  if (key === 'team_play_rating') return row.stat_rating.victories
  if (key === 'court_knowledge_rating') return row.stat_rating.diversity
  if (key === 'defense_rating') return row.stat_rating.defense
  if (key === 'triples_rating') return row.stat_rating.triples
  return null
}
function statDetail(row: LeagueScoutPayload['scout']['players'][number], key: (typeof ratingFields)[number]['key']): string | null {
  if (!row.stat_rating) return null
  if (key === 'scoring_rating') return `${row.stat_rating.detail.points_per_game.toFixed(1)} pts/j`
  if (key === 'team_play_rating') return `${row.stat_rating.detail.win_rate}% victorias`
  if (key === 'court_knowledge_rating') return `${row.stat_rating.detail.diversity}% balance`
  if (key === 'defense_rating') return row.stat_rating.detail.points_allowed_per_game === null ? 'Sin defensa' : `${row.stat_rating.detail.points_allowed_per_game.toFixed(1)} pts recibidos`
  if (key === 'triples_rating') return `${row.stat_rating.detail.triple_rate}% triples`
  return null
}
function tipToneClass(tone: ScoutTip['tone']): string { return tone === 'manual' ? 'member-chip member-chip--manual' : tone === 'hybrid' ? 'member-chip member-chip--positive' : 'member-chip member-chip--warning' }
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh"><IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" /></IonRefresher>
      <div class="mobile-shell"><div class="mobile-stack">
        <MobileAppTopbar :title="payload?.league.name ?? 'Scout'" description="Rating hibrido, ranking y auto balance del draft." />

        <section class="app-surface section-stack">
          <div class="section-head"><div><p class="app-kicker section-kicker">Resumen scout</p><p class="section-title">{{ payload?.scout.summary.profiled_players ?? 0 }}/{{ payload?.scout.summary.total_players ?? 0 }} perfilados</p></div><span class="member-chip member-chip--neutral">{{ payload?.scout.summary.auto_preview_pool_count ?? 0 }} pool</span></div>
          <p class="body-copy">{{ payload?.scout.summary.auto_preview_ready ? 'La vista previa del auto balance ya esta disponible.' : 'Se necesitan 10 jugadores en pool para previsualizar el auto balance.' }}</p>
        </section>

        <section class="app-surface section-stack">
          <div class="section-head"><p class="app-kicker section-kicker">Ranking</p><button class="member-chip member-chip--neutral" type="button" @click="tip = tipMap.rating">?</button></div>
          <p v-if="isLoading && !payload" class="body-copy">Cargando scout...</p>
          <article v-for="(row, index) in payload?.scout.ranking.slice(0, 8) ?? []" :key="row.player.id" class="data-row"><div><p class="data-row__name">{{ ['#1','#2','#3'][index] ?? `#${index + 1}` }} / {{ row.player.name }}</p><p class="body-copy">{{ row.profile.position || 'Sin posicion' }} / {{ row.profile.role || 'Sin rol' }}</p></div><span class="member-chip member-chip--warning">{{ row.combined_rating.toFixed(1) }}</span></article>
        </section>

        <section class="app-surface section-stack">
          <div class="section-head"><p class="app-kicker section-kicker">Auto balance</p><button class="member-chip member-chip--neutral" type="button" @click="tip = tipMap.rating">?</button></div>
          <p v-if="!payload?.scout.auto_preview" class="body-copy">Aun no hay un pool inicial listo para simular el reparto automatico.</p>
          <div v-else class="summary-grid summary-grid--two">
            <article class="summary-card"><p class="app-kicker">Equipo A</p><p class="summary-card__value summary-card__value--positive">{{ payload.scout.auto_preview.team_a_rating.toFixed(1) }}</p><p class="body-copy">{{ payload.scout.auto_preview.team_a.map((player) => player.name).join(', ') }}</p></article>
            <article class="summary-card"><p class="app-kicker">Equipo B</p><p class="summary-card__value summary-card__value--warning">{{ payload.scout.auto_preview.team_b_rating.toFixed(1) }}</p><p class="body-copy">{{ payload.scout.auto_preview.team_b.map((player) => player.name).join(', ') }}</p></article>
          </div>
        </section>

        <section class="app-surface section-stack">
          <div class="section-head"><div><p class="app-kicker section-kicker">Perfiles individuales</p><p class="body-copy">Los miembros solo ven; los admins actualizan.</p></div></div>
          <input v-model="searchTerm" type="text" class="sheet-input" placeholder="Buscar jugador" />
          <article v-for="row in filteredPlayers" :key="row.player.id" class="player-card">
            <div class="section-head"><div><p class="data-row__name">{{ row.player.name }}</p><p class="body-copy">Rating {{ row.combined_rating.toFixed(1) }} / Manual {{ row.manual_rating.toFixed(1) }} / Stats {{ row.stat_rating?.overall.toFixed(1) ?? 'n/a' }}</p></div><span class="member-chip" :class="row.has_stats ? 'member-chip--warning' : 'member-chip--neutral'">{{ row.has_stats ? 'manual + stats' : 'solo manual' }}</span></div>
            <p class="body-copy" v-if="row.season_stats">{{ row.season_stats.games }} juegos / {{ row.season_stats.points }} puntos / {{ row.season_stats.win_rate }}% victorias / {{ row.season_stats.sessions_attended }} jornadas</p>

            <div v-if="row.stat_rating" class="summary-grid summary-grid--five">
              <article class="summary-card"><p class="app-kicker">Anotacion</p><p class="summary-card__value summary-card__value--positive">{{ row.stat_rating.scoring.toFixed(1) }}</p><p class="body-copy">{{ row.stat_rating.detail.points_per_game.toFixed(1) }} pts/j</p></article>
              <article class="summary-card"><p class="app-kicker">Equipo</p><p class="summary-card__value summary-card__value--warning">{{ row.stat_rating.victories.toFixed(1) }}</p><p class="body-copy">{{ row.stat_rating.detail.win_rate }}% victorias</p></article>
              <article class="summary-card"><p class="app-kicker">Diversidad</p><p class="summary-card__value">{{ row.stat_rating.diversity.toFixed(1) }}</p><p class="body-copy">{{ row.stat_rating.detail.diversity }}% balance</p></article>
              <article class="summary-card"><p class="app-kicker">Defensa</p><p class="summary-card__value summary-card__value--negative">{{ row.stat_rating.defense.toFixed(1) }}</p><p class="body-copy">{{ row.stat_rating.detail.points_allowed_per_game === null ? 'Sin defensa' : `${row.stat_rating.detail.points_allowed_per_game.toFixed(1)} pts recibidos` }}</p></article>
              <article class="summary-card"><p class="app-kicker">Triples</p><p class="summary-card__value summary-card__value--info">{{ row.stat_rating.triples.toFixed(1) }}</p><p class="body-copy">{{ row.stat_rating.detail.triple_rate }}% triples</p></article>
            </div>

            <div class="button-group"><button v-for="option in payload?.scout.meta.positions ?? []" :key="`pos-${row.player.id}-${option}`" class="member-chip" :class="row.profile.position === option ? 'member-chip--warning' : 'member-chip--neutral'" type="button" :disabled="!payload?.role.can_manage" @click="toggleChoice(row, 'position', option)">{{ option }}</button></div>
            <div class="section-head"><p class="app-kicker">Rol</p><button class="member-chip member-chip--neutral" type="button" @click="tip = tipMap.role">?</button></div>
            <div class="button-group"><button v-for="option in payload?.scout.meta.roles ?? []" :key="`role-${row.player.id}-${option}`" class="member-chip" :class="row.profile.role === option ? 'member-chip--positive' : 'member-chip--neutral'" type="button" :disabled="!payload?.role.can_manage" @click="toggleChoice(row, 'role', option)">{{ option }}</button></div>
            <div class="section-head"><p class="app-kicker">Consistencia</p><button class="member-chip member-chip--neutral" type="button" @click="tip = tipMap.offensive_consistency">?</button></div>
            <div class="button-group"><button v-for="option in payload?.scout.meta.consistencies ?? []" :key="`cons-${row.player.id}-${option}`" class="member-chip" :class="row.profile.offensive_consistency === option ? 'member-chip--negative' : 'member-chip--neutral'" type="button" :disabled="!payload?.role.can_manage" @click="toggleChoice(row, 'offensive_consistency', option)">{{ option }}</button></div>

            <div v-for="field in ratingFields" :key="`${row.player.id}-${field.key}`" class="rating-block">
              <div class="section-head"><p class="app-kicker">{{ field.label }}</p><button class="member-chip member-chip--neutral" type="button" @click="tip = tipMap[field.key]">?</button></div>
              <div class="button-group"><button v-for="star in 5" :key="`${field.key}-${star}`" class="member-chip" :class="row.profile[field.key] >= star ? 'member-chip--warning' : 'member-chip--neutral'" type="button" :disabled="!payload?.role.can_manage" @click="toggleRating(row, field.key, star)">{{ star }}</button></div>
              <p v-if="statValue(row, field.key) !== null" class="body-copy">Base estadistica {{ statValue(row, field.key)?.toFixed(1) }} / {{ statDetail(row, field.key) }}</p>
            </div>
          </article>
        </section>
      </div></div>

      <div v-if="tip !== null" class="overlay" @click.self="tip = null">
        <section class="overlay__panel">
          <div class="overlay__head"><div class="overlay__icon"><component :is="tip?.icon" class="overlay__icon-svg" /></div><p class="app-kicker overlay__kicker">{{ tip?.title }}</p></div>
          <div class="body-copy overlay__body" v-html="tip?.body_html" />
          <div class="overlay__actions"><span :class="tip ? tipToneClass(tip.tone) : 'member-chip member-chip--neutral'">{{ tip?.tag }}</span><button class="action-button action-button--secondary" type="button" @click="tip = null">Entendido</button></div>
        </section>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.summary-card,.player-card,.overlay__panel,.overlay__actions,.rating-block{display:flex;flex-direction:column}
.section-stack,.player-card,.overlay__panel,.overlay__actions,.rating-block{gap:12px}
.section-head,.data-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.overlay__head{display:flex;align-items:center;gap:12px}
.summary-grid{display:grid;gap:12px}
.summary-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}
.summary-grid--five{grid-template-columns:repeat(2,minmax(0,1fr))}
.summary-card,.data-row,.player-card{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.summary-card__value,.body-copy,.data-row__name,.section-title{margin:0}
.summary-card__value,.data-row__name,.section-title{font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.data-row__name,.section-title{font-size:15px}
.summary-card__value--positive{color:#4ade80}
.summary-card__value--warning{color:#e5b849}
.summary-card__value--negative{color:#f87171}
.summary-card__value--info{color:#38bdf8}
.body-copy{font-size:13px;line-height:1.6;color:#94a3b8}
.section-kicker,.overlay__kicker{color:#e5b849}
.sheet-input{min-height:48px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0e1628;padding:0 14px;color:#f8fafc}
.button-group{display:flex;flex-wrap:wrap;gap:8px}
.member-chip,.action-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);padding:0 12px;font-size:12px;font-weight:700}
.action-button{width:100%}
.member-chip--neutral,.action-button--secondary{background:#131b2f;color:#f8fafc}
.member-chip--manual{background:rgba(167,139,250,.16);border-color:rgba(167,139,250,.3);color:#c4b5fd}
.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
.member-chip--negative{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
.overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(3,7,18,.72);padding:16px}
.overlay__panel{width:min(100%,480px);border:1px solid rgba(255,255,255,.06);border-radius:28px 28px 20px 20px;background:#1a243a;padding:18px 16px 20px}
.overlay__icon{display:inline-flex;height:44px;width:44px;align-items:center;justify-content:center;border-radius:999px;border:1px solid rgba(229,184,73,.24);background:rgba(229,184,73,.12);color:#e5b849}
.overlay__icon-svg{height:20px;width:20px}
.overlay__body{margin:0;line-height:1.7}
.overlay__body :deep(strong){color:#f8fafc}
@media (min-width: 480px){.summary-grid--five{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
