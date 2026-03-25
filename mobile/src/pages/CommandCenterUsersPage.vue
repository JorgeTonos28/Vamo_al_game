<script setup lang="ts">
import { IonButton, IonContent, IonInput, IonItem, IonLabel, IonPage, IonRefresher, IonRefresherContent, IonSelect, IonSelectOption, IonText, onIonViewWillEnter } from '@ionic/vue'
import type { AxiosError } from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { assignCommandCenterUserLeague, fetchCommandCenterUsers, inviteCommandCenterUser } from '@/services/command-center'
import type { CommandCenterInviteUserPayload, ErrorResponse } from '@/types/api'

type RoleOption = { value: string; label: string }
type LeagueMembershipRoleValue = 'league_admin' | 'member'
type LeagueRoleOption = { value: LeagueMembershipRoleValue; label: string }
type LeagueOption = { id: number; name: string; slug: string }
type UserMembership = { id: number; league_id: number; league_name: string; role_label: string; is_active: boolean }
type CommandCenterUserRow = { id: number; name: string; email: string; account_role_label: string | null; league_memberships_count: number; active_league_id: number | null; memberships: UserMembership[]; can_assign_leagues: boolean; has_completed_onboarding: boolean }

const form = reactive<CommandCenterInviteUserPayload>({
  first_name: '',
  last_name: '',
  document_id: null,
  phone: null,
  address: null,
  email: '',
  account_role: null,
  league_id: null,
})

const users = ref<CommandCenterUserRow[]>([])
const roleOptions = ref<RoleOption[]>([])
const leagueRoleOptions = ref<LeagueRoleOption[]>([])
const leagueOptions = ref<LeagueOption[]>([])
const assignmentForms = reactive<Record<number, { league_id: number | null; role: LeagueMembershipRoleValue }>>({})
const isLoading = ref(false)
const isSubmitting = ref(false)
const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

const requiresLeagueAssignment = computed(() => form.account_role === 'league_admin' || form.account_role === 'member')

watch(() => form.account_role, (role) => {
  if (role !== 'league_admin' && role !== 'member') {
    form.league_id = null
  }
})

function assignmentFor(user: CommandCenterUserRow): { league_id: number | null; role: LeagueMembershipRoleValue } {
  if (!assignmentForms[user.id]) {
    const assignedLeagueIds = new Set(user.memberships.map((membership) => membership.league_id))
    const suggestedLeague = leagueOptions.value.find((league) => !assignedLeagueIds.has(league.id)) ?? leagueOptions.value[0]
    assignmentForms[user.id] = {
      league_id: suggestedLeague?.id ?? null,
      role: leagueRoleOptions.value[0]?.value ?? 'member',
    }
  }

  return assignmentForms[user.id]
}

function replaceUserRow(user: CommandCenterUserRow): void {
  users.value = users.value.some((row) => row.id === user.id)
    ? users.value.map((row) => (row.id === user.id ? user : row))
    : [user, ...users.value]
}

async function loadUsers(): Promise<void> {
  isLoading.value = true

  try {
    const response = await fetchCommandCenterUsers()
    users.value = response.users as CommandCenterUserRow[]
    roleOptions.value = response.role_options as RoleOption[]
    leagueRoleOptions.value = (response as { league_role_options: LeagueRoleOption[] }).league_role_options
    leagueOptions.value = response.league_options as LeagueOption[]
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
  form.league_id = null
}

async function submit(): Promise<void> {
  isSubmitting.value = true
  successMessage.value = null
  errorMessage.value = null

  try {
    const response = await inviteCommandCenterUser(form)
    replaceUserRow(response.user as CommandCenterUserRow)
    resetForm()
    successMessage.value = 'Invitacion enviada correctamente.'
  } catch (error) {
    const response = (error as AxiosError<ErrorResponse>).response?.data
    errorMessage.value = response?.message ?? 'No fue posible enviar la invitacion.'
  } finally {
    isSubmitting.value = false
  }
}

async function assignLeague(user: CommandCenterUserRow): Promise<void> {
  const assignment = assignmentFor(user)

  if (!assignment.league_id) {
    return
  }

  const response = await assignCommandCenterUserLeague(user.id, {
    league_id: assignment.league_id,
    role: assignment.role,
  })

  replaceUserRow(response.user as CommandCenterUserRow)
}

async function handleRefresh(event: CustomEvent): Promise<void> {
  try {
    await loadUsers()
  } finally {
    await (event.target as HTMLIonRefresherElement).complete()
  }
}

onIonViewWillEnter(loadUsers)
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar command-center title="Usuarios" description="Invita usuarios, asigna ligas activas y revisa su onboarding." />

          <section class="app-surface section-stack">
            <p class="app-kicker section-kicker">Invitar usuario</p>
            <IonText v-if="successMessage" color="success"><p class="feedback-text">{{ successMessage }}</p></IonText>
            <IonText v-if="errorMessage" color="danger"><p class="feedback-text">{{ errorMessage }}</p></IonText>

            <div class="field-grid">
              <div class="field-group"><IonLabel position="stacked">Nombre</IonLabel><IonItem lines="none"><IonInput v-model="form.first_name" /></IonItem></div>
              <div class="field-group"><IonLabel position="stacked">Apellido</IonLabel><IonItem lines="none"><IonInput v-model="form.last_name" /></IonItem></div>
              <div class="field-group"><IonLabel position="stacked">Cedula</IonLabel><IonItem lines="none"><IonInput v-model="form.document_id" /></IonItem></div>
              <div class="field-group"><IonLabel position="stacked">Telefono</IonLabel><IonItem lines="none"><IonInput v-model="form.phone" /></IonItem></div>
              <div class="field-group field-group--full"><IonLabel position="stacked">Direccion</IonLabel><IonItem lines="none"><IonInput v-model="form.address" /></IonItem></div>
              <div class="field-group field-group--full"><IonLabel position="stacked">Correo</IonLabel><IonItem lines="none"><IonInput v-model="form.email" type="email" /></IonItem></div>
              <div class="field-group"><IonLabel position="stacked">Rol</IonLabel><IonItem lines="none"><IonSelect v-model="form.account_role" interface="action-sheet" placeholder="Invitado por defecto"><IonSelectOption :value="null">Invitado por defecto</IonSelectOption><IonSelectOption v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</IonSelectOption></IonSelect></IonItem></div>
              <div v-if="requiresLeagueAssignment" class="field-group"><IonLabel position="stacked">Liga inicial opcional</IonLabel><IonItem lines="none"><IonSelect v-model="form.league_id" interface="action-sheet" placeholder="Dejar sin liga"><IonSelectOption :value="null">Dejar sin liga</IonSelectOption><IonSelectOption v-for="league in leagueOptions" :key="league.id" :value="league.id">{{ league.name }}</IonSelectOption></IonSelect></IonItem></div>
            </div>

            <IonButton :disabled="isSubmitting" expand="block" @click="submit">{{ isSubmitting ? 'Enviando...' : 'Enviar invitacion' }}</IonButton>
          </section>

          <section class="app-surface section-stack">
            <p v-if="isLoading" class="feedback-text">Cargando usuarios...</p>

            <article v-for="user in users" :key="user.id" class="user-row">
              <div class="user-row__header">
                <div>
                  <p class="user-row__name">{{ user.name }}</p>
                  <p class="user-row__email">{{ user.email }}</p>
                </div>
                <div class="user-row__meta">
                  <span class="meta-chip">{{ user.account_role_label ?? 'Sin rol' }}</span>
                  <span class="meta-chip meta-chip--neutral">{{ user.league_memberships_count }} ligas</span>
                  <span :class="['meta-chip', user.has_completed_onboarding ? 'meta-chip--positive' : 'meta-chip--negative']">{{ user.has_completed_onboarding ? 'Activo' : 'Pendiente' }}</span>
                </div>
              </div>

              <div class="chip-wrap">
                <span v-for="membership in user.memberships" :key="membership.id" :class="['meta-chip', membership.is_active ? 'meta-chip--neutral' : 'meta-chip--negative']">
                  {{ membership.league_name }} · {{ membership.role_label }}{{ user.active_league_id === membership.league_id ? ' · Activa' : '' }}
                </span>
                <p v-if="user.memberships.length === 0" class="feedback-text">Sin ligas asignadas.</p>
              </div>

              <div v-if="user.can_assign_leagues" class="field-grid">
                <div class="field-group"><IonLabel position="stacked">Liga</IonLabel><IonItem lines="none"><IonSelect v-model="assignmentFor(user).league_id" interface="action-sheet"><IonSelectOption v-for="league in leagueOptions" :key="league.id" :value="league.id">{{ league.name }}</IonSelectOption></IonSelect></IonItem></div>
                <div class="field-group"><IonLabel position="stacked">Rol en liga</IonLabel><IonItem lines="none"><IonSelect v-model="assignmentFor(user).role" interface="action-sheet"><IonSelectOption v-for="role in leagueRoleOptions" :key="role.value" :value="role.value">{{ role.label }}</IonSelectOption></IonSelect></IonItem></div>
              </div>

              <IonButton v-if="user.can_assign_leagues" expand="block" @click="assignLeague(user)">Guardar liga</IonButton>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.field-group,.user-row,.user-row__header,.user-row__meta,.chip-wrap{display:flex;flex-direction:column}
.section-stack,.field-group,.user-row,.chip-wrap{gap:12px}
.field-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
.field-group--full{grid-column:1/-1}
.section-kicker{color:#e5b849}
.feedback-text,.user-row__email{margin:0;font-size:13px;line-height:1.6;color:#94a3b8}
.user-row{padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.06)}
.user-row:last-child{padding-bottom:0;border-bottom:0}
.user-row__name{margin:0;font-size:15px;font-weight:700;color:#f8fafc}
.user-row__meta,.chip-wrap{gap:8px}
.meta-chip{display:inline-flex;align-items:center;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:700;background:rgba(229,184,73,.12);color:#e5b849}
.meta-chip--neutral{background:#0e1628;color:#f8fafc}
.meta-chip--positive{background:rgba(74,222,128,.12);color:#4ade80}
.meta-chip--negative{background:rgba(248,113,113,.12);color:#fca5a5}
</style>
