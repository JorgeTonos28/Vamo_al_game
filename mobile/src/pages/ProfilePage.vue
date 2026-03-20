<script setup lang="ts">
import { ref } from 'vue'
import { onIonViewWillEnter, IonButton, IonContent, IonPage } from '@ionic/vue'
import { useRouter } from 'vue-router'
import { fetchCurrentUser, logout } from '@/services/auth'
import { sessionState } from '@/state/session'

const router = useRouter()
const isLoading = ref(false)

async function loadProfile(): Promise<void> {
  isLoading.value = true

  try {
    await fetchCurrentUser()
  } finally {
    isLoading.value = false
  }
}

onIonViewWillEnter(loadProfile)

async function handleLogout(): Promise<void> {
  await logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface profile-card">
            <div>
              <p class="app-kicker">Perfil</p>
              <h1 class="app-display profile-name">{{ sessionState.user?.name }}</h1>
            </div>

            <dl class="profile-list">
              <div>
                <dt class="app-kicker">Email</dt>
                <dd class="app-body-copy">{{ sessionState.user?.email }}</dd>
              </div>
              <div>
                <dt class="app-kicker">Verificado</dt>
                <dd class="app-body-copy">
                  {{ sessionState.user?.email_verified_at ? 'Si' : 'Pendiente' }}
                </dd>
              </div>
            </dl>
          </section>

          <section class="app-surface profile-actions">
            <IonButton expand="block" fill="solid" @click="loadProfile">
              {{ isLoading ? 'Actualizando...' : 'Recargar perfil' }}
            </IonButton>

            <IonButton color="secondary" expand="block" fill="solid" @click="handleLogout">
              Cerrar sesion
            </IonButton>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.profile-card,
.profile-actions {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.profile-name {
  margin: 8px 0 0;
  font-size: 44px;
  line-height: 0.92;
}

.profile-list {
  display: grid;
  gap: 12px;
  margin: 0;
}

.profile-list dt,
.profile-list dd {
  margin: 0;
}
</style>
