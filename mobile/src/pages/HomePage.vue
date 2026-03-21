<script setup lang="ts">
import { IonContent, IonPage } from '@ionic/vue'
import { ArrowDownRight, ArrowUpRight, CircleDollarSign, ShieldCheck, Trophy, Users } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { sessionState } from '@/state/session'

const homeScore = ref(8)
const awayScore = ref(7)

const operatorName = computed(() => sessionState.user?.name ?? 'Operador')

const modules = [
  {
    title: 'Ligas y temporadas',
    description: 'Organiza competiciones, jornadas y estados de calendario.',
    icon: Trophy,
  },
  {
    title: 'Miembros y equipos',
    description: 'Controla jugadores, staff y participacion de cada plantilla.',
    icon: Users,
  },
  {
    title: 'Cobros y balance',
    description: 'Registra entradas, gastos y deudas sin salir del panel.',
    icon: CircleDollarSign,
  },
]
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface home-hero">
            <div class="home-header">
              <div class="home-copy">
                <p class="app-kicker home-status">Jornada en curso</p>
                <h1 class="app-display home-title">Liga Aurora</h1>
                <p class="app-body-copy home-description">
                  El sistema ya quedo alineado a una linea grafica deportiva, oscura y
                  mobile-first. Desde aqui puedes seguir construyendo modulos sin salirte del
                  criterio visual.
                </p>
                <p class="home-operator">Operador actual: {{ operatorName }}</p>
              </div>

              <div class="app-badge-positive home-badge">
                <ShieldCheck class="badge-icon" />
                En juego
              </div>
            </div>

            <div class="home-scoreboard">
              <div>
                <p class="app-kicker home-score-label">Eq. A</p>
                <p class="app-display home-score home-team">{{ homeScore }}</p>
              </div>

              <p class="app-kicker home-score-versus">VS</p>

              <div class="home-score-away">
                <p class="app-kicker home-score-label">Eq. B</p>
                <p class="app-display home-score away-team">{{ awayScore }}</p>
              </div>
            </div>

            <div class="score-actions">
              <button class="score-button home-button" type="button" @click="homeScore += 1">
                +1 Eq. A
              </button>

              <button class="score-button away-button" type="button" @click="awayScore += 1">
                +1 Eq. B
              </button>
            </div>
          </section>

          <section class="home-grid">
            <article class="app-surface stat-card">
              <p class="app-kicker">Equipos activos</p>
              <p class="home-value">12</p>
              <p class="app-body-copy">Listos para calendario y control de resultados.</p>
            </article>

            <article class="app-surface stat-card">
              <p class="app-kicker">Partidos programados</p>
              <p class="home-value">36</p>
              <p class="app-body-copy">Operacion priorizada para jornada y mesa tecnica.</p>
            </article>

            <article class="app-surface stat-card">
              <div class="app-badge-positive home-inline-badge">
                <ArrowUpRight class="badge-icon" />
                Entradas
              </div>
              <p class="home-money">RD$ 23,400</p>
              <p class="app-body-copy">Recaudacion actual de la liga.</p>
            </article>

            <article class="app-surface stat-card">
              <div class="app-badge-negative home-inline-badge">
                <ArrowDownRight class="badge-icon" />
                Gastos
              </div>
              <p class="home-money">RD$ 16,600</p>
              <p class="app-body-copy">Visual financiero con badging semantico.</p>
            </article>
          </section>

          <section class="app-surface home-modules">
            <div class="modules-copy">
              <p class="app-kicker">Base de modulos</p>
              <p class="modules-description">
                Lo que se agregue desde aqui ya debe reutilizar este lenguaje de cards, tabs,
                feedback tactil y badges.
              </p>
            </div>

            <div class="modules-list">
              <article v-for="item in modules" :key="item.title" class="module-row">
                <div class="module-icon">
                  <component :is="item.icon" class="module-icon-svg" />
                </div>

                <div class="module-copy">
                  <p class="app-kicker">{{ item.title }}</p>
                  <p class="app-body-copy">{{ item.description }}</p>
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
.home-hero {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.home-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.home-copy {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.home-status {
  color: #e5b849;
}

.home-title {
  margin: 0 0 4px;
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

.home-badge,
.home-inline-badge {
  width: fit-content;
}

.badge-icon {
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

.home-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.home-value,
.home-money {
  margin: 0;
  font-size: 32px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}

.home-money {
  font-size: 28px;
}

.home-modules,
.modules-copy,
.modules-list,
.module-copy {
  display: flex;
  flex-direction: column;
}

.home-modules,
.modules-copy,
.modules-list {
  gap: 16px;
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
