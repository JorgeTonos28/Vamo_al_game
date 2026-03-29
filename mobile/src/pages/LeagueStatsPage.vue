<script setup lang="ts">
import {
    IonContent,
    IonPage,
    IonRefresher,
    IonRefresherContent,
    onIonViewWillEnter,
} from '@ionic/vue';
import { BarChart3, Target } from 'lucide-vue-next';
import { ref } from 'vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import { fetchLeagueStats } from '@/services/league';
import type { LeagueStatsPayload } from '@/services/league';

const payload = ref<LeagueStatsPayload | null>(null);
const isLoading = ref(false);

async function loadPage(): Promise<void> {
    isLoading.value = true;

    try {
        payload.value = await fetchLeagueStats();
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

async function changeSession(event: Event): Promise<void> {
    const target = event.target as HTMLSelectElement;
    const sessionId = Number(target.value);

    if (!Number.isFinite(sessionId) || sessionId <= 0) {
        return;
    }

    isLoading.value = true;

    try {
        payload.value = await fetchLeagueStats(sessionId);
    } finally {
        isLoading.value = false;
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
                <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
                    <IonRefresherContent
                        pulling-text="Desliza para refrescar"
                        refreshing-spinner="crescent"
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
                                    Estadisticas de jornada
                                </p>
                                <p class="body-copy">
                                    Puedes revisar la jornada actual o consultar
                                    dias anteriores sin salir del modulo.
                                </p>
                            </div>
                        </div>

                        <select
                            class="sheet-input"
                            :value="payload?.session_selector.selected_session_id"
                            @change="changeSession"
                        >
                            <option
                                v-for="session in payload?.session_selector
                                    .sessions ?? []"
                                :key="session.id"
                                :value="session.id"
                            >
                                {{ sessionLabel(session) }}
                            </option>
                        </select>
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
                                    Ranking ofensivo de la jornada actual con
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
                            Sin datos todavia.
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
                                    Lideres de participacion y balance de
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
                            Sin datos todavia.
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
        </IonContent>
    </IonPage>
</template>

<style scoped>
.section-stack,
.section-header,
.section-banner {
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
    border-color: rgba(74, 222, 128, 0.24);
    background: rgba(74, 222, 128, 0.12);
    color: #4ade80;
}

.section-kicker {
    color: #e5b849;
}

.data-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
}

.body-copy,
.data-row__name {
    margin: 0;
}

.body-copy {
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}

.data-row__name {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
}

.empty-state {
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
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

.member-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.member-chip--neutral {
    background: #131b2f;
    color: #f8fafc;
}

.member-chip--positive {
    background: rgba(74, 222, 128, 0.12);
    border-color: rgba(74, 222, 128, 0.28);
    color: #4ade80;
}

.member-chip--warning {
    background: rgba(229, 184, 73, 0.12);
    border-color: rgba(229, 184, 73, 0.28);
    color: #f8fafc;
}

@media (min-width: 420px) {
    .section-banner {
        flex-direction: row;
        align-items: flex-start;
        justify-content: space-between;
    }
}
</style>
