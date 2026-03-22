<script setup lang="ts">
import { onIonViewWillEnter, IonButton, IonContent, IonInput, IonItem, IonLabel, IonPage, IonText } from '@ionic/vue'
import type { AxiosError } from 'axios'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import SettingsLayout from '@/components/SettingsLayout.vue'
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
      <div class="mobile-shell">
        <SettingsLayout>
          <section class="settings-section">
            <div class="section-copy">
              <p class="app-kicker">Profile information</p>
              <p class="app-body-copy">Update your name and email address.</p>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Name</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="profileForm.name" placeholder="Full name" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Email address</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="profileForm.email" placeholder="Email address" type="email" />
              </IonItem>
            </div>

            <div v-if="!sessionState.user?.email_verified_at" class="verification-box">
              <p class="app-body-copy verification-copy">
                Your email address is unverified. Tap below to resend the verification email.
              </p>

              <IonButton fill="clear" size="small" @click="resendVerification">
                Resend verification email
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
              {{ isSaving ? 'Guardando...' : 'Save' }}
            </IonButton>
          </section>

          <section class="settings-section danger-zone">
            <div class="section-copy">
              <p class="app-kicker">Delete account</p>
              <p class="app-body-copy">
                Once your account is deleted, all of its resources and data will be permanently
                removed.
              </p>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Current password</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="deleteForm.password" placeholder="Current password" type="password" />
              </IonItem>
            </div>

            <IonButton color="danger" :disabled="isDeleting" expand="block" @click="handleDeleteAccount">
              {{ isDeleting ? 'Eliminando...' : 'Delete account' }}
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
