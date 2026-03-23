<script setup lang="ts">
import {
  IonButton,
  IonContent,
  IonInput,
  IonItem,
  IonLabel,
  IonPage,
  IonSelect,
  IonSelectOption,
  IonText,
  onIonViewWillEnter,
} from '@ionic/vue'
import type { AxiosError } from 'axios'
import { reactive, ref } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { fetchCommandCenterUsers, inviteCommandCenterUser } from '@/services/command-center'
import type {
  CommandCenterUser,
  CommandCenterInviteUserPayload,
  ErrorResponse,
  RoleOption,
} from '@/types/api'

const form = reactive<CommandCenterInviteUserPayload>({
  first_name: '',
  last_name: '',
  document_id: null,
  phone: null,
  address: null,
  email: '',
  account_role: null,
})

const users = ref<CommandCenterUser[]>([])
const roleOptions = ref<RoleOption[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

async function loadUsers(): Promise<void> {
  isLoading.value = true

  try {
    const response = await fetchCommandCenterUsers()
    users.value = response.users
    roleOptions.value = response.role_options
  } finally {
    isLoading.value = false
  }
}

function resetForm(): void {
  form.first_name = ''
  form.last_name = ''
  form.document_id = null
  form.phone = null
  form.address = null
  form.email = ''
  form.account_role = null
}

async function submit(): Promise<void> {
  isSubmitting.value = true
  successMessage.value = null
  errorMessage.value = null

  try {
    const response = await inviteCommandCenterUser(form)
    users.value = [response.user, ...users.value]
    resetForm()
    successMessage.value = 'Invitacion enviada correctamente.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    errorMessage.value = response?.message ?? 'No fue posible enviar la invitacion.'
  } finally {
    isSubmitting.value = false
  }
}

onIonViewWillEnter(loadUsers)
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar
            command-center
            title="Usuarios"
            description="Crea cuentas, envía invitaciones y supervisa el estado de onboarding de cada usuario."
          />

          <section class="app-surface section-stack">
            <div class="section-copy">
              <p class="app-kicker section-kicker">Invitar usuario</p>
              <p class="section-description">
                Si el rol queda vacio, el sistema asigna invitado como rol primario.
              </p>
            </div>

            <IonText v-if="successMessage" color="success">
              <p class="feedback-text">{{ successMessage }}</p>
            </IonText>

            <IonText v-if="errorMessage" color="danger">
              <p class="feedback-text">{{ errorMessage }}</p>
            </IonText>

            <div class="field-group">
              <IonLabel position="stacked">Nombre</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.first_name" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Apellido</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.last_name" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Cedula</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.document_id" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Telefono</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.phone" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Direccion</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.address" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Correo</IonLabel>
              <IonItem lines="none">
                <IonInput v-model="form.email" type="email" />
              </IonItem>
            </div>

            <div class="field-group">
              <IonLabel position="stacked">Rol</IonLabel>
              <IonItem lines="none">
                <IonSelect v-model="form.account_role" interface="action-sheet" placeholder="Invitado por defecto">
                  <IonSelectOption :value="null">Invitado por defecto</IonSelectOption>
                  <IonSelectOption v-for="role in roleOptions" :key="role.value" :value="role.value">
                    {{ role.label }}
                  </IonSelectOption>
                </IonSelect>
              </IonItem>
            </div>

            <IonButton :disabled="isSubmitting" expand="block" @click="submit">
              {{ isSubmitting ? 'Enviando...' : 'Enviar invitacion' }}
            </IonButton>
          </section>

          <section class="app-surface section-stack">
            <div class="section-copy">
              <p class="app-kicker">Directorio de usuarios</p>
              <p class="section-description">
                Estado de onboarding, rol primario y cantidad de ligas asociadas.
              </p>
            </div>

            <div v-if="isLoading" class="loading-copy">Cargando usuarios...</div>

            <article v-for="user in users" :key="user.id" class="user-row">
              <div>
                <p class="user-row__name">{{ user.name }}</p>
                <p class="user-row__email">{{ user.email }}</p>
              </div>

              <div class="user-row__meta">
                <span class="meta-chip">{{ user.account_role_label ?? 'Sin rol' }}</span>
                <span class="meta-chip meta-chip--neutral">{{ user.league_memberships_count }} ligas</span>
                <span :class="['meta-chip', user.has_completed_onboarding ? 'meta-chip--positive' : 'meta-chip--negative']">
                  {{ user.has_completed_onboarding ? 'Activo' : 'Pendiente' }}
                </span>
              </div>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,
.section-copy,
.field-group {
  display: flex;
  flex-direction: column;
}

.section-stack,
.field-group {
  gap: 16px;
}

.section-copy {
  gap: 8px;
}

.section-kicker {
  color: #e5b849;
}

.section-description,
.feedback-text,
.loading-copy {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.feedback-text {
  color: inherit;
}

.user-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.user-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.user-row__name,
.user-row__email {
  margin: 0;
}

.user-row__name {
  font-size: 15px;
  font-weight: 700;
  color: #f8fafc;
}

.user-row__email {
  font-size: 13px;
  line-height: 1.5;
  color: #94a3b8;
}

.user-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 700;
  background: rgba(229, 184, 73, 0.12);
  color: #e5b849;
}

.meta-chip--neutral {
  background: #0e1628;
  color: #94a3b8;
}

.meta-chip--positive {
  background: rgba(74, 222, 128, 0.12);
  color: #4ade80;
}

.meta-chip--negative {
  background: rgba(248, 113, 113, 0.12);
  color: #fca5a5;
}
</style>
