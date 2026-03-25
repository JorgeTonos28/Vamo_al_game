<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarRange, Coins, Medal, Trophy } from 'lucide-vue-next';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { formatCompactDate, formatMoney } from '@/lib/league';
import type { BreadcrumbItem } from '@/types';

type SeasonProfile = {
    identity: { name: string; is_guest: boolean };
    points: number;
    games: number;
    wins: number;
    losses: number;
    shots: { 1: number; 2: number; 3: number };
    points_per_game: number;
    win_rate: number;
    sessions_attended: number;
};

const props = defineProps<{
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    season: {
        season: {
            id: number;
            label: string;
            starts_on: string | null;
            sessions_count: number;
            totals: {
                games: number;
                points: number;
                revenue_cents: number;
                show_revenue: boolean;
            };
        };
        leaders: {
            points: SeasonProfile[];
            wins: SeasonProfile[];
            games: SeasonProfile[];
        };
        sessions: Array<{
            id: number;
            date: string | null;
            total_games: number;
            total_points: number;
            players: number;
            top_scorer: null | { name: string; points: number };
        }>;
        profiles: SeasonProfile[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Temporada', href: '/liga/modulos/temporada' }];
</script>

<template>
    <Head title="Temporada" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.league.name"
        :league-emoji="props.league.emoji"
        :role-label="props.role.label"
        active-module="temporada"
        :can-manage-league="props.role.can_manage"
    >
        <section class="app-surface space-y-4">
            <div class="flex items-center gap-3">
                <CalendarRange class="size-5 text-[#E5B849]" />
                <div>
                    <p class="app-kicker text-[#E5B849]">Temporada activa</p>
                    <h1 class="app-display app-module-title mt-2 text-[#F8FAFC]">
                        {{ props.season.season.label }}
                    </h1>
                    <p class="mt-3 text-[14px] leading-7 text-[#94A3B8]">
                        Resumen acumulado de jornadas, liderazgo individual y trazabilidad de cada sesion jugada.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="app-surface space-y-2">
                <p class="app-kicker">Jornadas</p>
                <p class="text-[30px] font-semibold text-[#F8FAFC]">{{ props.season.season.sessions_count }}</p>
                <p class="text-[12px] text-[#94A3B8]">
                    Desde {{ formatCompactDate(props.season.season.starts_on) }}
                </p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Juegos</p>
                <p class="text-[30px] font-semibold text-[#F8FAFC]">{{ props.season.season.totals.games }}</p>
                <p class="text-[12px] text-[#94A3B8]">Partidos cerrados en temporada</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Puntos</p>
                <p class="text-[30px] font-semibold text-[#E5B849]">{{ props.season.season.totals.points }}</p>
                <p class="text-[12px] text-[#94A3B8]">Produccion total acumulada</p>
            </article>
            <article
                v-if="props.season.season.totals.show_revenue"
                class="app-surface space-y-2"
            >
                <p class="app-kicker">Recaudado</p>
                <p class="text-[30px] font-semibold text-[#4ADE80]">
                    {{ formatMoney(props.season.season.totals.revenue_cents) }}
                </p>
                <p class="text-[12px] text-[#94A3B8]">Visible solo para admins</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Medal class="size-5 text-[#E5B849]" />
                    <p class="app-kicker text-[#E5B849]">Lideres en puntos</p>
                </div>
                <div v-if="props.season.leaders.points.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin datos todavia.
                </div>
                <div v-else class="grid gap-3">
                    <div v-for="(row, index) in props.season.leaders.points" :key="`${row.identity.name}-${index}`" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[14px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ row.points_per_game }} pts por juego</p>
                            </div>
                            <span class="text-[18px] font-semibold text-[#E5B849]">{{ row.points }}</span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Trophy class="size-5 text-[#4ADE80]" />
                    <p class="app-kicker text-[#E5B849]">Lideres en victorias</p>
                </div>
                <div v-if="props.season.leaders.wins.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin datos todavia.
                </div>
                <div v-else class="grid gap-3">
                    <div v-for="(row, index) in props.season.leaders.wins" :key="`${row.identity.name}-${index}`" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[14px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ row.games }} juegos · {{ row.win_rate }}%</p>
                            </div>
                            <span class="text-[18px] font-semibold text-[#4ADE80]">{{ row.wins }}V</span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Coins class="size-5 text-[#F97316]" />
                    <p class="app-kicker text-[#E5B849]">Mas jornadas jugadas</p>
                </div>
                <div v-if="props.season.leaders.games.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin datos todavia.
                </div>
                <div v-else class="grid gap-3">
                    <div v-for="(row, index) in props.season.leaders.games" :key="`${row.identity.name}-${index}`" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[14px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ row.sessions_attended }} jornadas asistidas</p>
                            </div>
                            <span class="text-[18px] font-semibold text-[#F97316]">{{ row.games }}</span>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <CalendarRange class="size-5 text-[#E5B849]" />
                    <div>
                        <p class="app-kicker text-[#E5B849]">Jornadas registradas</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Cada sesion resume volumen de juego, puntos y su mejor anotador.
                        </p>
                    </div>
                </div>

                <div v-if="props.season.sessions.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin jornadas guardadas todavia.
                </div>

                <div v-else class="grid gap-3">
                    <div v-for="sessionRow in props.season.sessions" :key="sessionRow.id" class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#F8FAFC]">
                                    {{ formatCompactDate(sessionRow.date) }}
                                </p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">
                                    {{ sessionRow.total_games }} juegos · {{ sessionRow.players }} jugadores
                                </p>
                            </div>
                            <span class="rounded-full border border-white/6 bg-[#131B2F] px-3 py-1 text-[11px] text-[#94A3B8]">
                                {{ sessionRow.total_points }} pts
                            </span>
                        </div>
                        <p class="mt-3 text-[12px] leading-6 text-[#94A3B8]">
                            <template v-if="sessionRow.top_scorer">
                                Mejor anotador: {{ sessionRow.top_scorer.name }} con {{ sessionRow.top_scorer.points }} pts.
                            </template>
                            <template v-else>
                                No hubo un lider ofensivo definido en esa sesion.
                            </template>
                        </p>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Trophy class="size-5 text-[#E5B849]" />
                    <div>
                        <p class="app-kicker text-[#E5B849]">Perfiles acumulados</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Vista de temporada para comparar produccion, victorias y asistencia.
                        </p>
                    </div>
                </div>

                <div v-if="props.season.profiles.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin perfiles acumulados.
                </div>

                <div v-else class="grid gap-3">
                    <div v-for="(row, index) in props.season.profiles" :key="`${row.identity.name}-${index}`" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[15px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">
                                    {{ row.games }} juegos · {{ row.wins }}V - {{ row.losses }}D · {{ row.sessions_attended }} jornadas
                                </p>
                            </div>
                            <span class="rounded-full border border-[rgba(229,184,73,0.24)] bg-[rgba(229,184,73,0.12)] px-3 py-1 text-[11px] font-semibold text-[#E5B849]">
                                {{ row.points }} pts
                            </span>
                        </div>
                        <p class="mt-3 text-[12px] text-[#94A3B8]">
                            1P: {{ row.shots[1] }} · 2P: {{ row.shots[2] }} · 3P: {{ row.shots[3] }} · {{ row.points_per_game }} pts/juego
                        </p>
                    </div>
                </div>
            </article>
        </section>
    </LeagueShellLayout>
</template>
