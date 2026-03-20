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
import { useRouter } from 'vue-router'
import { login } from '@/services/auth'
import type { ErrorResponse, LoginPayload } from '@/types/api'

const router = useRouter()

const form = reactive<LoginPayload>({
  email: '',
  password: '',
  device_name: 'Ionic Dev',
})

const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const canSubmit = computed(() => form.email.trim() !== '' && form.password.trim() !== '')

async function submit(): Promise<void> {
  if (!canSubmit.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    await login(form)
    await router.replace({ name: 'home' })
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data

    errorMessage.value =
      response?.errors?.email?.[0] ??
      response?.message ??
      'No fue posible iniciar sesion.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface auth-hero">
            <p class="app-kicker auth-brand">Vamo al Game</p>
            <h1 class="app-display auth-title">Acceso movil</h1>
            <p class="app-body-copy">
              La app movil consume la misma API versionada del backend Laravel.
            </p>
          </section>

          <section class="app-surface auth-form">
            <div class="field-group">
              <IonLabel position="stacked">Correo</IonLabel>
              <IonItem lines="none">
                <IonInput
                  v-model="form.email"
                  autocomplete="email"
                  inputmode="email"
                  placeholder="demo@vamoalgame.test"
                  type="email"
                />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Contrasena</IonLabel>
              <IonItem lines="none">
                <IonInput
                  v-model="form.password"
                  autocomplete="current-password"
                  placeholder="password"
                  type="password"
                />
              </IonItem>
            </div>

            <IonText v-if="errorMessage" color="danger">
              <p class="auth-error">{{ errorMessage }}</p>
            </IonText>

            <IonButton
              :disabled="!canSubmit || isSubmitting"
              class="auth-button"
              expand="block"
              @click="submit"
            >
              <IonSpinner v-if="isSubmitting" name="crescent" />
              <span v-else>Iniciar sesion</span>
            </IonButton>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.auth-hero,
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.auth-brand {
  color: #e5b849;
}

.auth-title {
  margin: 0;
  font-size: 52px;
  line-height: 0.9;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.auth-error {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
}

.auth-button {
  margin-top: 8px;
}
</style>
