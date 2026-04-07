<script setup lang="ts">
import {
    IonAlert,
    IonContent,
    IonPage,
    IonRefresher,
    IonRefresherContent,
    onIonViewWillEnter,
} from '@ionic/vue';
import { BarChart3, Target, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import { handleMobileRefresher } from '@/services/app-refresh';
import { destroyLeagueSession, fetchLeagueStats } from '@/services/league';
import type { LeagueStatsPayload } from '@/services/league';

const payload = ref<LeagueStatsPayload | null>(null);
const isLoading = ref(false);
const showDeleteAlert = ref(false);

const selectedSession = computed(
    () =>
        payload.value?.session_selector.sessions.find(
            (session) =>
                session.id === payload.value?.session_selector.selected_session_id,
        ) ?? null,
);
const canDeleteSession = computed(
    () => (payload.value?.role.can_manage ?? false) && selectedSession.value !== null,
);

async function loadPage(sessionId?: number): Promise<void> {
    isLoading.value = true;

    try {
        payload.value = await fetchLeagueStats(sessionId);
    } finally {
        isLoading.value = false;
    }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
    await handleMobileRefresher(
        event,
        () => loadPage(payload.value?.session_selector.selected_session_id ?? undefined),
    );
}

onIonViewWillEnter(() => loadPage());

async function changeSession(event: Event): Promise<void> {
    const target = event.target as HTMLSelectElement;
    const sessionId = Number(target.value);

    if (!Number.isFinite(sessionId) || sessionId <= 0) {
        await loadPage();

        return;
    }

    await loadPage(sessionId);
}

async function confirmDeleteSession(): Promise<void> {
    if (selectedSession.value === null) {
        return;
    }

    isLoading.value = true;

    try {
        payload.value = await destroyLeagueSession(selectedSession.value.id);
    } finally {
        isLoading.value = false;
        showDeleteAlert.value = false;
    }
}

function sessionLabel(
    session: LeagueStatsPayload['session_selector']['sessions'][number],
): string {
    const base = session.session_date ?? 'Sin fecha';
    const suffix = session.is_current
        ? 'actual'
        : session.status === 'completed'
          ? 'cerrada'
          : 'abierta';

    return `${base} · ${suffix} · ${session.completed_games_count} juegos`;
}
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <template v-slot:fixed>
<IonRefresher  @ionRefresh="handleRefresh">
                <IonRefresherContent
                    pulling-icon="refresh-circle"
                    pulling-text="Desliza para refrescar"
                    refreshing-spinner="crescent"
                    refreshing-text="Actualizando..."
                />
            </IonRefresher>
</template>

            <div class="mobile-shell">
                <div class="mobile-stack">
                    <MobileAppTopbar
                        :title="payload?.league.name ?? 'Stats'"
                        description="Puntos y juegos completados durante la jornada seleccionada."
                    />

                    <section class="app-surface section-stack">
                        <div class="section-header">
                            <div class="section-header__icon">
                                <BarChart3 :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Estadísticas de jornada
                                </p>
                                <p class="body-copy">
                                    Puedes revisar la jornada actual o consultar
                                    días anteriores sin salir del modulo.
                                </p>
                            </div>
                        </div>

                        <div class="selector-stack">
                            <select
                                class="sheet-input"
                                :value="
                                    payload?.session_selector.selected_session_id ??
                                    ''
                                "
                                :disabled="
                                    (payload?.session_selector.sessions.length ??
                                        0) === 0
                                "
                                @change="changeSession"
                            >
                                <option
                                    v-if="
                                        (payload?.session_selector
                                            .selected_session_id ?? null) === null &&
                                        (payload?.session_selector.sessions.length ??
                                            0) > 0
                                    "
                                    value=""
                                >
                                    Sin jornada activa · vista vacía de hoy
                                </option>
                                <option
                                    v-if="
                                        (payload?.session_selector.sessions.length ??
                                            0) === 0
                                    "
                                    value=""
                                >
                                    Sin jornadas registradas
                                </option>
                                <option
                                    v-for="session in payload?.session_selector
                                        .sessions ?? []"
                                    :key="session.id"
                                    :value="session.id"
                                >
                                    {{ sessionLabel(session) }}
                                </option>
                            </select>

                            <button
                                v-if="payload?.role.can_manage"
                                type="button"
                                class="danger-button"
                                :disabled="!canDeleteSession"
                                @click="showDeleteAlert = true"
                            >
                                <Trash2 :size="16" />
                                Eliminar jornada
                            </button>
                        </div>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-banner">
                            <div class="section-banner__copy">
                                <div class="section-header section-header--compact">
                                    <div class="section-header__icon">
                                        <Target :size="18" />
                                    </div>
                                    <p class="app-kicker section-kicker">
                                        Puntos anotados
                                    </p>
                                </div>
                                <p class="body-copy">
                                    Ranking ofensivo de la jornada visible con
                                    desglose por tipo de tiro.
                                </p>
                            </div>
                            <span class="member-chip member-chip--neutral">
                                {{ payload?.stats.games_count ?? 0 }} juegos
                            </span>
                        </div>
                        <p v-if="isLoading && !payload" class="body-copy">
                            Cargando stats...
                        </p>
                        <p
                            v-else-if="(payload?.stats.points_leaders.length ?? 0) === 0"
                            class="body-copy empty-state"
                        >
                            Sin datos todavía.
                        </p>
                        <article
                            v-for="(row, index) in payload?.stats
                                .points_leaders ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.games }} juegos · 1P
                                    {{ row.shots[1] }} · 2P {{ row.shots[2] }} ·
                                    3P {{ row.shots[3] }}
                                </p>
                            </div>
                            <span class="member-chip member-chip--warning"
                                >{{ row.points }} pts</span
                            >
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-banner">
                            <div class="section-banner__copy">
                                <div class="section-header section-header--compact">
                                    <div
                                        class="section-header__icon section-header__icon--success"
                                    >
                                        <BarChart3 :size="18" />
                                    </div>
                                    <p class="app-kicker section-kicker">
                                        Juegos jugados
                                    </p>
                                </div>
                                <p class="body-copy">
                                    Líderes de participacion y balance de
                                    victorias en la jornada seleccionada.
                                </p>
                            </div>
                            <span class="member-chip member-chip--neutral">
                                {{ payload?.stats.games_count ?? 0 }} juegos
                            </span>
                        </div>
                        <p
                            v-if="(payload?.stats.games_leaders.length ?? 0) === 0"
                            class="body-copy empty-state"
                        >
                            Sin datos todavía.
                        </p>
                        <article
                            v-for="(row, index) in payload?.stats
                                .games_leaders ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.wins }}V - {{ row.losses }}D
                                </p>
                            </div>
                            <span class="member-chip member-chip--positive"
                                >{{ row.games }} juegos</span
                            >
                        </article>
                    </section>
                </div>
            </div>

            <IonAlert
                :is-open="showDeleteAlert"
                header="Eliminar jornada"
                :message="`Se eliminará la jornada ${selectedSession?.session_date ?? 'seleccionada'} junto con sus juegos, stats y cola.`"
                :buttons="[
                    {
                        text: 'Cancelar',
                        role: 'cancel',
                        handler: () => {
                            showDeleteAlert = false;
                        },
                    },
                    {
                        text: 'Eliminar',
                        role: 'destructive',
                        handler: () => {
                            void confirmDeleteSession();
                        },
                    },
                ]"
                @didDismiss="showDeleteAlert = false"
            />
        </IonContent>
    </IonPage>
</template>

<style scoped>
.section-stack,
.section-header,
.section-banner,
.selector-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.section-banner {
    gap: 14px;
}

.section-banner__copy {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.section-header--compact {
    flex-direction: row;
    align-items: center;
    gap: 10px;
}

.section-header__icon {
    display: inline-flex;
    height: 40px;
    width: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid rgba(229, 184, 73, 0.24);
    background: rgba(229, 184, 73, 0.12);
    color: #e5b849;
}

.section-header__icon--success {
    border-color: rgba(74, 222, 128, 0.28);
    background: rgba(74, 222, 128, 0.12);
    color: #4ade80;
}

.section-kicker {
    color: #e5b849;
}

.danger-button {
    display: inline-flex;
    min-height: 48px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid rgba(248, 113, 113, 0.24);
    border-radius: 16px;
    background: rgba(248, 113, 113, 0.08);
    color: #fca5a5;
    font-size: 13px;
    font-weight: 700;
    transition:
        transform 0.1s ease-out,
        opacity 0.1s ease-out,
        background-color 0.2s ease-out;
}

.danger-button:active {
    opacity: 0.8;
    transform: scale(0.97);
}

.danger-button:disabled {
    opacity: 0.55;
}
</style>
