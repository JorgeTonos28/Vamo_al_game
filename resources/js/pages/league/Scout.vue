<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { BarChart3, BadgeInfo, Brain, CircleDot, Flame, Handshake, Search, Shield, Sparkles, Star, Tag, Target, Zap } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'

type ScoutProfile = { position: string | null; role: string | null; offensive_consistency: string | null; speed_rating: number; dribbling_rating: number; scoring_rating: number; team_play_rating: number; court_knowledge_rating: number; defense_rating: number; triples_rating: number }
type ScoutStatRating = { victories: number; scoring: number; defense: number; triples: number; diversity: number; overall: number; detail: { points_per_game: number; win_rate: number; points_allowed_per_game: number | null; triple_rate: number; diversity: number } }
type ScoutRow = { player: { id: number; name: string; jersey_number: number | null }; profile: ScoutProfile; season_stats: null | { points: number; games: number; wins: number; losses: number; points_per_game: number; win_rate: number; sessions_attended: number }; combined_rating: number; manual_rating: number; stat_rating: ScoutStatRating | null; has_stats: boolean }
type ScoutTip = { icon: typeof BadgeInfo; title: string; body_html: string; tag: string; tone: 'manual' | 'auto' | 'hybrid' }
type RatingField = 'speed_rating' | 'dribbling_rating' | 'scoring_rating' | 'team_play_rating' | 'court_knowledge_rating' | 'defense_rating' | 'triples_rating'

const props = defineProps<{
  league: { id: number; name: string; emoji: string | null; slug: string }
  role: { value: string; label: string; can_manage: boolean }
  scout: {
    meta: { positions: string[]; roles: string[]; consistencies: string[] }
    summary: { profiled_players: number; total_players: number; auto_preview_ready: boolean; auto_preview_pool_count: number }
    players: ScoutRow[]
    ranking: Array<{ player: { id: number; name: string; jersey_number: number | null }; combined_rating: number; profile: ScoutProfile; has_stats: boolean }>
    auto_preview: null | {
      mode: string; source: string; team_a: Array<{ id: number; name: string; is_guest: boolean; jersey_number: number | null; combined_rating: number; role: string | null; position: string | null; offensive_consistency: string | null; has_stats: boolean }>
      team_b: Array<{ id: number; name: string; is_guest: boolean; jersey_number: number | null; combined_rating: number; role: string | null; position: string | null; offensive_consistency: string | null; has_stats: boolean }>
      team_a_rating: number; team_b_rating: number
    }
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Scout', href: '/liga/modulos/scout' }]
const canManage = computed(() => props.role.can_manage)
const searchTerm = ref('')
const openTip = ref<ScoutTip | null>(null)
const rankingMedals = ['#1', '#2', '#3']
const filteredPlayers = computed(() => !searchTerm.value.trim() ? props.scout.players : props.scout.players.filter((row) => row.player.name.toLowerCase().includes(searchTerm.value.trim().toLowerCase())))

const tipMap: Record<string, ScoutTip> = {
  speed_rating: { icon: Zap, title: 'Velocidad', body_html: '<strong>100% manual</strong> &mdash; Solo t&uacute; puedes cambiar este valor bas&aacute;ndote en lo que ves en cancha. Mide qu&eacute; tan r&aacute;pido se mueve el jugador con y sin el bal&oacute;n, tanto en ataque como en repliegue defensivo.', tag: 'MANUAL', tone: 'manual' },
  dribbling_rating: { icon: CircleDot, title: 'Dribbling', body_html: '<strong>100% manual</strong> &mdash; Solo t&uacute; puedes cambiar este valor. Mide el control del bal&oacute;n: manejo bajo presi&oacute;n, cambios de direcci&oacute;n y habilidad para penetrar sin perder el bal&oacute;n.', tag: 'MANUAL', tone: 'manual' },
  scoring_rating: { icon: Target, title: 'Anotacion', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Cada jornada el sistema compara sus <strong>puntos por juego</strong> contra el promedio de la liga. Si anota consistentemente por encima del promedio la estrella sube. Si anota por debajo baja hasta 1 estrella m&aacute;ximo por jornada.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  team_play_rating: { icon: Handshake, title: 'Juego en Equipo', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Se ajusta seg&uacute;n el <strong>% de victorias</strong> del jugador. Si gana m&aacute;s del 55% de sus juegos y anota al menos el 70% del promedio de la liga, se considera constante y su estrella sube.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  court_knowledge_rating: { icon: Brain, title: 'Conocimiento', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema mide la <strong>diversidad de tiros</strong> usando entrop&iacute;a de Shannon &mdash; qu&eacute; tan balanceado es entre tiros libres (1PT), tiros de campo (2PT) y triples (3PT). Un jugador que solo hace un tipo obtiene puntuaci&oacute;n baja.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  defense_rating: { icon: Shield, title: 'Defensa', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema calcula los <strong>puntos permitidos por juego</strong> cuando este jugador est&aacute; en cancha. Si su equipo recibe menos puntos que el promedio de la liga su estrella sube. Los juegos son hasta 16 o 21 puntos, as&iacute; que el contexto importa.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  triples_rating: { icon: Flame, title: 'Triples', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. Se ajusta seg&uacute;n el <strong>porcentaje de triples</strong> del jugador &mdash; cu&aacute;ntos de sus tiros son de 3 puntos comparado con el jugador que m&aacute;s triples anota en la liga. Un especialista de triple obtiene la m&aacute;xima puntuaci&oacute;n.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  role: { icon: Tag, title: 'Rol', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema lo ajusta autom&aacute;ticamente al final de cada jornada: si anota 30% m&aacute;s que el promedio &rarr; <strong>Anotador</strong>. Si permite 20% menos puntos que el promedio &rarr; <strong>Defensivo</strong>. Si hace ambos &rarr; <strong>Equilibrado</strong>.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  offensive_consistency: { icon: BarChart3, title: 'Consistencia Ofensiva', body_html: 'Empieza con <strong>tu percepci&oacute;n</strong>. El sistema revisa si gana m&aacute;s del 55% de sus juegos Y anota al menos el 70% del promedio de la liga. Si cumple ambas condiciones &rarr; <strong>Constante</strong>. Si falla en alguna &rarr; <strong>Inconsistente</strong>.', tag: 'AUTO - SE AJUSTA CON JORNADAS', tone: 'auto' },
  rating: { icon: Star, title: 'Rating General', body_html: 'El rating evoluciona con la experiencia del jugador:<br><br><strong>Menos de 5 juegos:</strong> 80% tu percepci&oacute;n / 20% estad&iacute;sticas<br><strong>5-15 juegos:</strong> 60% percepci&oacute;n / 40% estad&iacute;sticas<br><strong>M&aacute;s de 15 juegos:</strong> 40% percepci&oacute;n / 60% estad&iacute;sticas<br><br>As&iacute; el sistema respeta tu conocimiento al inicio pero los n&uacute;meros van tomando control con el tiempo.', tag: 'HIBRIDO - EVOLUCIONA CON JORNADAS', tone: 'hybrid' },
}

const ratingFields: Array<{ key: RatingField; label: string; icon: typeof Zap }> = [
  { key: 'speed_rating', label: 'Velocidad', icon: Zap },
  { key: 'dribbling_rating', label: 'Dribbling', icon: CircleDot },
  { key: 'scoring_rating', label: 'Anotacion', icon: Sparkles },
  { key: 'team_play_rating', label: 'Juego en equipo', icon: Handshake },
  { key: 'court_knowledge_rating', label: 'Conocimiento', icon: Brain },
  { key: 'defense_rating', label: 'Defensa', icon: Shield },
  { key: 'triples_rating', label: 'Triples', icon: Flame },
]

function patchScout(row: ScoutRow, updates: Partial<ScoutProfile>) {
  if (!canManage.value) return
  router.patch(`/liga/modulos/scout/players/${row.player.id}`, {
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
  }, { preserveScroll: true })
}

function toggleChoice(row: ScoutRow, key: 'position' | 'role' | 'offensive_consistency', value: string) {
  patchScout(row, { [key]: row.profile[key] === value ? null : value } as Partial<ScoutProfile>)
}
function toggleRating(row: ScoutRow, key: RatingField, value: number) {
  patchScout(row, { [key]: row.profile[key] === value ? 0 : value } as Partial<ScoutProfile>)
}
function statValue(row: ScoutRow, key: RatingField): number | null {
  if (!row.stat_rating) return null
  if (key === 'scoring_rating') return row.stat_rating.scoring
  if (key === 'team_play_rating') return row.stat_rating.victories
  if (key === 'court_knowledge_rating') return row.stat_rating.diversity
  if (key === 'defense_rating') return row.stat_rating.defense
  if (key === 'triples_rating') return row.stat_rating.triples
  return null
}
function statDetail(row: ScoutRow, key: RatingField): string | null {
  if (!row.stat_rating) return null
  if (key === 'scoring_rating') return `${row.stat_rating.detail.points_per_game.toFixed(1)} pts/j`
  if (key === 'team_play_rating') return `${row.stat_rating.detail.win_rate}% victorias`
  if (key === 'court_knowledge_rating') return `${row.stat_rating.detail.diversity}% balance`
  if (key === 'defense_rating') return row.stat_rating.detail.points_allowed_per_game === null ? 'Sin defensa' : `${row.stat_rating.detail.points_allowed_per_game.toFixed(1)} pts recibidos`
  if (key === 'triples_rating') return `${row.stat_rating.detail.triple_rate}% triples`
  return null
}
function tipToneClass(tone: ScoutTip['tone']) {
  return tone === 'manual' ? 'border-[rgba(167,139,250,0.28)] bg-[rgba(167,139,250,0.16)] text-[#C4B5FD]' : tone === 'hybrid' ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.16)] text-[#4ADE80]' : 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.16)] text-[#E5B849]'
}
</script>

<template>
  <Head title="Scout" />
  <LeagueShellLayout :breadcrumbs="breadcrumbs" :league-name="props.league.name" :league-emoji="props.league.emoji" :role-label="props.role.label" active-module="scout" :can-manage-league="props.role.can_manage">
    <section class="app-surface space-y-4">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-3">
          <div class="flex items-center gap-3"><Search class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Scout de liga</p></div>
          <h1 class="app-display app-module-title text-[#F8FAFC]">Rating hibrido y draft automatico</h1>
          <p class="text-[14px] leading-7 text-[#94A3B8]">El ranking, la vista previa y el auto draft usan exactamente la misma combinacion entre percepcion manual y estadisticas reales de temporada.</p>
        </div>
        <div class="grid gap-2 rounded-[18px] border border-white/6 bg-[#0E1628] p-4 text-[12px] text-[#94A3B8]">
          <span>{{ props.scout.summary.profiled_players }} / {{ props.scout.summary.total_players }} perfilados</span>
          <span>Pool actual para auto draft: {{ props.scout.summary.auto_preview_pool_count }}</span>
          <span>{{ props.scout.summary.auto_preview_ready ? 'Vista previa lista' : 'Se necesitan 10 jugadores en pool' }}</span>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <article class="app-surface space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div><p class="app-kicker text-[#E5B849]">Ranking de calidad</p><p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">Ordenado por rating combinado. El modo automatico de Juego usa esta misma base.</p></div>
          <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">{{ props.scout.ranking.length }} jugadores</span>
        </div>
        <div v-if="props.scout.ranking.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">Todavia no hay perfiles o estadisticas suficientes para ordenar.</div>
        <div v-else class="grid gap-3">
          <div v-for="(row, index) in props.scout.ranking.slice(0, 8)" :key="row.player.id" class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="flex items-center gap-3">
                  <span class="inline-flex min-w-10 items-center justify-center rounded-full border border-white/8 bg-[#131B2F] px-3 py-2 text-xs font-semibold text-[#F8FAFC]">{{ rankingMedals[index] ?? `#${index + 1}` }}</span>
                  <div><p class="truncate text-[15px] font-semibold text-[#F8FAFC]">{{ row.player.name }}</p><p class="mt-1 text-[12px] text-[#94A3B8]">{{ row.profile.position || 'Sin posicion' }} / {{ row.profile.role || 'Sin rol' }}</p></div>
                </div>
              </div>
              <div class="text-right"><p class="app-display text-[26px] text-[#E5B849]">{{ row.combined_rating.toFixed(1) }}</p><p class="text-[11px] text-[#94A3B8]">{{ row.has_stats ? 'manual + stats' : 'solo manual' }}</p></div>
            </div>
          </div>
        </div>
      </article>

      <article class="app-surface space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div><p class="app-kicker text-[#E5B849]">Auto balance</p><p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">Vista previa del mismo motor automatico que usa Juego cuando el pool actual esta listo.</p></div>
          <button type="button" class="inline-flex size-9 items-center justify-center rounded-full border border-white/8 bg-[#131B2F] text-[#94A3B8]" @click="openTip = tipMap.rating"><BadgeInfo class="size-4" /></button>
        </div>
        <div v-if="!props.scout.auto_preview" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">La vista previa se activa cuando Llegada deja exactamente 10 jugadores listos en el pool inicial.</div>
        <div v-else class="grid gap-3 md:grid-cols-2">
          <div class="rounded-[16px] border border-[rgba(74,222,128,0.22)] bg-[rgba(74,222,128,0.08)] p-4"><div class="flex items-center justify-between gap-3"><p class="app-kicker text-[#4ADE80]">Equipo A</p><span class="text-sm font-semibold text-[#4ADE80]">{{ props.scout.auto_preview.team_a_rating.toFixed(1) }}</span></div><div class="mt-3 grid gap-2"><div v-for="player in props.scout.auto_preview.team_a" :key="player.id" class="rounded-[12px] border border-white/6 bg-[#0E1628] px-3 py-2"><p class="text-[13px] font-semibold text-[#F8FAFC]">{{ player.name }}</p><p class="mt-1 text-[11px] text-[#94A3B8]">{{ player.position || 'Sin posicion' }} / {{ player.role || 'Sin rol' }} / {{ player.combined_rating.toFixed(1) }}</p></div></div></div>
          <div class="rounded-[16px] border border-[rgba(229,184,73,0.22)] bg-[rgba(229,184,73,0.08)] p-4"><div class="flex items-center justify-between gap-3"><p class="app-kicker text-[#E5B849]">Equipo B</p><span class="text-sm font-semibold text-[#E5B849]">{{ props.scout.auto_preview.team_b_rating.toFixed(1) }}</span></div><div class="mt-3 grid gap-2"><div v-for="player in props.scout.auto_preview.team_b" :key="player.id" class="rounded-[12px] border border-white/6 bg-[#0E1628] px-3 py-2"><p class="text-[13px] font-semibold text-[#F8FAFC]">{{ player.name }}</p><p class="mt-1 text-[11px] text-[#94A3B8]">{{ player.position || 'Sin posicion' }} / {{ player.role || 'Sin rol' }} / {{ player.combined_rating.toFixed(1) }}</p></div></div></div>
        </div>
      </article>
    </section>

    <section class="app-surface space-y-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div><p class="app-kicker text-[#E5B849]">Perfiles individuales</p><p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">Los admins ajustan estrellas, posicion, rol y consistencia. Los miembros solo ven la informacion.</p></div>
        <label class="relative block w-full md:max-w-[280px]"><Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#64748B]" /><input v-model="searchTerm" type="text" placeholder="Buscar jugador" class="min-h-12 w-full rounded-[14px] border border-white/8 bg-[#0E1628] pl-10 pr-4 text-sm text-[#F8FAFC] outline-none placeholder:text-[#64748B]"></label>
      </div>

      <div v-if="filteredPlayers.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">No hay jugadores que coincidan con la busqueda.</div>
      <div v-else class="grid gap-4">
        <article v-for="row in filteredPlayers" :key="row.player.id" class="rounded-[20px] border border-white/6 bg-[#0E1628] p-4">
          <div class="flex flex-col gap-4 border-b border-white/6 pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
              <div class="flex items-center gap-3"><div class="flex size-11 items-center justify-center rounded-full border border-[rgba(229,184,73,0.22)] bg-[#131B2F] text-sm font-semibold text-[#E5B849]">{{ row.player.jersey_number ?? 'SC' }}</div><div><p class="text-[16px] font-semibold text-[#F8FAFC]">{{ row.player.name }}</p><p class="mt-1 text-[12px] text-[#94A3B8]">Rating {{ row.combined_rating.toFixed(1) }} / Manual {{ row.manual_rating.toFixed(1) }} / Stats {{ row.stat_rating?.overall.toFixed(1) ?? 'n/a' }}</p></div></div>
              <p class="text-[12px] leading-6 text-[#94A3B8]"><template v-if="row.season_stats">{{ row.season_stats.games }} juegos / {{ row.season_stats.points }} puntos / {{ row.season_stats.win_rate }}% victorias / {{ row.season_stats.sessions_attended }} jornadas</template><template v-else>Aun no tiene suficiente estadistica acumulada; el peso actual sigue siendo mayormente manual.</template></p>
            </div>
            <div class="flex items-center gap-2"><button type="button" class="inline-flex size-9 items-center justify-center rounded-full border border-white/8 bg-[#131B2F] text-[#94A3B8]" @click="openTip = tipMap.rating"><BadgeInfo class="size-4" /></button><span class="rounded-full border px-3 py-1 text-[11px] font-semibold" :class="row.has_stats ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]' : 'border-white/8 bg-[#131B2F] text-[#94A3B8]'">{{ row.has_stats ? 'manual + stats' : 'solo manual' }}</span></div>
          </div>

          <div v-if="row.stat_rating" class="mt-4 rounded-[16px] border border-[rgba(229,184,73,0.16)] bg-[#131B2F] p-4">
            <div class="flex items-center justify-between gap-3"><div><p class="app-kicker text-[#E5B849]">Rating de temporada</p><p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">Calculado con victorias, anotacion, defensa, triples y diversidad real de tiros.</p></div><span class="app-display text-[28px] text-[#E5B849]">{{ row.stat_rating.overall.toFixed(1) }}</span></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-3"><p class="text-[11px] uppercase tracking-[0.18em] text-[#94A3B8]">Anotacion</p><p class="mt-2 font-['Bebas_Neue'] text-[28px] leading-none text-[#4ADE80]">{{ row.stat_rating.scoring.toFixed(1) }}</p><p class="mt-2 text-[11px] text-[#94A3B8]">{{ row.stat_rating.detail.points_per_game.toFixed(1) }} pts/j</p></div>
              <div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-3"><p class="text-[11px] uppercase tracking-[0.18em] text-[#94A3B8]">Equipo</p><p class="mt-2 font-['Bebas_Neue'] text-[28px] leading-none text-[#E5B849]">{{ row.stat_rating.victories.toFixed(1) }}</p><p class="mt-2 text-[11px] text-[#94A3B8]">{{ row.stat_rating.detail.win_rate }}% victorias</p></div>
              <div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-3"><p class="text-[11px] uppercase tracking-[0.18em] text-[#94A3B8]">Diversidad</p><p class="mt-2 font-['Bebas_Neue'] text-[28px] leading-none text-[#F8FAFC]">{{ row.stat_rating.diversity.toFixed(1) }}</p><p class="mt-2 text-[11px] text-[#94A3B8]">{{ row.stat_rating.detail.diversity }}% balance</p></div>
              <div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-3"><p class="text-[11px] uppercase tracking-[0.18em] text-[#94A3B8]">Defensa</p><p class="mt-2 font-['Bebas_Neue'] text-[28px] leading-none text-[#F87171]">{{ row.stat_rating.defense.toFixed(1) }}</p><p class="mt-2 text-[11px] text-[#94A3B8]">{{ row.stat_rating.detail.points_allowed_per_game === null ? 'Sin defensa' : `${row.stat_rating.detail.points_allowed_per_game.toFixed(1)} pts recibidos` }}</p></div>
              <div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-3"><p class="text-[11px] uppercase tracking-[0.18em] text-[#94A3B8]">Triples</p><p class="mt-2 font-['Bebas_Neue'] text-[28px] leading-none text-[#38BDF8]">{{ row.stat_rating.triples.toFixed(1) }}</p><p class="mt-2 text-[11px] text-[#94A3B8]">{{ row.stat_rating.detail.triple_rate }}% triples</p></div>
            </div>
          </div>

          <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="grid gap-4">
              <div class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4"><div class="mb-3 flex items-center gap-2"><Tag class="size-4 text-[#E5B849]" /><p class="text-[12px] font-semibold uppercase tracking-[0.18em] text-[#94A3B8]">Posicion</p></div><div class="flex flex-wrap gap-2"><button v-for="option in props.scout.meta.positions" :key="option" type="button" class="min-h-10 rounded-full border px-4 text-xs font-semibold" :class="row.profile.position === option ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'" :disabled="!canManage" @click="toggleChoice(row, 'position', option)">{{ option }}</button></div></div>
              <div class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4"><div class="mb-3 flex items-center justify-between gap-3"><div class="flex items-center gap-2"><Tag class="size-4 text-[#E5B849]" /><p class="text-[12px] font-semibold uppercase tracking-[0.18em] text-[#94A3B8]">Rol</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-full border border-white/8 bg-[#0E1628] text-[#94A3B8]" @click="openTip = tipMap.role"><BadgeInfo class="size-4" /></button></div><div class="flex flex-wrap gap-2"><button v-for="option in props.scout.meta.roles" :key="option" type="button" class="min-h-10 rounded-full border px-4 text-xs font-semibold" :class="row.profile.role === option ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'" :disabled="!canManage" @click="toggleChoice(row, 'role', option)">{{ option }}</button></div></div>
              <div class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4"><div class="mb-3 flex items-center justify-between gap-3"><div class="flex items-center gap-2"><BarChart3 class="size-4 text-[#E5B849]" /><p class="text-[12px] font-semibold uppercase tracking-[0.18em] text-[#94A3B8]">Consistencia</p></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-full border border-white/8 bg-[#0E1628] text-[#94A3B8]" @click="openTip = tipMap.offensive_consistency"><BadgeInfo class="size-4" /></button></div><div class="flex flex-wrap gap-2"><button v-for="option in props.scout.meta.consistencies" :key="option" type="button" class="min-h-10 rounded-full border px-4 text-xs font-semibold" :class="row.profile.offensive_consistency === option ? 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'" :disabled="!canManage" @click="toggleChoice(row, 'offensive_consistency', option)">{{ option }}</button></div></div>
            </div>
            <div class="grid gap-3">
              <div v-for="field in ratingFields" :key="field.key" class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><component :is="field.icon" class="size-4 text-[#E5B849]" /><p class="text-[12px] font-semibold uppercase tracking-[0.18em] text-[#94A3B8]">{{ field.label }}</p><span v-if="statValue(row, field.key) !== null" class="rounded-full border border-[rgba(229,184,73,0.22)] bg-[rgba(229,184,73,0.12)] px-2 py-1 text-[10px] font-semibold text-[#E5B849]">AUTO</span></div><button type="button" class="inline-flex size-8 items-center justify-center rounded-full border border-white/8 bg-[#0E1628] text-[#94A3B8]" @click="openTip = tipMap[field.key]"><BadgeInfo class="size-4" /></button></div>
                <div class="mt-3 flex flex-wrap items-center gap-2"><button v-for="star in 5" :key="star" type="button" class="inline-flex size-10 items-center justify-center rounded-full border" :class="row.profile[field.key] >= star ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.16)] text-[#E5B849]' : 'border-white/6 bg-[#0E1628] text-[#64748B]'" :disabled="!canManage" @click="toggleRating(row, field.key, star)"><Star class="size-4" :fill="row.profile[field.key] >= star ? 'currentColor' : 'none'" /></button><span class="ml-auto text-[12px] text-[#94A3B8]">{{ row.profile[field.key] }}/5</span></div>
                <div v-if="statValue(row, field.key) !== null" class="mt-3 flex items-center justify-between gap-3 rounded-[12px] border border-white/6 bg-[#0E1628] px-3 py-2 text-[11px] text-[#94A3B8]"><span>Base estadistica</span><span class="font-semibold text-[#E5B849]">{{ statValue(row, field.key)?.toFixed(1) }}</span><span class="text-right">{{ statDetail(row, field.key) }}</span></div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <Dialog :open="openTip !== null" @update:open="openTip = null">
      <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
        <DialogHeader class="space-y-4">
          <div class="flex items-center gap-3"><div class="inline-flex size-11 items-center justify-center rounded-full border border-[rgba(229,184,73,0.24)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]"><component :is="openTip?.icon" class="size-5" /></div><DialogTitle class="app-display text-[30px]">{{ openTip?.title }}</DialogTitle></div>
          <div v-if="openTip" class="text-[13px] leading-7 text-[#94A3B8] [&_strong]:text-[#F8FAFC]" v-html="openTip.body_html" />
        </DialogHeader>
        <DialogFooter class="flex items-center justify-between gap-3"><span v-if="openTip" class="rounded-full border px-3 py-1 text-[11px] font-semibold" :class="tipToneClass(openTip.tone)">{{ openTip.tag }}</span><Button type="button" variant="secondary" class="min-h-11 rounded-[12px] border border-white/8 bg-[#131B2F]" @click="openTip = null">Entendido</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  </LeagueShellLayout>
</template>
