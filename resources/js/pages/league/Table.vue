<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Medal, Trophy, Users } from 'lucide-vue-next';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    session_selector: {
        selected_session_id: number | null;
        sessions: Array<{
            id: number;
            session_date: string | null;
            status: string;
            entries_count: number;
            completed_games_count: number;
            is_current: boolean;
        }>;
    };
    table: {
        banner: { games: number; points: number; players: number };
        standings: Array<{
            identity: { name: string; is_guest: boolean };
            games: number;
            wins: number;
            losses: number;
            win_rate: number;
        }>;
        top_scorers: Array<{
            identity: { name: string; is_guest: boolean };
            points: number;
            points_per_game: number;
        }>;
        top_games: Array<{
            identity: { name: string; is_guest: boolean };
            games: number;
            wins: number;
            losses: number;
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tabla', href: '/liga/modulos/tabla' }];

function changeSession(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const sessionId = Number(target.value);

    if (!Number.isFinite(sessionId) || sessionId <= 0) {
        return;
    }

    router.get(
        '/liga/modulos/tabla',
        { session_id: sessionId },
        { preserveScroll: true, preserveState: true },
    );
}

function sessionLabel(session: {
    session_date: string | null;
    status: string;
    completed_games_count: number;
    is_current: boolean;
}): string {
    const base = session.session_date ?? 'Sin fecha';
    const suffix = session.is_current
        ? ' · actual'
        : session.status === 'completed'
          ? ' · cerrada'
          : ' · abierta';

    return `${base}${suffix} · ${session.completed_games_count} juegos`;
}
</script>

<template>
    <Head title="Tabla" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.league.name"
        :league-emoji="props.league.emoji"
        :role-label="props.role.label"
        active-module="tabla"
        :can-manage-league="props.role.can_manage"
    >
        <section class="app-surface space-y-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex items-center gap-3">
                    <Trophy class="size-5 text-[#E5B849]" />
                    <div>
                        <p class="app-kicker text-[#E5B849]">Tabla de jornada</p>
                        <h1 class="app-display app-module-title mt-2 text-[#F8FAFC]">
                            Líderes por jornada
                        </h1>
                        <p class="mt-3 text-[14px] leading-7 text-[#94A3B8]">
                            La tabla combina victorias, puntos y volumen de juegos de la jornada seleccionada para ordenar el desempeño.
                        </p>
                    </div>
                </div>

                <select
                    :value="props.session_selector.selected_session_id ?? ''"
                    :disabled="props.session_selector.sessions.length === 0"
                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    @change="changeSession"
                >
                    <option
                        v-if="props.session_selector.sessions.length === 0"
                        value=""
                    >
                        Sin jornadas registradas
                    </option>
                    <option
                        v-for="session in props.session_selector.sessions"
                        :key="session.id"
                        :value="session.id"
                    >
                        {{ sessionLabel(session) }}
                    </option>
                </select>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="app-surface space-y-2">
                <p class="app-kicker">Juegos cerrados</p>
                <p class="text-[30px] font-semibold text-[#F8FAFC]">{{ props.table.banner.games }}</p>
                <p class="text-[12px] text-[#94A3B8]">Partidos que ya alimentan la tabla</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Puntos totales</p>
                <p class="text-[30px] font-semibold text-[#E5B849]">{{ props.table.banner.points }}</p>
                <p class="text-[12px] text-[#94A3B8]">Producción ofensiva acumulada</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Jugadores activos</p>
                <p class="text-[30px] font-semibold text-[#F8FAFC]">{{ props.table.banner.players }}</p>
                <p class="text-[12px] text-[#94A3B8]">Participantes registrados en la jornada</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Medal class="size-5 text-[#E5B849]" />
                    <div>
                        <p class="app-kicker text-[#E5B849]">Tabla general</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Se ordena por victorias y se desempata por puntos totales dentro de la jornada visible.
                        </p>
                    </div>
                </div>

                <div
                    v-if="props.table.standings.length === 0"
                    class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
                >
                    Sin juegos terminados todavía.
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="(row, index) in props.table.standings"
                        :key="`${row.identity.name}-${index}`"
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex size-9 items-center justify-center rounded-full border border-white/8 bg-[#131B2F] text-sm font-semibold text-[#F8FAFC]">
                                        {{ index + 1 }}
                                    </span>
                                    <div>
                                        <p class="truncate text-[15px] font-semibold text-[#F8FAFC]">
                                            {{ row.identity.name }}
                                        </p>
                                        <p class="mt-1 text-[12px] text-[#94A3B8]">
                                            {{ row.games }} juegos · {{ row.wins }}V - {{ row.losses }}D
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <span class="rounded-full border border-[rgba(74,222,128,0.24)] bg-[rgba(74,222,128,0.12)] px-3 py-1 text-[11px] font-semibold text-[#4ADE80]">
                                {{ row.win_rate }}% victorias
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <div class="grid gap-4">
                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <BarChart3 class="size-5 text-[#E5B849]" />
                        <div>
                            <p class="app-kicker text-[#E5B849]">Top anotadores</p>
                            <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                                Los cinco perfiles con mayor producción ofensiva de la jornada visible.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.table.top_scorers.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
                    >
                        Sin anotadores destacados todavía.
                    </div>

                    <div v-else class="grid gap-3">
                        <div
                            v-for="(row, index) in props.table.top_scorers"
                            :key="`${row.identity.name}-${index}`"
                            class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-[14px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                    <p class="mt-1 text-[12px] text-[#94A3B8]">
                                        {{ row.points_per_game }} pts por juego
                                    </p>
                                </div>
                                <span class="text-[18px] font-semibold text-[#E5B849]">{{ row.points }} pts</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <Users class="size-5 text-[#4ADE80]" />
                        <div>
                            <p class="app-kicker text-[#E5B849]">Más usados</p>
                            <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                                Quienes más tiempo han pasado rotando durante la jornada seleccionada.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="props.table.top_games.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
                    >
                        Sin volumen de juegos todavía.
                    </div>

                    <div v-else class="grid gap-3">
                        <div
                            v-for="(row, index) in props.table.top_games"
                            :key="`${row.identity.name}-${index}`"
                            class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-[14px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                    <p class="mt-1 text-[12px] text-[#94A3B8]">
                                        {{ row.wins }}V - {{ row.losses }}D
                                    </p>
                                </div>
                                <span class="text-[18px] font-semibold text-[#4ADE80]">{{ row.games }}</span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </LeagueShellLayout>
</template>
