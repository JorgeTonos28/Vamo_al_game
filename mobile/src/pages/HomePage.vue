<script setup lang="ts">
import { IonContent, IonPage, onIonViewWillEnter } from '@ionic/vue'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { updateActiveLeague } from '@/services/tenancy'
import type { LeagueOperationalContext } from '@/services/league'
import { sessionState } from '@/state/session'

const router = useRouter()
const isSubmittingLeagueId = ref<number | null>(null)

const tenancy = computed(() => sessionState.tenancy as (typeof sessionState.tenancy & LeagueOperationalContext) | null)
const activeLeague = computed(() => tenancy.value?.active_league ?? null)
const selectorMode = computed(() => Boolean(tenancy.value?.can_access_modules && tenancy.value?.can_switch))

const heroTitle = computed(() => {
  if (selectorMode.value) {
    return `Hola, ${sessionState.user?.first_name ?? sessionState.user?.name?.split(' ')[0] ?? 'Jugador'}`
  }

  if (activeLeague.value) {
    return activeLeague.value.emoji ? `${activeLeague.value.emoji} ${activeLeague.value.name}` : activeLeague.value.name
  }

  return tenancy.value?.is_guest_role ? 'Vista invitado' : 'Panel base'
})

const heroDescription = computed(() => {
  if (selectorMode.value) {
    return 'Selecciona la liga a la que quieres entrar. Desde ahi cargaremos el panel operativo y todos los modulos disponibles.'
  }

  if (tenancy.value?.is_guest_role) {
    return 'Tu acceso actual es informativo. Los modulos operativos siguen reservados para miembros y administracion.'
  }

  return 'Esta cuenta todavia no tiene una liga operativa lista para entrar desde mobile.'
})

const infoCards = computed(() => {
  if (tenancy.value?.is_guest_role && activeLeague.value) {
    return [
      { title: 'Liga activa', value: activeLeague.value.emoji ? `${activeLeague.value.emoji} ${activeLeague.value.name}` : activeLeague.value.name, description: 'Contexto actual de tu cuenta.' },
      { title: 'Rol', value: activeLeague.value.role_label, description: 'Aun sin acceso a modulos deportivos.' },
      { title: 'Switch', value: tenancy.value.can_switch ? 'Disponible' : 'No aplica', description: 'Puedes cambiar de liga desde el topbar.' },
    ]
  }

  return [
    { title: 'Usuario', value: sessionState.user?.name ?? 'Sin sesion', description: 'Cuenta autenticada.' },
    { title: 'Ligas', value: `${tenancy.value?.available_leagues.length ?? 0}`, description: 'Ligas visibles para tu usuario.' },
    { title: 'Estado', value: 'Base', description: 'Sin contexto operativo activo.' },
  ]
})

onIonViewWillEnter(async () => {
  if (tenancy.value?.can_access_modules && !tenancy.value?.can_switch) {
    await router.replace({ name: 'league-panel' })
  }
})

async function enterLeague(leagueId: number): Promise<void> {
  if (isSubmittingLeagueId.value) {
    return
  }

  isSubmittingLeagueId.value = leagueId

  try {
    await updateActiveLeague({ league_id: leagueId })
    await router.replace({ name: 'league-panel' })
  } finally {
    isSubmittingLeagueId.value = null
  }
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="heroTitle" :description="heroDescription" />

          <section v-if="selectorMode" class="app-surface selector-card">
            <p class="app-kicker selector-card__kicker">Acceso a liga</p>
            <h2 class="selector-card__title">¿A qué liga quieres acceder?</h2>

            <div class="selector-card__list">
              <button
                v-for="league in tenancy?.available_leagues ?? []"
                :key="league.id"
                class="selector-option"
                type="button"
                @click="enterLeague(league.id)"
              >
                <div>
                  <p class="selector-option__name">{{ league.emoji ? `${league.emoji} ${league.name}` : league.name }}</p>
                  <p class="selector-option__meta">{{ league.role_label }}</p>
                </div>
                <span class="selector-option__state">
                  {{ isSubmittingLeagueId === league.id ? 'Entrando...' : 'Entrar' }}
                </span>
              </button>
            </div>
          </section>

          <section v-else class="card-grid">
            <article v-for="card in infoCards" :key="card.title" class="app-surface detail-card">
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
.selector-card,
.selector-card__list,
.card-grid {
  display: flex;
  flex-direction: column;
}

.selector-card,
.card-grid {
  gap: 16px;
}

.selector-card__kicker {
  color: #e5b849;
}

.selector-card__title,
.selector-option__name,
.detail-card__value,
.detail-card__description {
  margin: 0;
}

.selector-card__title {
  font-size: 28px;
  line-height: 1;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
  color: #f8fafc;
}

.selector-card__list {
  gap: 12px;
}

.selector-option {
  display: flex;
  min-height: 84px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 18px;
  background: #0e1628;
  padding: 0 16px;
  text-align: left;
}

.selector-option__name,
.detail-card__value {
  color: #f8fafc;
}

.selector-option__name {
  font-size: 16px;
  font-weight: 700;
}

.selector-option__meta,
.detail-card__description {
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.selector-option__state {
  font-size: 12px;
  font-weight: 700;
  color: #e5b849;
}

.detail-card__value {
  font-size: 24px;
  line-height: 1.1;
  font-weight: 700;
}
</style>
