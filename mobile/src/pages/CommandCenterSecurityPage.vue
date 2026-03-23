<script setup lang="ts">
import {
  onIonViewWillEnter,
  IonButton,
  IonContent,
  IonInput,
  IonItem,
  IonLabel,
  IonPage,
  IonText,
} from '@ionic/vue'
import type { AxiosError } from 'axios'
import { reactive, ref } from 'vue'
import SettingsLayout from '@/components/SettingsLayout.vue'
import {
  confirmTwoFactor,
  disableTwoFactor,
  enableTwoFactor,
  fetchRecoveryCodes,
  fetchTwoFactorSetup,
  fetchTwoFactorStatus,
  regenerateRecoveryCodes,
  updatePassword,
} from '@/services/settings'
import type {
  ErrorResponse,
  PasswordUpdatePayload,
  RecoveryCodesData,
  TwoFactorSetupData,
  TwoFactorStatusData,
} from '@/types/api'

const settingsTabs = [
  { name: 'command-center-settings-profile', label: 'Perfil' },
  { name: 'command-center-settings-security', label: 'Seguridad' },
  { name: 'command-center-settings-appearance', label: 'Apariencia' },
]

const form = reactive<PasswordUpdatePayload>({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const isSubmitting = ref(false)
const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

const twoFactorMessage = ref<string | null>(null)
const twoFactorError = ref<string | null>(null)
const isLoadingTwoFactor = ref(false)
const isActivatingTwoFactor = ref(false)
const isConfirmingTwoFactor = ref(false)
const isDisablingTwoFactor = ref(false)
const isRegeneratingCodes = ref(false)
const showRecoveryCodes = ref(false)
const recoveryCodes = ref<RecoveryCodesData['codes']>([])
const twoFactorStatus = ref<TwoFactorStatusData | null>(null)
const twoFactorSetup = ref<TwoFactorSetupData | null>(null)
const twoFactorCode = ref('')

async function submit(): Promise<void> {
  isSubmitting.value = true
  successMessage.value = null
  errorMessage.value = null

  try {
    await updatePassword(form)
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    successMessage.value = 'Contrasena actualizada.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    errorMessage.value = response?.message ?? 'No fue posible actualizar la contrasena.'
  } finally {
    isSubmitting.value = false
  }
}

async function loadTwoFactor(): Promise<void> {
  isLoadingTwoFactor.value = true

  try {
    twoFactorStatus.value = await fetchTwoFactorStatus()

    if (twoFactorStatus.value.pending_setup) {
      twoFactorSetup.value = await fetchTwoFactorSetup()
    } else {
      twoFactorSetup.value = null
    }
  } finally {
    isLoadingTwoFactor.value = false
  }
}

onIonViewWillEnter(loadTwoFactor)

async function activateTwoFactor(): Promise<void> {
  isActivatingTwoFactor.value = true
  twoFactorMessage.value = null
  twoFactorError.value = null

  try {
    twoFactorSetup.value = await enableTwoFactor()
    twoFactorStatus.value = extractStatus(twoFactorSetup.value)
    twoFactorMessage.value = 'Escanea el QR y confirma el codigo para terminar de activar 2FA.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    twoFactorError.value = response?.message ?? 'No fue posible iniciar la configuracion de 2FA.'
  } finally {
    isActivatingTwoFactor.value = false
  }
}

async function finishTwoFactorSetup(): Promise<void> {
  if (twoFactorCode.value.trim() === '') {
    return
  }

  isConfirmingTwoFactor.value = true
  twoFactorMessage.value = null
  twoFactorError.value = null

  try {
    twoFactorStatus.value = await confirmTwoFactor(twoFactorCode.value)
    twoFactorSetup.value = null
    twoFactorCode.value = ''
    twoFactorMessage.value = 'Autenticacion de dos factores activada.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    twoFactorError.value =
      response?.errors?.code?.[0] ?? response?.message ?? 'No fue posible confirmar 2FA.'
  } finally {
    isConfirmingTwoFactor.value = false
  }
}

async function toggleRecoveryCodes(): Promise<void> {
  if (!showRecoveryCodes.value) {
    const data = await fetchRecoveryCodes()
    recoveryCodes.value = data.codes
  }

  showRecoveryCodes.value = !showRecoveryCodes.value
}

async function refreshRecoveryCodes(): Promise<void> {
  isRegeneratingCodes.value = true
  twoFactorMessage.value = null
  twoFactorError.value = null

  try {
    const data = await regenerateRecoveryCodes()
    recoveryCodes.value = data.codes
    showRecoveryCodes.value = true
    twoFactorMessage.value = 'Recovery codes regenerados.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    twoFactorError.value = response?.message ?? 'No fue posible regenerar los recovery codes.'
  } finally {
    isRegeneratingCodes.value = false
  }
}

async function turnOffTwoFactor(): Promise<void> {
  isDisablingTwoFactor.value = true
  twoFactorMessage.value = null
  twoFactorError.value = null

  try {
    twoFactorStatus.value = await disableTwoFactor()
    twoFactorSetup.value = null
    recoveryCodes.value = []
    showRecoveryCodes.value = false
    twoFactorCode.value = ''
    twoFactorMessage.value = 'Autenticacion de dos factores desactivada.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    twoFactorError.value = response?.message ?? 'No fue posible desactivar 2FA.'
  } finally {
    isDisablingTwoFactor.value = false
  }
}

function extractStatus(setup: TwoFactorSetupData): TwoFactorStatusData {
  return {
    enabled: setup.enabled,
    confirmed: setup.confirmed,
    pending_setup: setup.pending_setup,
    can_manage: setup.can_manage,
    requires_confirmation: setup.requires_confirmation,
    recovery_codes_available: true,
  }
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <SettingsLayout
          :tabs="settingsTabs"
          command-center
          title="Ajustes"
          description="Administra tu cuenta de administrador general sin salir del Centro de mando."
        >
          <section class="settings-section">
            <div class="section-copy">
              <p class="app-kicker">Actualizar contrasena</p>
              <p class="app-body-copy">Manten una contrasena robusta para proteger el acceso a tu cuenta.</p>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Contrasena actual</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.current_password" placeholder="Contrasena actual" type="password" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Nueva contrasena</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.password" placeholder="Nueva contrasena" type="password" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Confirmar contrasena</IonLabel>
              <IonItem lines="none">
                <IonInput
                  v-model="form.password_confirmation"
                  placeholder="Confirmar contrasena"
                  type="password"
                />
              </IonItem>
            </div>

            <IonText v-if="successMessage" color="success">
              <p class="feedback-text">{{ successMessage }}</p>
            </IonText>

            <IonText v-if="errorMessage" color="danger">
              <p class="feedback-text">{{ errorMessage }}</p>
            </IonText>

            <IonButton :disabled="isSubmitting" expand="block" @click="submit">
              {{ isSubmitting ? 'Guardando...' : 'Guardar contrasena' }}
            </IonButton>
          </section>

          <section class="settings-section security-note">
            <div class="section-copy">
              <p class="app-kicker">Autenticacion de dos factores</p>
              <p class="app-body-copy">
                Gestiona el mismo flujo de 2FA disponible en web: setup, confirmacion, recovery
                codes y desactivacion.
              </p>
            </div>

            <IonText v-if="twoFactorMessage" color="success">
              <p class="feedback-text">{{ twoFactorMessage }}</p>
            </IonText>

            <IonText v-if="twoFactorError" color="danger">
              <p class="feedback-text">{{ twoFactorError }}</p>
            </IonText>

            <div v-if="isLoadingTwoFactor" class="status-shell">
              <p class="app-body-copy">Cargando configuracion de 2FA...</p>
            </div>

            <template v-else>
              <div class="status-shell">
                <div class="status-pill">
                  {{ twoFactorStatus?.enabled ? '2FA activa' : '2FA inactiva' }}
                </div>
                <p class="app-body-copy">
                  {{
                    twoFactorStatus?.enabled
                      ? 'Tu cuenta ya exige segundo factor al entrar desde web y movil.'
                      : 'Activa 2FA para exigir un codigo adicional al iniciar sesion.'
                  }}
                </p>
              </div>

              <IonButton
                v-if="!twoFactorStatus?.enabled && !twoFactorStatus?.pending_setup"
                :disabled="isActivatingTwoFactor"
                expand="block"
                @click="activateTwoFactor"
              >
                {{ isActivatingTwoFactor ? 'Preparando...' : 'Activar 2FA' }}
              </IonButton>

              <section v-if="twoFactorSetup" class="setup-card">
                <div class="section-copy">
                  <p class="app-kicker">Setup de autenticador</p>
                  <p class="app-body-copy">
                    Escanea este QR en tu app autenticadora o introduce la clave manualmente.
                  </p>
                </div>

                <div class="qr-shell" v-html="twoFactorSetup.qr_code_svg" />

                <div class="secret-shell">
                  <p class="secret-label">Clave manual</p>
                  <code class="secret-value">{{ twoFactorSetup.secret_key }}</code>
                </div>

                <div class="field-group">
                  <IonLabel position="stacked">Codigo de confirmacion</IonLabel>
                  <IonItem lines="none">
                    <IonInput
                      v-model="twoFactorCode"
                      inputmode="numeric"
                      :maxlength="6"
                      placeholder="000000"
                    />
                  </IonItem>
                </div>

                <IonButton
                  :disabled="isConfirmingTwoFactor || twoFactorCode.trim() === ''"
                  expand="block"
                  @click="finishTwoFactorSetup"
                >
                  {{ isConfirmingTwoFactor ? 'Confirmando...' : 'Confirmar 2FA' }}
                </IonButton>
              </section>

              <template v-if="twoFactorStatus?.enabled">
                <div class="recovery-actions">
                  <IonButton expand="block" fill="outline" @click="toggleRecoveryCodes">
                    {{ showRecoveryCodes ? 'Ocultar recovery codes' : 'Ver recovery codes' }}
                  </IonButton>

                  <IonButton
                    :disabled="isRegeneratingCodes"
                    color="secondary"
                    expand="block"
                    @click="refreshRecoveryCodes"
                  >
                    {{ isRegeneratingCodes ? 'Regenerando...' : 'Regenerar recovery codes' }}
                  </IonButton>

                  <IonButton
                    :disabled="isDisablingTwoFactor"
                    color="danger"
                    expand="block"
                    @click="turnOffTwoFactor"
                  >
                    {{ isDisablingTwoFactor ? 'Desactivando...' : 'Desactivar 2FA' }}
                  </IonButton>
                </div>

                <div v-if="showRecoveryCodes" class="recovery-shell">
                  <p class="secret-label">Recovery codes</p>

                  <div class="recovery-grid">
                    <code v-for="code in recoveryCodes" :key="code" class="recovery-code">{{ code }}</code>
                  </div>
                </div>
              </template>
            </template>
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

.feedback-text {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
}

.security-note {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 24px;
}

.status-shell,
.setup-card,
.recovery-actions,
.recovery-shell {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.status-pill {
  width: fit-content;
  border: 1px solid rgba(229, 184, 73, 0.24);
  border-radius: 999px;
  background: rgba(229, 184, 73, 0.12);
  padding: 8px 12px;
  font-size: 12px;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #e5b849;
}

.qr-shell,
.secret-shell,
.recovery-grid {
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: #0e1628;
}

.qr-shell {
  overflow: hidden;
  padding: 20px;
}

.qr-shell :deep(svg) {
  display: block;
  width: 100%;
  height: auto;
}

.secret-shell {
  padding: 16px;
}

.secret-label {
  margin: 0 0 8px;
  font-size: 12px;
  line-height: 1.5;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
}

.secret-value {
  display: block;
  font-size: 13px;
  line-height: 1.6;
  color: #f8fafc;
  word-break: break-all;
}

.recovery-grid {
  display: grid;
  gap: 8px;
  padding: 16px;
}

.recovery-code {
  font-size: 13px;
  line-height: 1.6;
  color: #f8fafc;
}
</style>
