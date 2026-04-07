<script setup lang="ts">
import { onIonViewWillEnter, IonButton, IonContent, IonInput, IonItem, IonLabel, IonPage, IonRefresher, IonRefresherContent, IonText } from '@ionic/vue'
import type { AxiosError } from 'axios'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import SettingsLayout from '@/components/SettingsLayout.vue'
import { handleMobileRefresher } from '@/services/app-refresh'
import { fetchCurrentUser, logout } from '@/services/auth'
import { deleteAccount, resendVerificationEmail, updateProfile } from '@/services/settings'
import { sessionState } from '@/state/session'
import type { DeleteAccountPayload, ErrorResponse, ProfileUpdatePayload } from '@/types/api'

const router = useRouter()

const profileForm = reactive<ProfileUpdatePayload>({
  name: '',
  email: '',
})

const deleteForm = reactive<DeleteAccountPayload>({
  password: '',
})

const isLoading = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const profileMessage = ref<string | null>(null)
const profileError = ref<string | null>(null)
const verificationMessage = ref<string | null>(null)

function syncForm(): void {
  profileForm.name = sessionState.user?.name ?? ''
  profileForm.email = sessionState.user?.email ?? ''
}

async function loadProfile(): Promise<void> {
  isLoading.value = true

  try {
    await fetchCurrentUser()
    syncForm()
  } finally {
    isLoading.value = false
  }
}

onIonViewWillEnter(loadProfile)

async function handleRefresh(event: CustomEvent): Promise<void> {
  await handleMobileRefresher(event, loadProfile)
}

async function submitProfile(): Promise<void> {
  isSaving.value = true
  profileMessage.value = null
  profileError.value = null

  try {
    await updateProfile(profileForm)
    syncForm()
    profileMessage.value = 'Perfil actualizado.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    profileError.value = response?.message ?? 'No fue posible actualizar el perfil.'
  } finally {
    isSaving.value = false
  }
}

async function resendVerification(): Promise<void> {
  verificationMessage.value = null
  await resendVerificationEmail()
  verificationMessage.value = 'Se envio un nuevo enlace de verificacion.'
}

async function handleDeleteAccount(): Promise<void> {
  isDeleting.value = true

  try {
    await deleteAccount(deleteForm)
    await router.replace({ name: 'login' })
  } finally {
    isDeleting.value = false
  }
}

async function handleLogout(): Promise<void> {
  await logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <template #fixed>
        <IonRefresher @ionRefresh="handleRefresh">
          <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
        </IonRefresher>
      </template>

      <div class="mobile-shell">
        <SettingsLayout>
          <section class="settings-section">
            <div class="section-copy">
              <p class="app-kicker">Informacion del perfil</p>
              <p class="app-body-copy">Actualiza tu nombre y correo principal.</p>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Nombre</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="profileForm.name" placeholder="Nombre completo" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Correo</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="profileForm.email" placeholder="Correo electronico" type="email" />
              </IonItem>
            </div>

            <div v-if="!sessionState.user?.email_verified_at" class="verification-box">
              <p class="app-body-copy verification-copy">
                Tu correo aun no esta verificado. Usa el boton inferior para reenviar el email de verificacion.
              </p>

              <IonButton fill="clear" size="small" @click="resendVerification">
                Reenviar verificacion
              </IonButton>

              <p v-if="verificationMessage" class="verification-status">{{ verificationMessage }}</p>
            </div>

            <IonText v-if="profileMessage" color="success">
              <p class="feedback-text">{{ profileMessage }}</p>
            </IonText>

            <IonText v-if="profileError" color="danger">
              <p class="feedback-text">{{ profileError }}</p>
            </IonText>

            <IonButton :disabled="isSaving || isLoading" expand="block" @click="submitProfile">
              {{ isSaving ? 'Guardando...' : 'Guardar perfil' }}
            </IonButton>
          </section>

          <section class="settings-section danger-zone">
            <div class="section-copy">
              <p class="app-kicker">Eliminar cuenta</p>
              <p class="app-body-copy">Esta accion elimina la cuenta y sus datos de forma permanente.</p>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Contrasena actual</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="deleteForm.password" placeholder="Contrasena actual" type="password" />
              </IonItem>
            </div>

            <IonButton color="danger" :disabled="isDeleting" expand="block" @click="handleDeleteAccount">
              {{ isDeleting ? 'Eliminando...' : 'Eliminar cuenta' }}
            </IonButton>

            <IonButton color="secondary" expand="block" fill="solid" @click="handleLogout">
              Cerrar sesion
            </IonButton>
          </section>
        </SettingsLayout>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.settings-section,
.section-copy,
.field-group {
  display: flex;
  flex-direction: column;
}

.settings-section,
.field-group {
  gap: 16px;
}

.section-copy {
  gap: 8px;
}

.verification-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: #0e1628;
  padding: 16px;
}

.verification-copy,
.verification-status,
.feedback-text {
  margin: 0;
}

.verification-status,
.feedback-text {
  font-size: 13px;
  line-height: 1.5;
}

.verification-status {
  color: #4ade80;
}

.danger-zone {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 24px;
}
</style>
