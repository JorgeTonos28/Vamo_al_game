<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    RotateCcw,
    SearchSlash,
    Swords,
    Trophy,
    UserMinus,
    Waves,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/league';
import type { BreadcrumbItem } from '@/types';

type PlayerCard = {
    id: number;
    name: string;
    is_guest: boolean;
    jersey_number: number | null;
    arrival_order: number;
};

type TeamPlayer = PlayerCard & {
    points: number;
    shots: { 1: number; 2: number; 3: number };
};

type TeamSide = 'A' | 'B';

type ScoreFlashState = {
    key: number;
    label: string;
    side: TeamSide;
};

const props = defineProps<{
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    session: {
        id: number;
        status: string;
        current_game_number: number;
        streak: {
            team: 'A' | 'B' | null;
            count: number;
            double_rotation_mode: boolean;
            waiting_champion_team: 'A' | 'B' | null;
        };
    };
    game: {
        state: 'idle' | 'draft' | 'live' | 'completed';
        draft: { entries: PlayerCard[]; can_start: boolean };
        current: null | {
            id: number;
            game_number: number;
            score: { team_a: number; team_b: number };
            streak: {
                team: 'A' | 'B' | null;
                count: number;
                double_rotation_mode: boolean;
                waiting_champion_team: 'A' | 'B' | null;
            };
            team_a: TeamPlayer[];
            team_b: TeamPlayer[];
        };
        history: Array<{
            id: number;
            game_number: number;
            score: string;
            winner_side: 'A' | 'B' | null;
            summary: string;
        }>;
        summary: {
            games: number;
            streak_label: string;
            active_players: number;
            guests: number;
            cash_collected_cents: number;
            unpaid_members_count: number;
        };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Juego', href: '/liga/modulos/juego' }];
const draftMode = ref<'auto' | 'arrival' | 'manual'>('auto');
const manualAssignments = reactive<Record<number, 'A' | 'B'>>({});
const selectedPlayer = ref<TeamPlayer | null>(null);
const revertPlayer = ref<TeamPlayer | null>(null);
const playerToRemove = ref<TeamPlayer | null>(null);
const finishDialogOpen = ref(false);
const scoreFlash = ref<ScoreFlashState | null>(null);
const scoreBumpSide = ref<TeamSide | null>(null);

const canManage = computed(() => props.role.can_manage);
const teamACount = computed(() => Object.values(manualAssignments).filter((team) => team === 'A').length);
const teamBCount = computed(() => Object.values(manualAssignments).filter((team) => team === 'B').length);
const streakLabel = computed(() => props.game.current?.streak.team
    ? `EQ.${props.game.current.streak.team} - ${props.game.current.streak.count}`
    : 'Sin racha');
let scoreFeedbackNonce = 0;
let scoreFlashTimer: ReturnType<typeof setTimeout> | null = null;
let scoreBumpTimer: ReturnType<typeof setTimeout> | null = null;

function teamSideForPlayer(entryId: number): TeamSide | null {
    if (props.game.current?.team_a.some((player) => player.id === entryId)) {
        return 'A';
    }

    if (props.game.current?.team_b.some((player) => player.id === entryId)) {
        return 'B';
    }

    return null;
}

function triggerScoreFeedback(teamSide: TeamSide, points: number) {
    scoreFeedbackNonce += 1;
    scoreFlash.value = {
        key: scoreFeedbackNonce,
        label: `+${points}`,
        side: teamSide,
    };
    scoreBumpSide.value = teamSide;

    if (scoreFlashTimer !== null) {
        clearTimeout(scoreFlashTimer);
    }

    if (scoreBumpTimer !== null) {
        clearTimeout(scoreBumpTimer);
    }

    scoreFlashTimer = setTimeout(() => {
        scoreFlash.value = null;
    }, 520);

    scoreBumpTimer = setTimeout(() => {
        scoreBumpSide.value = null;
    }, 180);
}

function setAssignment(entryId: number, team: 'A' | 'B') {
    manualAssignments[entryId] = team;
}

function submitDraft() {
    if (!canManage.value) return;

    const payload =
        draftMode.value === 'manual'
            ? { mode: draftMode.value, assignments: manualAssignments }
            : { mode: draftMode.value };

    router.post('/liga/modulos/juego/draft', payload, { preserveScroll: true });
}

function addTeamPoint(teamSide: 'A' | 'B') {
    if (!canManage.value) return;
    router.post('/liga/modulos/juego/team-point', { team_side: teamSide }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerScoreFeedback(teamSide, 1);
        },
    });
}

function addPlayerPoint(points: 1 | 2 | 3) {
    if (!selectedPlayer.value || !canManage.value) return;
    const teamSide = teamSideForPlayer(selectedPlayer.value.id);

    router.post(
        `/liga/modulos/juego/players/${selectedPlayer.value.id}/point`,
        { points },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (teamSide !== null) {
                    triggerScoreFeedback(teamSide, points);
                }

                selectedPlayer.value = null;
            },
        },
    );
}

function revertPlayerPoint(points: 1 | 2 | 3) {
    if (!revertPlayer.value || !canManage.value) return;

    router.post(
        `/liga/modulos/juego/players/${revertPlayer.value.id}/revert`,
        { points },
        {
            preserveScroll: true,
            onSuccess: () => {
                revertPlayer.value = null;
            },
        },
    );
}

function openRemovePlayerModal(player: TeamPlayer) {
    if (!canManage.value) return;
    playerToRemove.value = player;
}

function confirmRemovePlayer() {
    if (!playerToRemove.value || !canManage.value) return;

    router.post(`/liga/modulos/juego/players/${playerToRemove.value.id}/remove`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            playerToRemove.value = null;
        },
    });
}

function undoLastAction() {
    if (!canManage.value) return;
    router.post('/liga/modulos/juego/undo', {}, { preserveScroll: true });
}

function finishGame(winnerSide?: 'A' | 'B') {
    if (!canManage.value) return;
    router.post('/liga/modulos/juego/finish', winnerSide ? { winner_side: winnerSide } : {}, {
        preserveScroll: true,
        onSuccess: () => {
            finishDialogOpen.value = false;
        },
    });
}

function endSession() {
    if (!canManage.value || !window.confirm('Cerrar la jornada del dia?')) return;
    router.post('/liga/modulos/juego/end-session', {}, { preserveScroll: true });
}

function resetCurrentGame() {
    if (!canManage.value || !window.confirm('Limpiar por completo el juego actual?')) return;
    router.post('/liga/modulos/juego/reset', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Juego" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.league.name"
        :league-emoji="props.league.emoji"
        :role-label="props.role.label"
        active-module="juego"
        :can-manage-league="props.role.can_manage"
    >
        <section class="app-surface space-y-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <p class="app-kicker text-[#E5B849]">Juego actual</p>
                    <h1 class="app-display app-module-title text-[#F8FAFC]">
                        Jornada en cancha
                    </h1>
                    <p class="text-[14px] leading-7 text-[#94A3B8]">
                        Administra el draft inicial, la anotacion, las salidas de jugadores y el cierre de cada juego de la jornada.
                    </p>
                </div>

                <div class="grid gap-2 rounded-[18px] border border-white/6 bg-[#0E1628] p-4 text-[12px] text-[#94A3B8]">
                    <div class="flex items-center gap-2">
                        <Trophy class="size-4 text-[#E5B849]" />
                        <span>{{ props.game.summary.games }} juegos terminados</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Waves class="size-4 text-[#4ADE80]" />
                        <span>{{ props.game.summary.streak_label }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="size-4 text-[#E5B849]" />
                        <span>{{ formatMoney(props.game.summary.cash_collected_cents) }} cobrados</span>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="props.game.state === 'draft'"
            class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_320px]"
        >
            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Draft pendiente</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Selecciona como quieres repartir los 10 jugadores disponibles antes de abrir el juego.
                        </p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.game.draft.entries.length }} listos
                    </span>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <button
                        type="button"
                        class="min-h-12 rounded-[14px] border px-4 text-sm font-semibold"
                        :class="draftMode === 'auto' ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'"
                        @click="draftMode = 'auto'"
                    >
                        Auto por scout
                    </button>
                    <button
                        type="button"
                        class="min-h-12 rounded-[14px] border px-4 text-sm font-semibold"
                        :class="draftMode === 'arrival' ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'"
                        @click="draftMode = 'arrival'"
                    >
                        Por llegada
                    </button>
                    <button
                        type="button"
                        class="min-h-12 rounded-[14px] border px-4 text-sm font-semibold"
                        :class="draftMode === 'manual' ? 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'"
                        @click="draftMode = 'manual'"
                    >
                        Manual
                    </button>
                </div>

                <div class="grid gap-3">
                    <div
                        v-for="entry in props.game.draft.entries"
                        :key="entry.id"
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#F8FAFC]">
                                    {{ entry.name }}
                                </p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">
                                    Llegada #{{ entry.arrival_order }}{{ entry.jersey_number ? ` - #${entry.jersey_number}` : '' }}
                                </p>
                            </div>

                            <div
                                v-if="draftMode === 'manual'"
                                class="flex gap-2"
                            >
                                <button
                                    type="button"
                                    class="size-10 rounded-full border text-sm font-semibold"
                                    :class="manualAssignments[entry.id] === 'A' ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]' : 'border-white/6 bg-[#131B2F] text-[#94A3B8]'"
                                    @click="setAssignment(entry.id, 'A')"
                                >
                                    A
                                </button>
                                <button
                                    type="button"
                                    class="size-10 rounded-full border text-sm font-semibold"
                                    :class="manualAssignments[entry.id] === 'B' ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]' : 'border-white/6 bg-[#131B2F] text-[#94A3B8]'"
                                    @click="setAssignment(entry.id, 'B')"
                                >
                                    B
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <p class="app-kicker text-[#E5B849]">Confirmacion</p>
                <p class="text-[13px] leading-6 text-[#94A3B8]">
                    El juego se abre al confirmar el reparto. Luego podras agregar puntos, corregir jugadas y cerrar el marcador.
                </p>
                <div
                    v-if="draftMode === 'manual'"
                    class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4 text-[13px] text-[#94A3B8]"
                >
                    Equipo A: {{ teamACount }} / 5
                    <br>
                    Equipo B: {{ teamBCount }} / 5
                </div>
                <Button
                    type="button"
                    class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                    :disabled="draftMode === 'manual' && (teamACount !== 5 || teamBCount !== 5)"
                    @click="submitDraft"
                >
                    Confirmar draft
                </Button>
            </article>
        </section>

        <section
            v-else-if="props.game.current"
            class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]"
        >
            <article class="app-surface space-y-5">
                <div class="relative overflow-hidden rounded-[24px] border border-white/6 bg-[radial-gradient(circle_at_top,_rgba(229,184,73,0.14),_rgba(14,22,40,0.98)_48%),linear-gradient(180deg,_rgba(19,27,47,0.98),_rgba(10,15,29,1))] p-5">
                    <div class="grid gap-5 md:grid-cols-[1fr_auto_1fr] md:items-center">
                        <div class="text-center md:text-left">
                            <p class="app-kicker text-[#4ADE80]">Equipo A</p>
                            <p
                                class="scoreboard-value scoreboard-value--a"
                                :class="{ 'scoreboard-value--bump': scoreBumpSide === 'A' }"
                            >
                                {{ props.game.current.score.team_a }}
                            </p>
                        </div>
                        <div class="flex flex-col items-center justify-center gap-3 text-center">
                            <span class="rounded-full border border-white/8 bg-[#131B2F]/90 px-4 py-2 text-[12px] font-semibold uppercase tracking-[0.28em] text-[#94A3B8]">
                                Juego #{{ props.game.current.game_number }}
                            </span>
                            <div class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full border border-white/8 bg-[#131B2F]/90">
                                <span class="app-display text-[28px] text-[#94A3B8]">Vs</span>
                            </div>
                            <p class="text-[12px] uppercase tracking-[0.28em] text-[#94A3B8]">{{ streakLabel }}</p>
                        </div>
                        <div class="text-center md:text-right">
                            <p class="app-kicker text-[#E5B849]">Equipo B</p>
                            <p
                                class="scoreboard-value scoreboard-value--b"
                                :class="{ 'scoreboard-value--bump': scoreBumpSide === 'B' }"
                            >
                                {{ props.game.current.score.team_b }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="scoreFlash"
                    :key="scoreFlash.key"
                    class="score-flash"
                    :class="scoreFlash.side === 'A' ? 'score-flash--a' : 'score-flash--b'"
                >
                    {{ scoreFlash.label }}
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <article class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#4ADE80]">Equipo A</p>
                            <button
                                v-if="canManage"
                                type="button"
                                class="min-h-10 rounded-[10px] border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-3 text-xs font-semibold text-[#4ADE80]"
                                @click="addTeamPoint('A')"
                            >
                                +1 al equipo
                            </button>
                        </div>
                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="player in props.game.current.team_a"
                                :key="player.id"
                                class="rounded-[14px] border border-white/6 bg-[#131B2F] p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ player.name }}</p>
                                        <p class="mt-1 text-[12px] text-[#94A3B8]">
                                            {{ player.points }} pts · 1P: {{ player.shots[1] }} · 2P: {{ player.shots[2] }} · 3P: {{ player.shots[3] }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]"
                                            @click="selectedPlayer = player"
                                        >
                                            +
                                        </button>
                                        <button
                                            v-if="canManage && player.points > 0"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]"
                                            @click="revertPlayer = player"
                                        >
                                            <RotateCcw class="mx-auto size-4" />
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#F87171]"
                                            @click="openRemovePlayerModal(player)"
                                        >
                                            <UserMinus class="mx-auto size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#E5B849]">Equipo B</p>
                            <button
                                v-if="canManage"
                                type="button"
                                class="min-h-10 rounded-[10px] border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-3 text-xs font-semibold text-[#E5B849]"
                                @click="addTeamPoint('B')"
                            >
                                +1 al equipo
                            </button>
                        </div>
                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="player in props.game.current.team_b"
                                :key="player.id"
                                class="rounded-[14px] border border-white/6 bg-[#131B2F] p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ player.name }}</p>
                                        <p class="mt-1 text-[12px] text-[#94A3B8]">
                                            {{ player.points }} pts · 1P: {{ player.shots[1] }} · 2P: {{ player.shots[2] }} · 3P: {{ player.shots[3] }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]"
                                            @click="selectedPlayer = player"
                                        >
                                            +
                                        </button>
                                        <button
                                            v-if="canManage && player.points > 0"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#E5B849]"
                                            @click="revertPlayer = player"
                                        >
                                            <RotateCcw class="mx-auto size-4" />
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="size-10 rounded-full border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#F87171]"
                                            @click="openRemovePlayerModal(player)"
                                        >
                                            <UserMinus class="mx-auto size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </article>

            <div class="grid gap-4">
                <article class="app-surface space-y-3">
                    <p class="app-kicker text-[#E5B849]">Acciones</p>
                    <Button
                        v-if="canManage"
                        type="button"
                        class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        @click="finishDialogOpen = true"
                    >
                        Marcar fin de juego
                    </Button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[12px] border border-white/6 bg-[#131B2F] px-4 text-sm font-semibold text-[#F8FAFC]"
                        @click="undoLastAction"
                    >
                        <RotateCcw class="size-4" />
                        Deshacer ultima accion
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[12px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-4 text-sm font-semibold text-[#FCA5A5]"
                        @click="resetCurrentGame"
                    >
                        <SearchSlash class="size-4" />
                        Limpiar juego actual
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[12px] border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-4 text-sm font-semibold text-[#F8FAFC]"
                        @click="endSession"
                    >
                        <Trophy class="size-4 text-[#E5B849]" />
                        Dar fin a la jornada
                    </button>
                </article>

                <article class="app-surface space-y-3">
                    <p class="app-kicker text-[#E5B849]">Historial</p>
                    <div
                        v-if="props.game.history.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-[13px] text-[#94A3B8]"
                    >
                        Sin juegos finalizados todavia.
                    </div>
                    <div v-else class="grid gap-3">
                        <div
                            v-for="row in props.game.history"
                            :key="row.id"
                            class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-[#F8FAFC]">Juego #{{ row.game_number }}</p>
                                <span class="rounded-full border border-white/6 bg-[#131B2F] px-3 py-1 text-[11px] text-[#94A3B8]">
                                    {{ row.score }}
                                </span>
                            </div>
                            <p class="mt-2 text-[12px] leading-6 text-[#94A3B8]">{{ row.summary }}</p>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section
            v-else
            class="app-surface flex min-h-[320px] flex-col items-center justify-center gap-4 text-center"
        >
            <div class="flex size-16 items-center justify-center rounded-full border border-white/6 bg-[#0E1628]">
                <Swords class="size-7 text-[#E5B849]" />
            </div>
            <div class="space-y-2">
                <p class="app-kicker text-[#E5B849]">Sin juego activo</p>
                <p class="text-[14px] leading-7 text-[#94A3B8]">
                    Prepara la jornada desde Llegada o confirma el draft pendiente para empezar el primer juego.
                </p>
            </div>
        </section>

        <Dialog :open="selectedPlayer !== null" @update:open="selectedPlayer = null">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader>
                    <DialogTitle class="app-display text-[30px]">{{ selectedPlayer?.name }}</DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Selecciona cuantos puntos quieres agregarle a este jugador.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Button type="button" class="min-h-16 rounded-[14px] bg-[#4ADE80] text-[#0A0F1D] hover:bg-[#67e38f]" @click="addPlayerPoint(1)">+1</Button>
                    <Button type="button" class="min-h-16 rounded-[14px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]" @click="addPlayerPoint(2)">+2</Button>
                    <Button type="button" class="min-h-16 rounded-[14px] bg-[#F97316] text-[#0A0F1D] hover:bg-[#fb8b3a]" @click="addPlayerPoint(3)">+3</Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="revertPlayer !== null" @update:open="revertPlayer = null">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader>
                    <DialogTitle class="app-display text-[30px]">{{ revertPlayer?.name }}</DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Revierte una jugada registrada en el juego actual.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Button type="button" variant="secondary" class="min-h-14 rounded-[14px] border border-white/8 bg-[#131B2F]" :disabled="!revertPlayer || revertPlayer.shots[1] < 1" @click="revertPlayerPoint(1)">-1</Button>
                    <Button type="button" variant="secondary" class="min-h-14 rounded-[14px] border border-white/8 bg-[#131B2F]" :disabled="!revertPlayer || revertPlayer.shots[2] < 1" @click="revertPlayerPoint(2)">-2</Button>
                    <Button type="button" variant="secondary" class="min-h-14 rounded-[14px] border border-white/8 bg-[#131B2F]" :disabled="!revertPlayer || revertPlayer.shots[3] < 1" @click="revertPlayerPoint(3)">-3</Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="playerToRemove !== null" @update:open="playerToRemove = null">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-3 app-display text-[30px]">
                        <UserMinus class="size-5 text-[#F87171]" />
                        <span>Jugador se va</span>
                    </DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Confirma la salida de {{ playerToRemove?.name }}. Si hay cola activa, el siguiente jugador entrara segun la prioridad de la jornada.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="grid gap-2 sm:grid-cols-2">
                    <Button type="button" class="min-h-12 rounded-[12px] bg-[#F87171] text-[#0A0F1D] hover:bg-[#fb8b8b]" @click="confirmRemovePlayer">Si, se fue</Button>
                    <Button type="button" variant="secondary" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F]" @click="playerToRemove = null">Cancelar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="finishDialogOpen" @update:open="finishDialogOpen = false">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader>
                    <DialogTitle class="app-display text-[30px]">Fin de juego</DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Confirma el ganador para aplicar la rotacion de cola y abrir el siguiente juego.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="grid gap-2 sm:grid-cols-3">
                    <Button type="button" class="min-h-12 rounded-[12px] bg-[#4ADE80] text-[#0A0F1D]" @click="finishGame('A')">Gano A</Button>
                    <Button type="button" class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D]" @click="finishGame('B')">Gano B</Button>
                    <Button type="button" variant="secondary" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F]" @click="finishGame()">Automatico</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueShellLayout>
</template>

<style scoped>
.scoreboard-value {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(4.5rem, 14vw, 6.25rem);
    line-height: 1;
}

.scoreboard-value--a {
    color: #4ade80;
}

.scoreboard-value--b {
    color: #e5b849;
}

.scoreboard-value--bump {
    animation: scoreboard-bump 0.18s ease-out;
}

.score-flash {
    position: fixed;
    top: 38%;
    left: 50%;
    z-index: 70;
    transform: translate(-50%, -50%);
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(4.25rem, 14vw, 5.75rem);
    line-height: 1;
    pointer-events: none;
    animation: score-flash 0.5s forwards;
}

.score-flash--a {
    color: #4ade80;
}

.score-flash--b {
    color: #e5b849;
}

@keyframes score-flash {
    0% {
        opacity: 0.9;
        transform: translate(-50%, -50%) scale(1);
    }

    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(2.4);
    }
}

@keyframes scoreboard-bump {
    0%,
    100% {
        transform: scale(1);
    }

    40% {
        transform: scale(1.18);
    }
}
</style>
