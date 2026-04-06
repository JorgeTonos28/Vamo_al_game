<script setup lang="ts">
import { IonButton, IonContent, IonPage, IonRefresher, IonRefresherContent, IonText, onIonViewWillEnter } from '@ionic/vue'
import type { AxiosError } from 'axios'
import { reactive, ref } from 'vue'
import BrandLogo from '@/components/BrandLogo.vue'
import SettingsLayout from '@/components/SettingsLayout.vue'
import { handleMobileRefresher } from '@/services/app-refresh'
import { useAppearance } from '@/composables/useAppearance'
import {
  fetchCommandCenterSettings,
  updateCommandCenterSettings,
} from '@/services/command-center'
import { brandingState } from '@/state/branding'
import type { ErrorResponse } from '@/types/api'

const settingsTabs = [
  { name: 'command-center-settings-profile', label: 'Perfil' },
  { name: 'command-center-settings-security', label: 'Seguridad' },
  { name: 'command-center-settings-appearance', label: 'Apariencia' },
]

const { appearance, updateAppearance } = useAppearance()

const appearanceTabs = [
  { value: 'light', label: 'Claro' },
  { value: 'dark', label: 'Oscuro' },
  { value: 'system', label: 'Sistema' },
] as const

const form = reactive<{
  logo: File | null
  favicon: File | null
}>({
  logo: null,
  favicon: null,
})

const isSubmitting = ref(false)
const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

async function loadSettings(): Promise<void> {
  await fetchCommandCenterSettings()
}

async function submit(): Promise<void> {
  isSubmitting.value = true
  successMessage.value = null
  errorMessage.value = null

  try {
    await updateCommandCenterSettings(form)
    form.logo = null
    form.favicon = null
    successMessage.value = 'Branding actualizado correctamente.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    errorMessage.value = response?.message ?? 'No fue posible actualizar el branding.'
  } finally {
    isSubmitting.value = false
  }
}

onIonViewWillEnter(loadSettings)

async function handleRefresh(event: CustomEvent): Promise<void> {
  await handleMobileRefresher(event, loadSettings)
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
        <SettingsLayout
          :tabs="settingsTabs"
          command-center
          title="Ajustes"
          description="Administra tu cuenta de administrador general sin salir del Centro de mando."
        >
          <section class="app-surface section-stack">
            <div class="section-copy">
              <p class="app-kicker preview-kicker">Apariencia</p>
              <p class="preview-note">
                Define la preferencia visual del Centro de mando y administra el branding compartido de la plataforma.
              </p>
            </div>

            <div class="appearance-tabs">
              <button
                v-for="tab in appearanceTabs"
                :key="tab.value"
                :class="['appearance-tab', { 'is-active': appearance === tab.value }]"
                type="button"
                @click="updateAppearance(tab.value)"
              >
                {{ tab.label }}
              </button>
            </div>

            <IonText v-if="successMessage" color="success">
              <p class="feedback-text">{{ successMessage }}</p>
            </IonText>

            <IonText v-if="errorMessage" color="danger">
              <p class="feedback-text">{{ errorMessage }}</p>
            </IonText>

            <div class="preview-card">
              <p class="app-kicker preview-kicker">Vista actual del logo</p>
              <div class="preview-box">
                <BrandLogo />
              </div>
              <p class="preview-note">
                Dimension recomendada: logo horizontal en PNG, SVG o WebP de al menos 960 x 240 px, con fondo transparente.
              </p>
            </div>

            <div class="preview-card">
              <p class="app-kicker preview-kicker">Vista actual del favicon</p>
              <div class="preview-box">
                <img
                  v-if="brandingState.branding?.favicon_url"
                  :src="brandingState.branding.favicon_url"
                  alt="Favicon actual"
                  class="favicon-preview"
                />
                <div v-else class="favicon-fallback">ICO</div>
              </div>
              <p class="preview-note">
                Dimension recomendada: favicon cuadrado en PNG o ICO de 512 x 512 px. Si usas SVG, mantenlo simple y legible a 32 x 32 px.
              </p>
            </div>

            <label class="upload-field">
              <span class="upload-label">Logo principal</span>
              <input type="file" accept=".png,.jpg,.jpeg,.webp,.svg" @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
            </label>

            <label class="upload-field">
              <span class="upload-label">Favicon</span>
              <input type="file" accept=".png,.ico,.svg" @change="form.favicon = ($event.target as HTMLInputElement).files?.[0] ?? null" />
            </label>

            <IonButton :disabled="isSubmitting" expand="block" @click="submit">
              {{ isSubmitting ? 'Guardando...' : 'Guardar branding' }}
            </IonButton>
          </section>
        </SettingsLayout>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,
.preview-card,
.section-copy {
  display: flex;
  flex-direction: column;
}

.section-stack,
.preview-card,
.section-copy {
  gap: 16px;
}

.feedback-text,
.preview-note {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: inherit;
}

.preview-kicker {
  color: #e5b849;
}

.preview-box {
  display: flex;
  min-height: 120px;
  align-items: center;
  justify-content: center;
  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 18px;
  background: #0a0f1d;
  padding: 20px;
}

.preview-note {
  color: #94a3b8;
}

.appearance-tabs {
  display: inline-flex;
  gap: 4px;
  border-radius: 12px;
  background: #0e1628;
  padding: 4px;
}

.appearance-tab {
  min-height: 48px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  padding: 0 14px;
  color: #94a3b8;
}

.appearance-tab.is-active {
  background: #1e293b;
  color: #f8fafc;
}

.favicon-preview,
.favicon-fallback {
  width: 64px;
  height: 64px;
  border-radius: 16px;
}

.favicon-preview {
  object-fit: contain;
}

.favicon-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background: #131b2f;
  font-size: 12px;
  font-weight: 700;
  color: #94a3b8;
}

.upload-field {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.upload-label {
  font-size: 13px;
  font-weight: 700;
  color: #f8fafc;
}

.upload-field input {
  min-height: 52px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  background: #0e1628;
  padding: 12px 14px;
  color: #f8fafc;
}
</style>
