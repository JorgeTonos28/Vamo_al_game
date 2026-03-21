<script setup lang="ts">
import { IonButton, IonContent, IonIcon, IonPage } from '@ionic/vue'
import {
  calendarOutline,
  logInOutline,
  personAddOutline,
  peopleOutline,
  pulseOutline,
  trophyOutline,
  walletOutline,
} from 'ionicons/icons'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { sessionState } from '@/state/session'

const router = useRouter()

const highlights = [
  {
    title: 'Miembros',
    description: 'Jugadores, staff y roles bien organizados.',
    icon: peopleOutline,
  },
  {
    title: 'Jornadas',
    description: 'Calendario, marcador y operaciones tactiles rapidas.',
    icon: trophyOutline,
  },
  {
    title: 'Cobros',
    description: 'Entradas, deudas y balance de la liga en un mismo flujo.',
    icon: walletOutline,
  },
]

const primaryActionLabel = computed(() => (sessionState.token ? 'Entrar al panel' : 'Iniciar sesion'))

async function openPrimaryAction(): Promise<void> {
  await router.push(sessionState.token ? { name: 'home' } : { name: 'login' })
}

async function openSecondaryAction(): Promise<void> {
  await router.push(sessionState.token ? { name: 'settings-profile' } : { name: 'register' })
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <header class="landing-topbar">
            <div>
              <p class="app-kicker landing-brand">Vamo al Game</p>
              <p class="landing-caption">Gestion de ligas deportivas</p>
            </div>

            <IonButton
              class="landing-topbar-button"
              color="light"
              fill="clear"
              size="small"
              @click="sessionState.token ? router.push({ name: 'home' }) : router.push({ name: 'login' })"
            >
              {{ sessionState.token ? 'Panel' : 'Entrar' }}
            </IonButton>
          </header>

          <section class="app-surface landing-hero">
            <div class="landing-copy">
              <p class="app-kicker landing-kicker">Operacion mobile-first</p>
              <h1 class="app-display landing-title">Organiza la jornada sin perder el ritmo</h1>
              <p class="app-body-copy landing-description">
                Vamo al Game centraliza equipos, partidos, cobros y resultados en una interfaz
                pensada primero para movil, con una operacion rapida y consistente.
              </p>
            </div>

            <div class="landing-scoreboard">
              <div>
                <p class="app-kicker landing-score-label">Eq. A</p>
                <p class="app-display landing-score home-team">08</p>
              </div>

              <p class="app-kicker landing-score-versus">VS</p>

              <div class="landing-score-away">
                <p class="app-kicker landing-score-label">Eq. B</p>
                <p class="app-display landing-score away-team">07</p>
              </div>
            </div>

            <div class="landing-actions">
              <IonButton class="cta-button" expand="block" @click="openPrimaryAction">
                <IonIcon :icon="sessionState.token ? pulseOutline : logInOutline" />
                <span>{{ primaryActionLabel }}</span>
              </IonButton>

              <IonButton
                class="cta-button secondary-cta"
                color="secondary"
                expand="block"
                @click="openSecondaryAction"
              >
                <IonIcon :icon="sessionState.token ? peopleOutline : personAddOutline" />
                <span>{{ sessionState.token ? 'Ver perfil' : 'Crear cuenta' }}</span>
              </IonButton>
            </div>
          </section>

          <section class="landing-highlights">
            <article
              v-for="item in highlights"
              :key="item.title"
              class="app-surface highlight-card"
            >
              <div class="highlight-icon">
                <IonIcon :icon="item.icon" />
              </div>

              <div class="highlight-copy">
                <p class="app-kicker">{{ item.title }}</p>
                <p class="app-body-copy">{{ item.description }}</p>
              </div>
            </article>
          </section>

          <section class="landing-stats">
            <article class="app-surface stat-card">
              <div class="app-badge-positive">Entradas</div>
              <p class="landing-money">RD$ 23,400</p>
              <p class="app-body-copy">Balance claro para la jornada.</p>
            </article>

            <article class="app-surface stat-card">
              <div class="app-badge-negative">
                <IonIcon :icon="calendarOutline" />
                <span>Gastos</span>
              </div>
              <p class="landing-money">RD$ 16,600</p>
              <p class="app-body-copy">Resumen legible y sin ruido visual.</p>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.landing-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.landing-topbar-button {
  margin: 0;
  min-height: 40px;
  --color: #e5b849;
}

.landing-brand {
  color: #e5b849;
}

.landing-caption {
  margin: 4px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: #94a3b8;
}

.landing-hero,
.landing-copy {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.landing-kicker {
  color: #e5b849;
}

.landing-title {
  margin: 0;
  font-size: 64px;
  line-height: 0.88;
}

.landing-description {
  margin: 0;
  font-size: 16px;
  line-height: 1.75;
  color: #c9d5e3;
}

.landing-scoreboard {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: end;
  gap: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: #0e1628;
  padding: 16px;
}

.landing-score-label,
.landing-score-versus {
  color: #94a3b8;
}

.landing-score {
  margin: 8px 0 0;
  font-size: 72px;
  line-height: 1;
}

.landing-score-away {
  text-align: right;
}

.home-team {
  color: #4ade80;
}

.away-team {
  color: #e5b849;
}

.landing-score-versus {
  padding-bottom: 12px;
}

.landing-actions {
  display: grid;
  gap: 12px;
}

.cta-button {
  margin: 0;
}

.cta-button::part(native) {
  min-height: 48px;
}

.secondary-cta::part(native) {
  --background: #1e293b;
  --color: #f8fafc;
}

.landing-highlights,
.landing-stats {
  display: grid;
  gap: 12px;
}

.highlight-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: #0e1628;
}

.highlight-icon {
  display: flex;
  height: 48px;
  width: 48px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: #1e293b;
  color: #e5b849;
  font-size: 20px;
}

.highlight-copy {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.highlight-copy p {
  margin: 0;
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.landing-money {
  margin: 0;
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}
</style>
