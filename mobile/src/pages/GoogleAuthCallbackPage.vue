<script setup lang="ts">
import { IonButton, IonContent, IonPage, IonSpinner } from '@ionic/vue'
import type { AxiosError } from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { exchangeGoogleHandoff } from '@/services/auth'
import type { ErrorResponse } from '@/types/api'

const route = useRoute()
const router = useRouter()

const isLoading = ref(true)
const message = ref('Conectando tu cuenta de Google...')
const isError = ref(false)

const statusTitle = computed(() => (isError.value ? 'Google no pudo completar el acceso' : 'Google listo'))

async function resolveCallback(): Promise<void> {
  const handoff = typeof route.query.handoff === 'string' ? route.query.handoff : null
  const status = typeof route.query.status === 'string' ? route.query.status : null
  const statusMessage = typeof route.query.message === 'string' ? route.query.message : null

  if (handoff) {
    try {
      const result = await exchangeGoogleHandoff(handoff)

      if (result.kind === 'two-factor') {
        await router.replace({
          name: 'two-factor-challenge',
          query: {
            challenge: result.response.data.challenge_token,
            source: 'google',
          },
        })

        return
      }

      await router.replace({ name: 'home' })

      return
    } catch (error) {
      const response = (error as AxiosError<ErrorResponse>).response?.data
      message.value = response?.message ?? 'No fue posible completar el acceso con Google.'
      isError.value = true
    } finally {
      isLoading.value = false
    }

    return
  }

  isLoading.value = false
  isError.value = true

  if (status === 'verification_required') {
    message.value = statusMessage ?? 'Debes verificar tu correo antes de entrar con Google.'

    return
  }

  message.value = statusMessage ?? 'No fue posible completar el acceso con Google.'
}

onMounted(resolveCallback)
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface callback-card">
            <p class="app-kicker callback-kicker">Google</p>
            <h1 class="app-display callback-title">{{ statusTitle }}</h1>
            <p class="app-body-copy callback-copy">{{ message }}</p>

            <div v-if="isLoading" class="callback-loader">
              <IonSpinner name="crescent" />
            </div>

            <div v-else class="callback-actions">
              <IonButton expand="block" @click="router.replace({ name: 'login' })">
                Volver a login
              </IonButton>

              <IonButton color="secondary" expand="block" @click="router.replace({ name: 'register' })">
                Crear cuenta
              </IonButton>
            </div>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.callback-card,
.callback-actions {
  display: flex;
  flex-direction: column;
}

.callback-card {
  gap: 16px;
}

.callback-actions {
  gap: 12px;
}

.callback-kicker {
  color: #e5b849;
}

.callback-title {
  margin: 0;
  font-size: 48px;
  line-height: 0.9;
}

.callback-copy {
  margin: 0;
}

.callback-loader {
  display: flex;
  justify-content: center;
  padding-top: 8px;
}
</style>
