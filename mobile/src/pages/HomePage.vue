<script setup lang="ts">
import { IonContent, IonPage } from '@ionic/vue'
import { computed } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { sessionState } from '@/state/session'

const activeLeague = computed(() => sessionState.tenancy?.active_league ?? null)

const heroTitle = computed(() => {
  if (activeLeague.value) {
    return activeLeague.value.name
  }

  return sessionState.tenancy?.guest_mode ? 'Modo invitado' : 'Panel base'
})

const heroDescription = computed(() => {
  if (activeLeague.value) {
    return `Estas operando en nombre de ${activeLeague.value.name}. Toda la informacion visible en este shell ya responde a la liga seleccionada.`
  }

  if (sessionState.tenancy?.guest_mode) {
    return 'Tu cuenta aun no forma parte de una liga. Por ahora solo ves tu panel base y tus ajustes personales.'
  }

  return 'Tu cuenta mantiene acceso al panel base y a los ajustes mientras se siguen construyendo los modulos deportivos.'
})

const quickCards = computed(() => [
  {
    title: 'Rol visible',
    value: activeLeague.value?.role_label ?? sessionState.user?.account_role_label ?? 'Invitado',
    description: 'Contexto con el que entras al sistema.',
  },
  {
    title: 'Ligas disponibles',
    value: `${sessionState.tenancy?.available_leagues.length ?? 0}`,
    description: 'Cantidad de tenants visibles desde tu switch.',
  },
  {
    title: 'Estado',
    value: activeLeague.value?.is_active === false ? 'Bloqueado' : activeLeague.value ? 'Operativo' : 'Base',
    description: 'Disponibilidad actual del entorno regular.',
  },
])

const detailCards = computed(() => [
  {
    title: 'Usuario',
    value: sessionState.user?.name ?? 'Sin sesion',
    description: 'Cuenta autenticada en esta sesion.',
  },
  {
    title: 'Liga activa',
    value: activeLeague.value?.name ?? 'Sin liga',
    description: 'Tenant actual de la app.',
  },
  {
    title: 'Switch multi-tenant',
    value: sessionState.tenancy?.can_switch ? 'Disponible' : 'No aplica',
    description: 'Se habilita cuando la cuenta tiene mas de una liga.',
  },
  {
    title: 'Correo verificado',
    value: sessionState.user?.email_verified_at ? 'Si' : 'Pendiente',
    description: 'La verificacion sigue siendo obligatoria.',
  },
])
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="heroTitle" :description="heroDescription" />

          <section class="app-surface hero-card">
            <div class="hero-card__row">
              <div>
                <p class="app-kicker hero-card__kicker">Tenant activo</p>
                <p class="hero-card__value">{{ activeLeague?.slug ?? 'guest' }}</p>
              </div>

              <div class="hero-card__separator">/</div>

              <div class="hero-card__copy">
                <p class="app-kicker hero-card__kicker">Usuario</p>
                <p class="hero-card__value hero-card__value--secondary">
                  {{ sessionState.user?.first_name ?? sessionState.user?.name ?? 'Usuario' }}
                </p>
              </div>
            </div>

            <div class="hero-card__actions">
              <div class="hero-chip hero-chip--success">Panel base</div>
              <div class="hero-chip hero-chip--warning">Ajustes listos</div>
            </div>
          </section>

          <section class="card-grid">
            <article v-for="card in quickCards" :key="card.title" class="app-surface info-card">
              <p class="app-kicker info-card__kicker">{{ card.title }}</p>
              <p class="info-card__value">{{ card.value }}</p>
              <p class="info-card__description">{{ card.description }}</p>
            </article>
          </section>

          <section class="card-grid">
            <article v-for="card in detailCards" :key="card.title" class="app-surface detail-card">
              <p class="app-kicker">{{ card.title }}</p>
              <p class="detail-card__value">{{ card.value }}</p>
              <p class="detail-card__description">{{ card.description }}</p>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.hero-card,
.card-grid,
.info-card,
.detail-card {
  display: flex;
  flex-direction: column;
}

.hero-card,
.info-card,
.detail-card {
  gap: 16px;
}

.hero-card {
  background:
    radial-gradient(circle at top right, rgba(229, 184, 73, 0.14), transparent 34%),
    radial-gradient(circle at bottom left, rgba(74, 222, 128, 0.1), transparent 32%),
    #131b2f;
}

.hero-card__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: end;
  gap: 12px;
}

.hero-card__copy {
  text-align: right;
}

.hero-card__separator {
  padding-bottom: 14px;
  color: #94a3b8;
}

.hero-card__kicker {
  color: #94a3b8;
}

.hero-card__value,
.info-card__value,
.detail-card__value {
  margin: 0;
  color: #f8fafc;
}

.hero-card__value {
  font-size: 34px;
  line-height: 0.95;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
  color: #4ade80;
}

.hero-card__value--secondary {
  color: #e5b849;
}

.hero-card__actions {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.hero-chip {
  display: flex;
  min-height: 64px;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 14px;
  font-weight: 700;
}

.hero-chip--success {
  background: rgba(74, 222, 128, 0.12);
  border-color: rgba(74, 222, 128, 0.28);
  color: #4ade80;
}

.hero-chip--warning {
  background: rgba(229, 184, 73, 0.12);
  border-color: rgba(229, 184, 73, 0.28);
  color: #e5b849;
}

.card-grid {
  gap: 12px;
}

.info-card__kicker {
  color: #e5b849;
}

.info-card__value {
  font-size: 36px;
  line-height: 0.95;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
}

.detail-card__value {
  font-size: 24px;
  line-height: 1.1;
  font-weight: 700;
}

.info-card__description,
.detail-card__description {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}
</style>
