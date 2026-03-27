<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clock3, Wallet } from 'lucide-vue-next';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    session_selector: {
        selected_session_id: number;
        sessions: Array<{
            id: number;
            session_date: string | null;
            status: string;
            entries_count: number;
            completed_games_count: number;
            is_current: boolean;
        }>;
    };
    queue: {
        on_court: Array<{ id: number; name: string; team_side: string | null; games_played: number; points_scored: number }>;
        waiting: Array<{ id: number; name: string; position: number | null; games_played: number; points_scored: number }>;
        summary: {
            games: number;
            streak_label: string;
            current_streak: string;
            active_players: number;
            guests: number;
            today_guests: number;
            cash_collected_cents: number;
            unpaid_members_count: number;
        };
        live_game: null | { game_number: number; score: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cola', href: '/liga/modulos/cola' }];

function changeSession(event: Event): void {
    const target = event.target as HTMLSelectElement;

    router.get(
        '/liga/modulos/cola',
        { session_id: Number(target.value) },
        { preserveScroll: true, preserveState: true },
    );
}

function sessionLabel(session: { session_date: string | null; status: string; completed_games_count: number; is_current: boolean }): string {
    const base = session.session_date ?? 'Sin fecha';
    const suffix = session.is_current ? ' · actual' : session.status === 'completed' ? ' · cerrada' : ' · abierta';

    return `${base}${suffix} · ${session.completed_games_count} juegos`;
}
</script>

<template>
    <Head title="Cola" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.league.name"
        :league-emoji="props.league.emoji"
        :role-label="props.role.label"
        active-module="cola"
        :can-manage-league="props.role.can_manage"
    >
        <section class="app-surface space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="app-kicker text-[#E5B849]">Jornada visible</p>
                    <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                        Consulta la cola y el estado operativo de la jornada actual o de jornadas anteriores ya jugadas.
                    </p>
                </div>
                <select
                    :value="props.session_selector.selected_session_id"
                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none"
                    @change="changeSession"
                >
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

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="app-surface space-y-2">
                <p class="app-kicker">Juegos</p>
                <p class="text-[28px] font-semibold text-[#F8FAFC]">{{ props.queue.summary.games }}</p>
                <p class="text-[12px] text-[#94A3B8]">{{ props.queue.summary.streak_label }}</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Activos hoy</p>
                <p class="text-[28px] font-semibold text-[#F8FAFC]">{{ props.queue.summary.active_players }}</p>
                <p class="text-[12px] text-[#94A3B8]">{{ props.queue.summary.guests }} invitados incluidos</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Racha actual</p>
                <p class="text-[28px] font-semibold text-[#F8FAFC]">{{ props.queue.summary.current_streak }}</p>
                <p class="text-[12px] text-[#94A3B8]">Secuencia vigente de la jornada seleccionada.</p>
            </article>
            <article class="app-surface space-y-2">
                <p class="app-kicker">Invitados hoy</p>
                <p class="text-[28px] font-semibold text-[#F8FAFC]">{{ props.queue.summary.today_guests }}</p>
                <p class="text-[12px] text-[#94A3B8]">Invitados registrados en esta jornada.</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Jugadores en cancha</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Vista viva del juego actual con su equipo y acumulado de la jornada.
                        </p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.queue.on_court.length }}
                    </span>
                </div>

                <div class="grid gap-3">
                    <div
                        v-for="player in props.queue.on_court"
                        :key="player.id"
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ player.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ player.games_played }} juegos · {{ player.points_scored }} puntos</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-[11px] font-semibold"
                                :class="player.team_side === 'A' ? 'bg-[rgba(74,222,128,0.12)] text-[#4ADE80]' : 'bg-[rgba(229,184,73,0.12)] text-[#E5B849]'"
                            >
                                Eq. {{ player.team_side }}
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <div class="grid gap-4">
                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <Clock3 class="size-5 text-[#E5B849]" />
                        <p class="app-kicker text-[#E5B849]">Cola</p>
                    </div>
                    <div v-if="props.queue.waiting.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                        Cola vacia.
                    </div>
                    <div v-else class="grid gap-3">
                        <div
                            v-for="player in props.queue.waiting"
                            :key="player.id"
                            class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ player.name }}</p>
                                    <p class="mt-1 text-[12px] text-[#94A3B8]">{{ player.games_played }} juegos · {{ player.points_scored }} puntos</p>
                                </div>
                                <span class="rounded-full border border-white/6 bg-[#131B2F] px-3 py-1 text-[11px] text-[#94A3B8]">
                                    #{{ player.position }}
                                </span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="app-surface space-y-3">
                    <div class="flex items-center gap-3">
                        <Wallet class="size-5 text-[#E5B849]" />
                        <p class="app-kicker text-[#E5B849]">Resumen</p>
                    </div>
                    <p class="text-[13px] leading-6 text-[#94A3B8]">
                        El modulo Juego sigue controlando el cierre de jornada. Aqui solo ves el estado vivo de cancha, espera y cobros del dia.
                    </p>
                    <p v-if="props.queue.live_game" class="text-[12px] text-[#94A3B8]">
                        Juego actual: #{{ props.queue.live_game.game_number }} · {{ props.queue.live_game.score }}
                    </p>
                </article>
            </div>
        </section>
    </LeagueShellLayout>
</template>
