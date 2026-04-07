<script setup lang="ts">
import {
    IonContent,
    IonPage,
    IonRefresher,
    IonRefresherContent,
    onIonViewWillEnter,
} from '@ionic/vue';
import {
    CalendarRange,
    Coins,
    Medal,
    Trophy,
} from 'lucide-vue-next';
import { ref } from 'vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import { handleMobileRefresher } from '@/services/app-refresh';
import { fetchLeagueSeason } from '@/services/league';
import type { LeagueSeasonPayload } from '@/services/league';

const payload = ref<LeagueSeasonPayload | null>(null);
const isLoading = ref(false);

function money(amountCents: number): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP',
        maximumFractionDigits: 0,
    }).format(amountCents / 100);
}

function compactDate(value: string | null | undefined): string {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

async function loadPage(): Promise<void> {
    isLoading.value = true;

    try {
        payload.value = await fetchLeagueSeason();
    } finally {
        isLoading.value = false;
    }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
    await handleMobileRefresher(event, loadPage);
}

onIonViewWillEnter(loadPage);
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
                        :title="payload?.league.name ?? 'Temporada'"
                        :description="
                            payload?.season.season.label ??
                            'Resumen acumulado de jornadas y lideres.'
                        "
                    />

                    <section class="app-surface section-stack">
                        <div class="section-header">
                            <div class="section-header__icon">
                                <CalendarRange :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Temporada activa
                                </p>
                                <p class="body-copy">
                                    Resumen acumulado de jornadas, liderazgo
                                    individual y trazabilidad de cada sesion
                                    jugada.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="summary-grid">
                        <article class="app-surface summary-card">
                            <p class="app-kicker">Jornadas</p>
                            <p class="summary-card__value">
                                {{
                                    payload?.season.season.sessions_count ?? 0
                                }}
                            </p>
                            <p class="body-copy">
                                Desde
                                {{ compactDate(payload?.season.season.starts_on) }}
                            </p>
                        </article>
                        <article class="app-surface summary-card">
                            <p class="app-kicker">Juegos</p>
                            <p class="summary-card__value">
                                {{ payload?.season.season.totals.games ?? 0 }}
                            </p>
                            <p class="body-copy">
                                Partidos cerrados en temporada
                            </p>
                        </article>
                        <article class="app-surface summary-card">
                            <p class="app-kicker">Puntos</p>
                            <p
                                class="summary-card__value summary-card__value--warning"
                            >
                                {{ payload?.season.season.totals.points ?? 0 }}
                            </p>
                            <p class="body-copy">Produccion total acumulada</p>
                        </article>
                        <article
                            v-if="payload?.season.season.totals.show_revenue"
                            class="app-surface summary-card"
                        >
                            <p class="app-kicker">Recaudado</p>
                            <p
                                class="summary-card__value summary-card__value--positive"
                            >
                                {{
                                    money(
                                        payload?.season.season.totals
                                            .revenue_cents ?? 0,
                                    )
                                }}
                            </p>
                            <p class="body-copy">Visible solo para admins</p>
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-header section-header--row">
                            <div class="section-header__icon">
                                <Medal :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Lideres en puntos
                                </p>
                                <p class="body-copy">
                                    Produccion ofensiva acumulada durante toda la
                                    temporada activa.
                                </p>
                            </div>
                        </div>
                        <p v-if="isLoading && !payload" class="body-copy">
                            Cargando temporada...
                        </p>
                        <article
                            v-for="(row, index) in payload?.season.leaders
                                .points ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.points_per_game }} pts por juego
                                </p>
                            </div>
                            <span class="member-chip member-chip--warning">
                                {{ row.points }}
                            </span>
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-header section-header--row">
                            <div
                                class="section-header__icon section-header__icon--success"
                            >
                                <Trophy :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Lideres en victorias
                                </p>
                                <p class="body-copy">
                                    Balance de juegos ganados y ritmo de victorias
                                    por jugador.
                                </p>
                            </div>
                        </div>
                        <article
                            v-for="(row, index) in payload?.season.leaders
                                .wins ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.games }} juegos · {{ row.win_rate }}%
                                </p>
                            </div>
                            <span class="member-chip member-chip--positive"
                                >{{ row.wins }}V</span
                            >
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-header section-header--row">
                            <div
                                class="section-header__icon section-header__icon--orange"
                            >
                                <Coins :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Mas jornadas jugadas
                                </p>
                                <p class="body-copy">
                                    Participacion acumulada y asistencia a
                                    sesiones durante la temporada.
                                </p>
                            </div>
                        </div>
                        <article
                            v-for="(row, index) in payload?.season.leaders
                                .games ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.sessions_attended }} jornadas
                                    asistidas
                                </p>
                            </div>
                            <span class="member-chip member-chip--orange">
                                {{ row.games }}
                            </span>
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-header section-header--row">
                            <div class="section-header__icon">
                                <CalendarRange :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Jornadas registradas
                                </p>
                                <p class="body-copy">
                                    Cada sesion resume volumen de juego, puntos y
                                    su mejor anotador.
                                </p>
                            </div>
                        </div>
                        <article
                            v-for="sessionRow in payload?.season.sessions ?? []"
                            :key="sessionRow.id"
                            class="data-row"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ compactDate(sessionRow.date) }}
                                </p>
                                <p class="body-copy">
                                    {{ sessionRow.total_games }} juegos ·
                                    {{ sessionRow.players }} jugadores
                                </p>
                                <p class="body-copy">
                                    {{
                                        sessionRow.top_scorer
                                            ? `Mejor anotador: ${sessionRow.top_scorer.name} con ${sessionRow.top_scorer.points} pts`
                                            : 'No hubo un lider ofensivo definido.'
                                    }}
                                </p>
                            </div>
                            <span class="member-chip member-chip--neutral">
                                {{ sessionRow.total_points }} pts
                            </span>
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-header section-header--row">
                            <div
                                class="section-header__icon section-header__icon--success"
                            >
                                <Trophy :size="18" />
                            </div>
                            <div>
                                <p class="app-kicker section-kicker">
                                    Perfiles acumulados
                                </p>
                                <p class="body-copy">
                                    Vista de temporada para comparar produccion,
                                    victorias y asistencia.
                                </p>
                            </div>
                        </div>
                        <article
                            v-for="(row, index) in payload?.season.profiles ?? []"
                            :key="`${row.identity.name}-${index}`"
                            class="data-row data-row--stack"
                        >
                            <div>
                                <p class="data-row__name">
                                    {{ row.identity.name }}
                                </p>
                                <p class="body-copy">
                                    {{ row.games }} juegos · {{ row.wins }}V -
                                    {{ row.losses }}D ·
                                    {{ row.sessions_attended }} jornadas
                                </p>
                                <p class="body-copy">
                                    1P {{ row.shots[1] }} · 2P
                                    {{ row.shots[2] }} · 3P
                                    {{ row.shots[3] }} ·
                                    {{ row.points_per_game }} pts/juego
                                </p>
                            </div>
                            <span class="member-chip member-chip--warning">
                                {{ row.points }} pts
                            </span>
                        </article>
                    </section>
                </div>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.section-stack,
.summary-card,
.section-header {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.section-header--row {
    flex-direction: row;
    align-items: flex-start;
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
    flex-shrink: 0;
}

.section-header__icon--success {
    border-color: rgba(74, 222, 128, 0.24);
    background: rgba(74, 222, 128, 0.12);
    color: #4ade80;
}

.section-header__icon--orange {
    border-color: rgba(249, 115, 22, 0.24);
    background: rgba(249, 115, 22, 0.12);
    color: #f97316;
}

.summary-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.summary-card,
.data-row {
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
}

.data-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.data-row--stack {
    align-items: flex-start;
}

.summary-card__value,
.body-copy,
.data-row__name {
    margin: 0;
}

.summary-card__value {
    font-size: 22px;
    line-height: 1;
    font-weight: 700;
    color: #f8fafc;
}

.data-row__name {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
}

.summary-card__value--warning {
    color: #e5b849;
}

.summary-card__value--positive {
    color: #4ade80;
}

.section-kicker {
    color: #e5b849;
}

.body-copy {
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
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

.member-chip--orange {
    background: rgba(249, 115, 22, 0.12);
    border-color: rgba(249, 115, 22, 0.28);
    color: #f97316;
}
</style>
