<script setup lang="ts">
import {
  IonButton,
  IonContent,
  IonInput,
  IonItem,
  IonLabel,
  IonPage,
  IonSpinner,
  IonText,
} from '@ionic/vue'
import type { AxiosError } from 'axios'
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { submitTwoFactorChallenge } from '@/services/auth'
import type { ErrorResponse, TwoFactorChallengePayload } from '@/types/api'

const route = useRoute()
const router = useRouter()

const form = reactive<TwoFactorChallengePayload>({
  challenge_token: '',
  code: '',
  recovery_code: '',
})

const isSubmitting = ref(false)
const useRecoveryCode = ref(false)
const errorMessage = ref<string | null>(null)

const source = computed(() => (route.query.source === 'google' ? 'Google' : 'tu autenticador'))

function syncChallengeToken(): void {
  form.challenge_token = typeof route.query.challenge === 'string' ? route.query.challenge : ''
}

syncChallengeToken()

const canSubmit = computed(() =>
  form.challenge_token !== '' &&
  (useRecoveryCode.value
    ? (form.recovery_code ?? '').trim() !== ''
    : (form.code ?? '').trim() !== ''),
)

async function submit(): Promise<void> {
  if (!canSubmit.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    await submitTwoFactorChallenge({
      challenge_token: form.challenge_token,
      code: useRecoveryCode.value ? undefined : form.code,
      recovery_code: useRecoveryCode.value ? form.recovery_code : undefined,
    })

    await router.replace({ name: 'home' })
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data

    errorMessage.value =
      response?.errors?.code?.[0] ??
      response?.errors?.challenge_token?.[0] ??
      response?.message ??
      'No fue posible completar la verificacion.'
  } finally {
    isSubmitting.value = false
  }
}

async function goBack(): Promise<void> {
  await router.replace({ name: 'login' })
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface challenge-hero">
            <p class="app-kicker challenge-kicker">Seguridad</p>
            <h1 class="app-display challenge-title">Confirma tu acceso</h1>
            <p class="app-body-copy challenge-copy">
              Introduce el codigo generado en {{ source }} o usa uno de tus recovery codes para
              terminar de entrar al panel.
            </p>
          </section>

          <section class="app-surface challenge-form">
            <div class="challenge-toggle">
              <button
                :class="['challenge-tab', { 'is-active': !useRecoveryCode }]"
                type="button"
                @click="useRecoveryCode = false"
              >
                Codigo
              </button>

              <button
                :class="['challenge-tab', { 'is-active': useRecoveryCode }]"
                type="button"
                @click="useRecoveryCode = true"
              >
                Recovery code
              </button>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">
                {{ useRecoveryCode ? 'Recovery code' : 'Codigo de autenticacion' }}
              </IonLabel>
              <IonItem lines="none">
                <IonInput
                  v-if="useRecoveryCode"
                  v-model="form.recovery_code"
                  autocomplete="one-time-code"
                  placeholder="XXXX-XXXX"
                />
                <IonInput
                  v-else
                  v-model="form.code"
                  inputmode="numeric"
                  :maxlength="6"
                  placeholder="000000"
                />
              </IonItem>
            </div>

            <IonText v-if="errorMessage" color="danger">
              <p class="feedback-text">{{ errorMessage }}</p>
            </IonText>

            <IonButton :disabled="!canSubmit || isSubmitting" expand="block" @click="submit">
              <IonSpinner v-if="isSubmitting" name="crescent" />
              <span v-else>Verificar y entrar</span>
            </IonButton>

            <IonButton color="secondary" expand="block" @click="goBack">Volver a login</IonButton>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.challenge-hero,
.challenge-form,
.field-group {
  display: flex;
  flex-direction: column;
}

.challenge-hero,
.challenge-form {
  gap: 16px;
}

.challenge-kicker {
  color: #e5b849;
}

.challenge-title {
  margin: 0;
  font-size: 52px;
  line-height: 0.9;
}

.challenge-copy,
.feedback-text {
  margin: 0;
}

.feedback-text {
  font-size: 13px;
  line-height: 1.5;
}

.field-group {
  gap: 8px;
}

.challenge-toggle {
  display: inline-flex;
  gap: 4px;
  border-radius: 12px;
  background: #0e1628;
  padding: 4px;
}

.challenge-tab {
  min-height: 44px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  padding: 0 14px;
  color: #94a3b8;
}

.challenge-tab.is-active {
  background: #1e293b;
  color: #f8fafc;
}
</style>
