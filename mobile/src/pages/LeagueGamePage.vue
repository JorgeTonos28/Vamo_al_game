<script setup lang="ts">
import {
    IonContent,
    IonPage,
    IonRefresher,
    IonRefresherContent,
    onIonViewWillEnter,
} from '@ionic/vue';
import { Flame, RotateCcw, Trophy } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import { extractApiError } from '@/services/api';
import {
    addLeaguePlayerPoint,
    addLeagueTeamPoint,
    configureLeagueGameClock,
    draftLeagueGame,
    endLeagueSession,
    fetchLeagueGame,
    finishLeagueGame,
    pauseLeagueGameClock,
    removeLeagueGamePlayer,
    resetLeagueGame,
    resetLeagueGameClock,
    revertLeaguePlayerPoint,
    startLeagueGameClock,
    
    
    undoLeagueGameAction
} from '@/services/league';
import type {LeagueGamePayload, LeagueTeamPlayer} from '@/services/league';

type TeamSide = 'A' | 'B';

type ScoreFlashState = {
    key: number;
    label: string;
    side: TeamSide;
};

type RotationNotice = NonNullable<LeagueGamePayload['game']['rotation_notice']>;
type DraftAlert = { title: string; body: string[] };

const payload = ref<LeagueGamePayload | null>(null);
const isLoading = ref(false);
const draftMode = ref<'auto' | 'arrival' | 'manual'>('auto');
const manualAssignments = reactive<Record<number, 'A' | 'B'>>({});
const selectedPlayer = ref<LeagueTeamPlayer | null>(null);
const revertPlayer = ref<LeagueTeamPlayer | null>(null);
const playerToRemove = ref<LeagueTeamPlayer | null>(null);
const scoreFlash = ref<ScoreFlashState | null>(null);
const scoreBumpSide = ref<TeamSide | null>(null);
const actionError = ref('');
const rotationNotice = ref<RotationNotice | null>(null);
const draftAlert = ref<DraftAlert | null>(null);
const clockForm = reactive({ minutes: '20', seconds: '00' });
const clockDisplaySeconds = ref<number | null>(null);
let lastRotationNoticeKey: string | null = null;

const canManage = computed(() => payload.value?.role.can_manage ?? false);
const teamACount = computed(
    () =>
        Object.values(manualAssignments).filter((team) => team === 'A').length,
);
const teamBCount = computed(
    () =>
        Object.values(manualAssignments).filter((team) => team === 'B').length,
);
const streakLabel = computed(() => {
    const streak = payload.value?.game.current?.streak;

    return streak?.team ? `EQ.${streak.team} - ${streak.count}` : 'Sin racha';
});
const clockState = computed(
    () => payload.value?.game.clock.state ?? 'unconfigured',
);
const clockDurationSeconds = computed(
    () => payload.value?.game.clock.duration_seconds ?? null,
);
const formattedClock = computed(() => {
    if (clockDisplaySeconds.value === null) {
return '--:--';
}

    const minutes = Math.floor(clockDisplaySeconds.value / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (clockDisplaySeconds.value % 60)
        .toString()
        .padStart(2, '0');

    return `${minutes}:${seconds}`;
});
const clockActionLabel = computed(() => {
    if (clockState.value === 'running') {
return 'Pausar';
}

    if (
        clockDurationSeconds.value !== null &&
        clockDisplaySeconds.value !== null &&
        clockDisplaySeconds.value < clockDurationSeconds.value &&
        clockDisplaySeconds.value > 0
    ) {
        return 'Reanudar';
    }

    return 'Iniciar';
});
let scoreFeedbackNonce = 0;
let scoreFlashTimer: ReturnType<typeof setTimeout> | null = null;
let scoreBumpTimer: ReturnType<typeof setTimeout> | null = null;
let clockTicker: ReturnType<typeof setInterval> | null = null;

watch(
    () => payload.value?.game.rotation_notice,
    (notice) => {
        if (notice === null || notice === undefined) {
            rotationNotice.value = null;

            return;
        }

        if (notice.key !== lastRotationNoticeKey) {
            rotationNotice.value = notice;
            lastRotationNoticeKey = notice.key;
        }
    },
    { immediate: true },
);

watch(
    () => payload.value?.game.draft.entries.map((entry) => entry.id),
    (entryIds) => {
        const activeIds = new Set(entryIds ?? []);

        Object.keys(manualAssignments).forEach((entryId) => {
            if (!activeIds.has(Number(entryId))) {
                delete manualAssignments[Number(entryId)];
            }
        });
    },
    { immediate: true },
);

watch(
    () => payload.value?.game.clock,
    (clock) => {
        const durationSeconds = clock?.duration_seconds ?? 1200;
        clockForm.minutes = String(Math.floor(durationSeconds / 60)).padStart(
            2,
            '0',
        );
        clockForm.seconds = String(durationSeconds % 60).padStart(2, '0');
        clockDisplaySeconds.value = clock?.remaining_seconds ?? null;

        if (clockTicker !== null) {
            clearInterval(clockTicker);
            clockTicker = null;
        }

        if (clock?.state === 'running' && clock.remaining_seconds !== null) {
            clockTicker = setInterval(() => {
                if (
                    clockDisplaySeconds.value === null ||
                    clockDisplaySeconds.value <= 0
                ) {
                    if (clockTicker !== null) {
                        clearInterval(clockTicker);
                        clockTicker = null;
                    }

                    clockDisplaySeconds.value = 0;

                    return;
                }

                clockDisplaySeconds.value -= 1;
            }, 1000);
        }
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => {
    if (scoreFlashTimer !== null) {
clearTimeout(scoreFlashTimer);
}

    if (scoreBumpTimer !== null) {
clearTimeout(scoreBumpTimer);
}

    if (clockTicker !== null) {
clearInterval(clockTicker);
}
});

function teamSideForPlayer(entryId: number): TeamSide | null {
    if (
        payload.value?.game.current?.team_a.some(
            (player) => player.id === entryId,
        )
    ) {
return 'A';
}

    if (
        payload.value?.game.current?.team_b.some(
            (player) => player.id === entryId,
        )
    ) {
return 'B';
}

    return null;
}

function triggerScoreFeedback(teamSide: TeamSide, points: number): void {
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

async function loadPage(): Promise<void> {
    isLoading.value = true;

    try {
        payload.value = await fetchLeagueGame();
    } finally {
        isLoading.value = false;
    }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
    try {
        await loadPage();
    } finally {
        await (event.target as HTMLIonRefresherElement).complete();
    }
}

onIonViewWillEnter(loadPage);

function setAssignment(entryId: number, team: 'A' | 'B'): void {
    const currentTeam = manualAssignments[entryId];

    if (currentTeam === team) {
        return;
    }

    const nextCount =
        Object.entries(manualAssignments).reduce(
            (count, [currentEntryId, assignedTeam]) => {
                if (Number(currentEntryId) === entryId) {
                    return count;
                }

                return assignedTeam === team ? count + 1 : count;
            },
            0,
        ) + 1;

    if (nextCount > 5) {
        draftAlert.value = {
            title: `Equipo ${team} completo`,
            body: [
                `El Equipo ${team} ya tiene 5 integrantes asignados.`,
                'Mueve a otro jugador antes de intentar agregar uno más.',
            ],
        };

        return;
    }

    manualAssignments[entryId] = team;
}

async function submitDraft(): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    if (draftMode.value === 'manual') {
        const entries = payload.value?.game.draft.entries ?? [];
        const unassignedEntries = entries.filter(
            (entry) => !manualAssignments[entry.id],
        );

        if (unassignedEntries.length > 0) {
            draftAlert.value = {
                title: 'Draft manual incompleto',
                body: [
                    'Todos los integrantes deben quedar asignados antes de confirmar el draft.',
                    `Faltan ${unassignedEntries.length} integrante(s) por ubicar en un equipo.`,
                ],
            };

            return;
        }

        if (teamACount.value !== 5 || teamBCount.value !== 5) {
            draftAlert.value = {
                title: 'Equipos incompletos',
                body: [
                    'Cada equipo debe tener exactamente 5 integrantes.',
                    `Equipo A: ${teamACount.value}/5. Equipo B: ${teamBCount.value}/5.`,
                ],
            };

            return;
        }
    }

    try {
        payload.value = await draftLeagueGame(
            draftMode.value === 'manual'
                ? { mode: draftMode.value, assignments: manualAssignments }
                : { mode: draftMode.value },
        );
    } catch (error) {
        actionError.value = extractApiError(error);

        if (draftMode.value === 'manual') {
            draftAlert.value = {
                title: 'No se pudo confirmar el draft',
                body: [actionError.value],
            };
        }
    }
}

async function addTeamPoint(teamSide: TeamSide): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await addLeagueTeamPoint(teamSide);
        triggerScoreFeedback(teamSide, 1);
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function addPlayerPoint(points: 1 | 2 | 3): Promise<void> {
    if (!selectedPlayer.value || !canManage.value) {
return;
}

    const teamSide = teamSideForPlayer(selectedPlayer.value.id);
    actionError.value = '';

    try {
        payload.value = await addLeaguePlayerPoint(
            selectedPlayer.value.id,
            points,
        );

        if (teamSide !== null) {
triggerScoreFeedback(teamSide, points);
}

        selectedPlayer.value = null;
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function revertPlayerPoint(points: 1 | 2 | 3): Promise<void> {
    if (!revertPlayer.value || !canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await revertLeaguePlayerPoint(
            revertPlayer.value.id,
            points,
        );
        revertPlayer.value = null;
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

function openRemovePlayerModal(player: LeagueTeamPlayer): void {
    if (!canManage.value) {
return;
}

    playerToRemove.value = player;
}

async function confirmRemovePlayer(): Promise<void> {
    if (!canManage.value || !playerToRemove.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await removeLeagueGamePlayer(playerToRemove.value.id);
        playerToRemove.value = null;
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function undoAction(): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await undoLeagueGameAction();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function finishGame(): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await finishLeagueGame();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function endSession(): Promise<void> {
    if (!canManage.value || !window.confirm('¿Cerrar la jornada del día?')) {
return;
}

    actionError.value = '';

    try {
        payload.value = await endLeagueSession();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function resetGame(): Promise<void> {
    if (
        !canManage.value ||
        !window.confirm('¿Limpiar por completo el juego actual?')
    ) {
return;
}

    actionError.value = '';

    try {
        payload.value = await resetLeagueGame();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

function parsedClockDuration(): number | null {
    const minutes = Number(clockForm.minutes || 0);
    const seconds = Number(clockForm.seconds || 0);

    if (!Number.isFinite(minutes) || !Number.isFinite(seconds)) {
return null;
}

    const total =
        Math.max(0, Math.floor(minutes)) * 60 +
        Math.max(0, Math.floor(seconds));

    return total > 0 ? total : null;
}

async function saveClockDuration(): Promise<void> {
    if (!canManage.value) {
return;
}

    const total = parsedClockDuration();

    if (total === null) {
        actionError.value = 'Configura un tiempo válido para el cronómetro.';

        return;
    }

    actionError.value = '';

    try {
        payload.value = await configureLeagueGameClock(total);
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function toggleClock(): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value =
            clockState.value === 'running'
                ? await pauseLeagueGameClock()
                : await startLeagueGameClock();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

async function resetClock(): Promise<void> {
    if (!canManage.value) {
return;
}

    actionError.value = '';

    try {
        payload.value = await resetLeagueGameClock();
    } catch (error) {
        actionError.value = extractApiError(error);
    }
}

function rotationNoticeIcon(icon: string) {
    if (icon === 'trophy') {
return Trophy;
}

    if (icon === 'flame') {
return Flame;
}

    return RotateCcw;
}
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <template v-slot:fixed>
<IonRefresher  @ionRefresh="handleRefresh">
                <IonRefresherContent
                    pulling-text="Desliza para refrescar"
                    refreshing-spinner="crescent"
                />
            </IonRefresher>
</template>

            <div class="mobile-shell">
                <div class="mobile-stack">
                    <MobileAppTopbar
                        :title="payload?.league.name ?? 'Juego'"
                        description="Draft, marcador, historial y cierre de la jornada."
                    />

                    <section class="app-surface section-stack">
                        <div class="section-head">
                            <div>
                                <p class="app-kicker section-kicker">
                                    Juego actual
                                </p>
                                <p class="section-title">Jornada en cancha</p>
                            </div>
                            <div class="summary-pills">
                                <span class="member-chip member-chip--warning"
                                    >{{
                                        payload?.game.summary.games ?? 0
                                    }}
                                    juegos</span
                                >
                                <span
                                    class="member-chip member-chip--positive"
                                    >{{ streakLabel }}</span
                                >
                            </div>
                        </div>
                        <p class="body-copy">
                            Administra el draft inicial, la anotación, las
                            salidas y el cierre de cada juego.
                        </p>
                    </section>

                    <section
                        v-if="isLoading && !payload"
                        class="app-surface section-stack"
                    >
                        <p class="body-copy">Cargando juego...</p>
                    </section>

                    <template v-else-if="payload?.game.state === 'draft'">
                        <section class="app-surface section-stack">
                            <div class="section-head">
                                <div>
                                    <p class="app-kicker section-kicker">
                                        Draft pendiente
                                    </p>
                                    <p class="section-title">
                                        {{
                                            payload?.game.draft.entries
                                                .length ?? 0
                                        }}
                                        listos
                                    </p>
                                </div>
                            </div>

                            <div class="action-grid action-grid--three">
                                <button
                                    class="action-button"
                                    :class="
                                        draftMode === 'auto'
                                            ? 'action-button--primary'
                                            : 'action-button--secondary'
                                    "
                                    type="button"
                                    @click="draftMode = 'auto'"
                                >
                                    Auto
                                </button>
                                <button
                                    class="action-button"
                                    :class="
                                        draftMode === 'arrival'
                                            ? 'action-button--warning'
                                            : 'action-button--secondary'
                                    "
                                    type="button"
                                    @click="draftMode = 'arrival'"
                                >
                                    Llegada
                                </button>
                                <button
                                    class="action-button"
                                    :class="
                                        draftMode === 'manual'
                                            ? 'action-button--danger'
                                            : 'action-button--secondary'
                                    "
                                    type="button"
                                    @click="draftMode = 'manual'"
                                >
                                    Manual
                                </button>
                            </div>

                            <article
                                v-for="entry in payload?.game.draft.entries ??
                                []"
                                :key="entry.id"
                                class="data-row"
                            >
                                <div>
                                    <p class="data-row__name">
                                        {{ entry.name }}
                                    </p>
                                    <p class="body-copy">
                                        Llegada #{{ entry.arrival_order
                                        }}<span
                                            v-if="entry.jersey_number !== null"
                                        >
                                            · #{{ entry.jersey_number }}</span
                                        >
                                    </p>
                                </div>
                                <div
                                    v-if="draftMode === 'manual'"
                                    class="draft-actions"
                                >
                                    <button
                                        class="member-chip"
                                        :class="
                                            manualAssignments[entry.id] === 'A'
                                                ? 'member-chip--positive'
                                                : 'member-chip--neutral'
                                        "
                                        type="button"
                                        @click="setAssignment(entry.id, 'A')"
                                    >
                                        A
                                    </button>
                                    <button
                                        class="member-chip"
                                        :class="
                                            manualAssignments[entry.id] === 'B'
                                                ? 'member-chip--warning'
                                                : 'member-chip--neutral'
                                        "
                                        type="button"
                                        @click="setAssignment(entry.id, 'B')"
                                    >
                                        B
                                    </button>
                                </div>
                            </article>

                            <div
                                v-if="draftMode === 'manual'"
                                class="summary-grid summary-grid--two"
                            >
                                <article class="summary-card">
                                    <p class="app-kicker">Equipo A</p>
                                    <p class="summary-card__value">
                                        {{ teamACount }}/5
                                    </p>
                                </article>
                                <article class="summary-card">
                                    <p class="app-kicker">Equipo B</p>
                                    <p class="summary-card__value">
                                        {{ teamBCount }}/5
                                    </p>
                                </article>
                            </div>

                            <button
                                class="action-button action-button--warning"
                                type="button"
                                @click="submitDraft"
                            >
                                Confirmar draft
                            </button>
                        </section>
                    </template>

                    <template v-else-if="payload?.game.current">
                        <section class="app-surface section-stack">
                            <div v-if="actionError" class="error-banner">
                                {{ actionError }}
                            </div>

                            <div class="clock-card">
                                <div>
                                    <p class="app-kicker section-kicker">
                                        Cronómetro
                                    </p>
                                    <p class="clock-card__value">
                                        {{ formattedClock }}
                                    </p>
                                    <p class="body-copy">
                                        {{
                                            clockState === 'running'
                                                ? 'Corriendo'
                                                : clockState === 'finished'
                                                  ? 'Tiempo agotado'
                                                  : clockState ===
                                                      'unconfigured'
                                                    ? 'Sin configurar'
                                                    : 'Listo'
                                        }}
                                    </p>
                                </div>
                                <div class="clock-card__controls">
                                    <label class="clock-field"
                                        ><span class="clock-field__label"
                                            >Minutos</span
                                        ><input
                                            v-model="clockForm.minutes"
                                            type="number"
                                            min="0"
                                            max="120"
                                            class="sheet-input"
                                            placeholder="20"
                                    /></label>
                                    <label class="clock-field"
                                        ><span class="clock-field__label"
                                            >Segundos</span
                                        ><input
                                            v-model="clockForm.seconds"
                                            type="number"
                                            min="0"
                                            max="59"
                                            class="sheet-input"
                                            placeholder="00"
                                    /></label>
                                    <button
                                        v-if="canManage"
                                        class="action-button action-button--secondary"
                                        type="button"
                                        @click="saveClockDuration"
                                    >
                                        Cargar
                                    </button>
                                    <button
                                        v-if="canManage"
                                        class="action-button action-button--warning"
                                        type="button"
                                        @click="toggleClock"
                                    >
                                        {{ clockActionLabel }}
                                    </button>
                                    <button
                                        v-if="canManage"
                                        class="action-button action-button--secondary"
                                        type="button"
                                        @click="resetClock"
                                    >
                                        Reiniciar
                                    </button>
                                </div>
                            </div>

                            <div class="scoreboard">
                                <div class="scoreboard__team">
                                    <p class="app-kicker">Equipo A</p>
                                    <p
                                        class="scoreboard__value scoreboard__value--green"
                                        :class="{
                                            'scoreboard__value--bump':
                                                scoreBumpSide === 'A',
                                        }"
                                    >
                                        {{ payload.game.current.score.team_a }}
                                    </p>
                                </div>
                                <div class="scoreboard__center">
                                    <span class="scoreboard__badge"
                                        >Juego #{{
                                            payload.game.current.game_number
                                        }}</span
                                    >
                                    <div class="scoreboard__vs">Vs</div>
                                    <p class="scoreboard__streak">
                                        {{ streakLabel }}
                                    </p>
                                </div>
                                <div class="scoreboard__team">
                                    <p class="app-kicker">Equipo B</p>
                                    <p
                                        class="scoreboard__value scoreboard__value--gold"
                                        :class="{
                                            'scoreboard__value--bump':
                                                scoreBumpSide === 'B',
                                        }"
                                    >
                                        {{ payload.game.current.score.team_b }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <div
                            v-if="scoreFlash"
                            :key="scoreFlash.key"
                            class="score-flash"
                            :class="
                                scoreFlash.side === 'A'
                                    ? 'score-flash--a'
                                    : 'score-flash--b'
                            "
                        >
                            {{ scoreFlash.label }}
                        </div>

                        <section class="team-card team-card--a">
                            <div class="section-head">
                                <div>
                                    <p
                                        class="app-kicker section-kicker team-a-copy"
                                    >
                                        Equipo A
                                    </p>
                                    <p class="body-copy">Jugadores en cancha</p>
                                </div>
                                <button
                                    v-if="canManage"
                                    class="member-chip member-chip--positive"
                                    type="button"
                                    @click="addTeamPoint('A')"
                                >
                                    +1 equipo
                                </button>
                            </div>
                            <article
                                v-for="player in payload.game.current.team_a"
                                :key="player.id"
                                class="data-row"
                            >
                                <div>
                                    <p class="data-row__name">
                                        {{ player.name }}
                                    </p>
                                    <p class="body-copy">
                                        {{ player.points }} pts · 1P
                                        {{ player.shots[1] }} · 2P
                                        {{ player.shots[2] }} · 3P
                                        {{ player.shots[3] }}
                                    </p>
                                </div>
                                <div class="player-actions">
                                    <button
                                        v-if="canManage"
                                        class="member-chip member-chip--positive"
                                        type="button"
                                        @click="selectedPlayer = player"
                                    >
                                        +
                                    </button>
                                    <button
                                        v-if="canManage && player.points > 0"
                                        class="member-chip member-chip--warning"
                                        type="button"
                                        @click="revertPlayer = player"
                                    >
                                        Revertir
                                    </button>
                                    <button
                                        v-if="canManage"
                                        class="member-chip member-chip--negative"
                                        type="button"
                                        @click="openRemovePlayerModal(player)"
                                    >
                                        Salida
                                    </button>
                                </div>
                            </article>
                        </section>

                        <section class="team-card team-card--b">
                            <div class="section-head">
                                <div>
                                    <p
                                        class="app-kicker section-kicker team-b-copy"
                                    >
                                        Equipo B
                                    </p>
                                    <p class="body-copy">Jugadores en cancha</p>
                                </div>
                                <button
                                    v-if="canManage"
                                    class="member-chip member-chip--warning"
                                    type="button"
                                    @click="addTeamPoint('B')"
                                >
                                    +1 equipo
                                </button>
                            </div>
                            <article
                                v-for="player in payload.game.current.team_b"
                                :key="player.id"
                                class="data-row"
                            >
                                <div>
                                    <p class="data-row__name">
                                        {{ player.name }}
                                    </p>
                                    <p class="body-copy">
                                        {{ player.points }} pts · 1P
                                        {{ player.shots[1] }} · 2P
                                        {{ player.shots[2] }} · 3P
                                        {{ player.shots[3] }}
                                    </p>
                                </div>
                                <div class="player-actions">
                                    <button
                                        v-if="canManage"
                                        class="member-chip member-chip--warning"
                                        type="button"
                                        @click="selectedPlayer = player"
                                    >
                                        +
                                    </button>
                                    <button
                                        v-if="canManage && player.points > 0"
                                        class="member-chip member-chip--warning"
                                        type="button"
                                        @click="revertPlayer = player"
                                    >
                                        Revertir
                                    </button>
                                    <button
                                        v-if="canManage"
                                        class="member-chip member-chip--negative"
                                        type="button"
                                        @click="openRemovePlayerModal(player)"
                                    >
                                        Salida
                                    </button>
                                </div>
                            </article>
                        </section>

                        <section class="app-surface section-stack">
                            <p class="app-kicker section-kicker">Acciones</p>
                            <div class="action-grid">
                                <button
                                    v-if="canManage"
                                    class="action-button action-button--warning"
                                    type="button"
                                    @click="finishGame"
                                >
                                    Marcar fin de juego
                                </button>
                                <button
                                    v-if="canManage"
                                    class="action-button action-button--secondary"
                                    type="button"
                                    @click="undoAction"
                                >
                                    Deshacer última acción
                                </button>
                                <button
                                    v-if="canManage"
                                    class="action-button action-button--danger"
                                    type="button"
                                    @click="resetGame"
                                >
                                    Limpiar juego actual
                                </button>
                                <button
                                    v-if="canManage"
                                    class="action-button action-button--primary"
                                    type="button"
                                    @click="endSession"
                                >
                                    Dar fin a la jornada
                                </button>
                            </div>
                        </section>

                        <section class="app-surface section-stack">
                            <p class="app-kicker section-kicker">Historial</p>
                            <p
                                v-if="payload.game.history.length === 0"
                                class="body-copy"
                            >
                                Sin juegos finalizados todavía.
                            </p>
                            <article
                                v-for="row in payload.game.history"
                                :key="row.id"
                                class="data-row"
                            >
                                <div>
                                    <p class="data-row__name">
                                        Juego #{{ row.game_number }}
                                    </p>
                                    <p class="body-copy">{{ row.summary }}</p>
                                </div>
                                <span
                                    class="member-chip member-chip--neutral"
                                    >{{ row.score }}</span
                                >
                            </article>
                        </section>
                    </template>

                    <section v-else class="app-surface section-stack">
                        <p class="app-kicker section-kicker">
                            Sin juego activo
                        </p>
                        <p class="body-copy">
                            Prepara la jornada desde Llegada o confirma el draft
                            pendiente para abrir el primer juego.
                        </p>
                    </section>
                </div>
            </div>

            <div
                v-if="selectedPlayer !== null"
                class="overlay"
                @click.self="selectedPlayer = null"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">
                        {{ selectedPlayer?.name }}
                    </p>
                    <p class="body-copy">
                        Selecciona cuántos puntos quieres agregarle a este
                        jugador.
                    </p>
                    <div class="action-grid action-grid--three">
                        <button
                            class="action-button action-button--primary"
                            type="button"
                            @click="addPlayerPoint(1)"
                        >
                            +1
                        </button>
                        <button
                            class="action-button action-button--warning"
                            type="button"
                            @click="addPlayerPoint(2)"
                        >
                            +2
                        </button>
                        <button
                            class="action-button action-button--danger"
                            type="button"
                            @click="addPlayerPoint(3)"
                        >
                            +3
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="revertPlayer !== null"
                class="overlay"
                @click.self="revertPlayer = null"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">
                        {{ revertPlayer?.name }}
                    </p>
                    <p class="body-copy">
                        Revierte una jugada registrada en el juego actual.
                    </p>
                    <div class="action-grid action-grid--three">
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            :disabled="
                                !revertPlayer || revertPlayer.shots[1] < 1
                            "
                            @click="revertPlayerPoint(1)"
                        >
                            -1
                        </button>
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            :disabled="
                                !revertPlayer || revertPlayer.shots[2] < 1
                            "
                            @click="revertPlayerPoint(2)"
                        >
                            -2
                        </button>
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            :disabled="
                                !revertPlayer || revertPlayer.shots[3] < 1
                            "
                            @click="revertPlayerPoint(3)"
                        >
                            -3
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="playerToRemove !== null"
                class="overlay"
                @click.self="playerToRemove = null"
            >
                <section class="overlay__panel">
                    <p
                        class="app-kicker overlay__kicker overlay__kicker--danger"
                    >
                        Jugador se va
                    </p>
                    <p class="body-copy">
                        Confirma la salida de {{ playerToRemove?.name }}. Si hay
                        cola activa, el siguiente jugador entrará según la
                        prioridad de la jornada.
                    </p>
                    <div class="action-grid action-grid--two">
                        <button
                            class="action-button action-button--danger"
                            type="button"
                            @click="confirmRemovePlayer"
                        >
                            Sí, se fue
                        </button>
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            @click="playerToRemove = null"
                        >
                            Cancelar
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="rotationNotice !== null"
                class="overlay"
                @click.self="rotationNotice = null"
            >
                <section class="overlay__panel">
                    <div
                        class="notice-icon"
                        :class="
                            rotationNotice.tone === 'success'
                                ? 'notice-icon--success'
                                : 'notice-icon--warning'
                        "
                    >
                        <component
                            :is="rotationNoticeIcon(rotationNotice.icon)"
                            class="size-5"
                        />
                    </div>
                    <p class="app-kicker overlay__kicker">
                        {{ rotationNotice.title }}
                    </p>
                    <div class="section-stack">
                        <p
                            v-for="line in rotationNotice.body"
                            :key="line"
                            class="body-copy notice-line"
                        >
                            {{ line }}
                        </p>
                    </div>
                    <button
                        class="action-button action-button--warning"
                        type="button"
                        @click="rotationNotice = null"
                    >
                        Entendido
                    </button>
                </section>
            </div>

            <div
                v-if="draftAlert !== null"
                class="overlay"
                @click.self="draftAlert = null"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">
                        {{ draftAlert.title }}
                    </p>
                    <div class="section-stack">
                        <p
                            v-for="line in draftAlert.body"
                            :key="line"
                            class="body-copy notice-line"
                        >
                            {{ line }}
                        </p>
                    </div>
                    <button
                        class="action-button action-button--warning"
                        type="button"
                        @click="draftAlert = null"
                    >
                        Entendido
                    </button>
                </section>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.section-stack,
.summary-card,
.overlay__panel,
.summary-pills,
.draft-actions,
.player-actions {
    display: flex;
    flex-direction: column;
}
.section-stack,
.overlay__panel,
.summary-pills {
    gap: 12px;
}
.section-head,
.data-row,
.scoreboard,
.action-grid,
.player-actions,
.draft-actions {
    display: flex;
    gap: 12px;
}
.section-head,
.data-row {
    align-items: center;
    justify-content: space-between;
}
.section-kicker,
.overlay__kicker,
.team-a-copy,
.team-b-copy {
    color: #e5b849;
}
.team-a-copy {
    color: #4ade80;
}
.team-b-copy {
    color: #e5b849;
}
.section-title,
.body-copy,
.data-row__name,
.summary-card__value {
    margin: 0;
}
.section-title,
.data-row__name {
    font-size: 16px;
    font-weight: 700;
    color: #f8fafc;
}
.body-copy {
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}
.summary-pills {
    align-items: flex-end;
}
.summary-grid {
    display: grid;
    gap: 12px;
}
.summary-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.action-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.action-grid--three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.clock-card,
.clock-card__controls,
.error-banner {
    display: flex;
    flex-direction: column;
}
.clock-card,
.error-banner {
    gap: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
}
.clock-card__controls {
    gap: 10px;
}
.clock-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.clock-field__label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}
.clock-card__value {
    margin: 6px 0 0;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 52px;
    line-height: 1;
    color: #f8fafc;
}
.error-banner {
    border-color: rgba(248, 113, 113, 0.28);
    background: rgba(248, 113, 113, 0.12);
    font-size: 13px;
    line-height: 1.6;
    color: #fca5a5;
}
.summary-card,
.data-row,
.team-card {
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
}
.summary-card__value {
    margin-top: 10px;
    font-size: 22px;
    line-height: 1;
    font-weight: 700;
    color: #f8fafc;
}
.team-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.team-card--a {
    border-color: rgba(74, 222, 128, 0.18);
}
.team-card--b {
    border-color: rgba(229, 184, 73, 0.18);
}
.scoreboard {
    align-items: center;
    justify-content: space-between;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 20px;
    background:
        radial-gradient(
            circle at top,
            rgba(229, 184, 73, 0.14),
            rgba(14, 22, 40, 0.98) 48%
        ),
        linear-gradient(180deg, rgba(19, 27, 47, 0.98), rgba(10, 15, 29, 1));
    padding: 14px;
}
.scoreboard__team,
.scoreboard__center {
    text-align: center;
}
.scoreboard__team {
    flex: 1;
}
.scoreboard__center {
    display: flex;
    min-width: 120px;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.scoreboard__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    background: rgba(19, 27, 47, 0.9);
    padding: 8px 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #94a3b8;
}
.scoreboard__vs {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 58px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    background: rgba(19, 27, 47, 0.9);
    font-family: 'Bebas Neue', sans-serif;
    font-size: 28px;
    line-height: 1;
    color: #94a3b8;
}
.scoreboard__streak {
    margin: 0;
    font-size: 11px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #94a3b8;
}
.scoreboard__value {
    margin: 8px 0 0;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 62px;
    line-height: 1;
    color: #f8fafc;
}
.scoreboard__value--green {
    color: #4ade80;
}
.scoreboard__value--gold {
    color: #e5b849;
}
.scoreboard__value--bump {
    animation: scoreboard-bump 0.18s ease-out;
}
.action-grid {
    display: grid;
    gap: 12px;
}
.action-grid--three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.draft-actions,
.player-actions {
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.member-chip,
.action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
}
.action-button {
    width: 100%;
}
.sheet-input {
    width: 100%;
    min-height: 48px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #0e1628;
    padding: 0 14px;
    color: #f8fafc;
}
.member-chip--neutral,
.action-button--secondary {
    background: #131b2f;
    color: #f8fafc;
}
.member-chip--positive,
.action-button--primary {
    background: rgba(74, 222, 128, 0.12);
    border-color: rgba(74, 222, 128, 0.28);
    color: #4ade80;
}
.member-chip--warning,
.action-button--warning {
    background: rgba(229, 184, 73, 0.12);
    border-color: rgba(229, 184, 73, 0.28);
    color: #f8fafc;
}
.member-chip--negative,
.action-button--danger {
    background: rgba(248, 113, 113, 0.12);
    border-color: rgba(248, 113, 113, 0.28);
    color: #fca5a5;
}
.score-flash {
    position: fixed;
    top: 38%;
    left: 50%;
    z-index: 1001;
    transform: translate(-50%, -50%);
    font-family: 'Bebas Neue', sans-serif;
    font-size: 72px;
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
.overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    background: rgba(3, 7, 18, 0.72);
    padding: 16px;
}
.overlay__panel {
    width: min(100%, 480px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 28px 28px 20px 20px;
    background: #1a243a;
    padding: 18px 16px 20px;
}
.overlay__kicker--danger {
    color: #f87171;
}
.notice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(229, 184, 73, 0.24);
    border-radius: 999px;
    background: rgba(229, 184, 73, 0.08);
    color: #e5b849;
}
.notice-icon--success {
    border-color: rgba(74, 222, 128, 0.24);
    background: rgba(74, 222, 128, 0.08);
    color: #4ade80;
}
.notice-line {
    margin: 0;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    background: #0e1628;
    padding: 12px 14px;
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
