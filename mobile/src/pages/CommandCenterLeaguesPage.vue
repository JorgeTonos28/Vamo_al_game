<script setup lang="ts">
import { IonButton, IonContent, IonPage, onIonViewWillEnter } from '@ionic/vue'
import { ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchCommandCenterLeagues, toggleCommandCenterLeague } from '@/services/command-center'
import type { CommandCenterLeague } from '@/types/api'

const leagues = ref<CommandCenterLeague[]>([])
const isLoading = ref(false)
const activeRequestLeagueId = ref<number | null>(null)

async function loadLeagues(): Promise<void> {
  isLoading.value = true

  try {
    const response = await fetchCommandCenterLeagues()
    leagues.value = response.leagues
  } finally {
    isLoading.value = false
  }
}

async function toggleLeague(leagueId: number): Promise<void> {
  activeRequestLeagueId.value = leagueId

  try {
    const response = await toggleCommandCenterLeague(leagueId)
    leagues.value = leagues.value.map((league) => (league.id === leagueId ? response.league : league))
  } finally {
    activeRequestLeagueId.value = null
  }
}

onIonViewWillEnter(loadLeagues)
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar
            command-center
            title="Ligas"
            description="Revoca o restaura el acceso operativo por liga sin ocultarla del switch multi-tenant."
          />

          <section class="app-surface section-stack">
            <p v-if="isLoading" class="loading-copy">Cargando ligas...</p>

            <article v-for="league in leagues" :key="league.id" class="league-row">
              <div class="league-row__copy">
                <div class="league-row__title">
                  <p class="league-row__name">{{ league.name }}</p>
                  <span :class="['status-chip', league.is_active ? 'status-chip--positive' : 'status-chip--negative']">
                    {{ league.is_active ? 'Con acceso' : 'Acceso revocado' }}
                  </span>
                </div>
                <p class="league-row__meta">
                  {{ league.admins.map((admin) => admin.name).filter(Boolean).join(', ') || 'Sin administrador asignado' }}
                </p>
              </div>

              <div class="league-row__stats">
                <div class="stat-box">
                  <p class="app-kicker">Slug</p>
                  <p>{{ league.slug }}</p>
                </div>
                <div class="stat-box">
                  <p class="app-kicker">Miembros</p>
                  <p>{{ league.members_count }}</p>
                </div>
              </div>

              <IonButton
                :color="league.is_active ? 'danger' : 'primary'"
                :disabled="activeRequestLeagueId === league.id"
                expand="block"
                @click="toggleLeague(league.id)"
              >
                {{
                  activeRequestLeagueId === league.id
                    ? 'Actualizando...'
                    : league.is_active
                      ? 'Revocar acceso'
                      : 'Restaurar acceso'
                }}
              </IonButton>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.loading-copy,
.league-row__meta {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.league-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.league-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.league-row__copy,
.league-row__stats {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.league-row__title {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.league-row__name,
.stat-box p:last-child {
  margin: 0;
  color: #f8fafc;
}

.league-row__name {
  font-size: 17px;
  font-weight: 700;
}

.status-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 7px 11px;
  font-size: 12px;
  font-weight: 700;
}

.status-chip--positive {
  background: rgba(74, 222, 128, 0.12);
  color: #4ade80;
}

.status-chip--negative {
  background: rgba(248, 113, 113, 0.12);
  color: #fca5a5;
}

.league-row__stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.stat-box {
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  background: #0e1628;
  padding: 14px;
}

.stat-box p:last-child {
  margin-top: 8px;
}
</style>
