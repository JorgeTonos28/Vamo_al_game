<script setup lang="ts">
import { IonContent, IonPage } from '@ionic/vue';
import {
    CircleDollarSign,
    Clock3,
    Receipt,
    ShieldCheck,
    Target,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { sessionState } from '@/state/session';

const homeScore = ref(8);
const awayScore = ref(7);

const operatorName = computed(() => sessionState.user?.name ?? 'Operador');

const quickCards = [
    {
        title: 'Cola activa',
        value: '06',
        description: 'Miembros e invitados ordenados por prioridad de juego.',
        icon: Clock3,
    },
    {
        title: 'Cobros del corte',
        value: 'RD$23.4K',
        description: 'Cuotas e invitados listos para recibos y cierre.',
        icon: CircleDollarSign,
    },
    {
        title: 'Racha actual',
        value: '2',
        description: 'El próximo ganador descansará si mantiene la racha.',
        icon: Trophy,
    },
];

const dayStats = [
    {
        title: 'Miembros listos',
        value: '18',
        description: 'Con preferencia activa para entrar.',
    },
    {
        title: 'Juegos del día',
        value: '11',
        description: 'Historial breve guardado durante la jornada.',
    },
    {
        title: 'Anotadores activos',
        value: '9',
        description: 'Puntos individuales corriendo en vivo.',
    },
    {
        title: 'Recibos emitidos',
        value: '12',
        description: 'Cobros listos para compartir por vías digitales.',
    },
];

const moduleHighlights = [
    {
        title: 'Llegada y cola',
        description:
            'Controla la llegada, la prioridad de miembros e invitados y el armado de equipos desde el mismo panel.',
        icon: Users,
    },
    {
        title: 'Contador y rachas',
        description:
            'Registra puntos de 1, 2 y 3, corrige errores y mantén el ritmo de la jornada en tiempo real.',
        icon: Target,
    },
    {
        title: 'Cobros y recibos',
        description:
            'Supervisa cortes, cuotas, invitados y comprobantes sin salir de la operación del día.',
        icon: Receipt,
    },
];
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <div class="mobile-shell">
                <div class="mobile-stack">
                    <section class="app-surface home-hero">
                        <div class="home-header">
                            <div class="home-copy">
                                <p class="app-kicker home-status">
                                    Jornada en curso
                                </p>
                                <h1 class="app-display home-title">
                                    Liga Aurora
                                </h1>
                                <p class="app-body-copy home-description">
                                    Supervisa llegada, marcador, cola y cobros
                                    desde un panel diseñado para operar rápido
                                    en cancha y con claridad total durante la
                                    jornada.
                                </p>
                                <p class="home-operator">
                                    Operador actual: {{ operatorName }}
                                </p>
                            </div>

                            <div class="app-badge-positive home-badge">
                                <ShieldCheck class="badge-icon" />
                                En juego
                            </div>
                        </div>

                        <div class="home-scoreboard">
                            <div>
                                <p class="app-kicker home-score-label">Eq. A</p>
                                <p class="app-display home-score home-team">
                                    {{ homeScore }}
                                </p>
                            </div>

                            <p class="app-kicker home-score-versus">VS</p>

                            <div class="home-score-away">
                                <p class="app-kicker home-score-label">Eq. B</p>
                                <p class="app-display home-score away-team">
                                    {{ awayScore }}
                                </p>
                            </div>
                        </div>

                        <div class="score-actions">
                            <button
                                class="score-button home-button"
                                type="button"
                                @click="homeScore += 1"
                            >
                                +1 Eq. A
                            </button>

                            <button
                                class="score-button away-button"
                                type="button"
                                @click="awayScore += 1"
                            >
                                +1 Eq. B
                            </button>
                        </div>
                    </section>

                    <section class="quick-grid">
                        <article
                            v-for="card in quickCards"
                            :key="card.title"
                            class="app-surface quick-card"
                        >
                            <div class="quick-card__header">
                                <p class="app-kicker quick-card__title">
                                    {{ card.title }}
                                </p>
                                <component
                                    :is="card.icon"
                                    class="quick-card__icon"
                                />
                            </div>

                            <p class="app-display quick-card__value">
                                {{ card.value }}
                            </p>
                            <p class="app-body-copy">{{ card.description }}</p>
                        </article>
                    </section>

                    <section class="stats-grid">
                        <article
                            v-for="item in dayStats"
                            :key="item.title"
                            class="app-surface stat-card"
                        >
                            <p class="app-kicker">{{ item.title }}</p>
                            <p class="home-value">{{ item.value }}</p>
                            <p class="app-body-copy">{{ item.description }}</p>
                        </article>
                    </section>

                    <section class="app-surface home-modules">
                        <div class="modules-copy">
                            <p class="app-kicker">Panel operativo</p>
                            <p class="modules-description">
                                Vamo al Game conecta el ritmo del juego con el
                                control administrativo para que la liga siga
                                fluyendo sin improvisaciones.
                            </p>
                        </div>

                        <div class="modules-list">
                            <article
                                v-for="item in moduleHighlights"
                                :key="item.title"
                                class="module-row"
                            >
                                <div class="module-icon">
                                    <component
                                        :is="item.icon"
                                        class="module-icon-svg"
                                    />
                                </div>

                                <div class="module-copy">
                                    <p class="app-kicker">{{ item.title }}</p>
                                    <p class="app-body-copy">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.home-hero,
.home-header,
.home-copy,
.quick-grid,
.stats-grid,
.home-modules,
.modules-copy,
.modules-list,
.module-copy {
    display: flex;
    flex-direction: column;
}

.home-hero,
.home-header,
.home-copy,
.home-modules,
.modules-copy,
.modules-list {
    gap: 16px;
}

.home-status,
.quick-card__title {
    color: #e5b849;
}

.home-title {
    margin: 0;
    font-size: 52px;
    line-height: 0.9;
}

.home-description,
.modules-description {
    margin: 0;
}

.home-operator {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.home-badge {
    width: fit-content;
}

.badge-icon,
.quick-card__icon {
    height: 14px;
    width: 14px;
}

.home-scoreboard {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: end;
    gap: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    background: #0e1628;
    padding: 16px;
}

.home-score-label,
.home-score-versus {
    color: #94a3b8;
}

.home-score {
    margin: 8px 0 0;
    font-size: 82px;
    line-height: 1;
}

.home-score-away {
    text-align: right;
}

.home-score-versus {
    padding-bottom: 12px;
}

.home-team {
    color: #4ade80;
}

.away-team {
    color: #e5b849;
}

.score-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.score-button {
    min-height: 88px;
    border: 1px solid transparent;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
    transition:
        transform 0.1s ease-out,
        opacity 0.1s ease-out;
}

.score-button:active {
    transform: scale(0.97);
    opacity: 0.8;
}

.home-button {
    background: rgba(74, 222, 128, 0.12);
    border-color: rgba(74, 222, 128, 0.3);
}

.away-button {
    background: rgba(229, 184, 73, 0.12);
    border-color: rgba(229, 184, 73, 0.3);
}

.quick-grid,
.stats-grid {
    gap: 12px;
}

.quick-card,
.stat-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.quick-card__icon {
    color: #e5b849;
}

.quick-card__value {
    margin: 0;
    font-size: 42px;
    line-height: 1;
    color: #f8fafc;
}

.home-value {
    margin: 0;
    font-size: 32px;
    line-height: 1;
    font-weight: 700;
    color: #f8fafc;
}

.module-row {
    display: flex;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.module-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.module-icon {
    display: flex;
    height: 48px;
    width: 48px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #1e293b;
}

.module-icon-svg {
    height: 20px;
    width: 20px;
    color: #e5b849;
}

.module-copy {
    gap: 4px;
}

.module-copy p {
    margin: 0;
}
</style>
