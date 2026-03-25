<script setup lang="ts">
import { reactive, ref } from 'vue'
import { addLeaguePlayer, setLeaguePlayerStatus, updateLeaguePlayer, type LeagueRosterManagement } from '@/services/league'

const props = defineProps<{ isOpen: boolean; rosterManagement: LeagueRosterManagement }>()
const emit = defineEmits<{ (event: 'update:isOpen', value: boolean): void; (event: 'changed'): void }>()

const inviteForm = reactive({
  first_name: '',
  last_name: '',
  document_id: '',
  phone: '',
  address: '',
  email: '',
  account_role: 'member' as 'league_admin' | 'member',
})
const editPlayerId = ref<number | null>(null)
const editForm = reactive({ display_name: '', jersey_number: '' })

function close(): void { emit('update:isOpen', false) }

async function submitInvite(): Promise<void> {
  if (!inviteForm.first_name.trim() || !inviteForm.last_name.trim() || !inviteForm.email.trim()) return
  await addLeaguePlayer({
    first_name: inviteForm.first_name,
    last_name: inviteForm.last_name,
    document_id: inviteForm.document_id || null,
    phone: inviteForm.phone || null,
    address: inviteForm.address || null,
    email: inviteForm.email,
    account_role: inviteForm.account_role,
  })
  inviteForm.first_name = ''
  inviteForm.last_name = ''
  inviteForm.document_id = ''
  inviteForm.phone = ''
  inviteForm.address = ''
  inviteForm.email = ''
  inviteForm.account_role = 'member'
  emit('changed')
}

function loadEditPlayer(playerId: number): void {
  const player = [...props.rosterManagement.active_players, ...props.rosterManagement.inactive_players].find((entry) => entry.id === playerId)
  if (!player) return
  editPlayerId.value = playerId
  editForm.display_name = player.name
  editForm.jersey_number = player.jersey_number?.toString() ?? ''
}

async function submitEdit(): Promise<void> {
  if (!editPlayerId.value || !editForm.display_name.trim()) return
  await updateLeaguePlayer(editPlayerId.value, { display_name: editForm.display_name, jersey_number: editForm.jersey_number ? Number(editForm.jersey_number) : null })
  editPlayerId.value = null
  editForm.display_name = ''
  editForm.jersey_number = ''
  emit('changed')
}

async function toggleStatus(playerId: number, active: boolean): Promise<void> {
  await setLeaguePlayerStatus(playerId, active)
  emit('changed')
}

function handleEditChange(event: Event): void {
  const target = event.target as HTMLSelectElement
  loadEditPlayer(Number(target.value))
}
</script>

<template>
  <Teleport to="body">
    <div v-if="props.isOpen" class="sheet-backdrop" @click.self="close">
      <section class="sheet-panel">
        <div class="sheet-handle" />
        <p class="app-kicker sheet-kicker">Gestionar miembros</p>

        <div class="sheet-block">
          <p class="sheet-label">Invitar miembro</p>
          <div class="sheet-grid">
            <input v-model="inviteForm.first_name" type="text" class="sheet-input" placeholder="Nombre" />
            <input v-model="inviteForm.last_name" type="text" class="sheet-input" placeholder="Apellido" />
          </div>
          <div class="sheet-grid">
            <input v-model="inviteForm.document_id" type="text" class="sheet-input" placeholder="Cedula" />
            <select v-model="inviteForm.account_role" class="sheet-input">
              <option value="member">Miembro</option>
              <option value="league_admin">Administrador</option>
            </select>
          </div>
          <div class="sheet-grid">
            <input v-model="inviteForm.phone" type="text" class="sheet-input" placeholder="Telefono" />
            <input v-model="inviteForm.email" type="email" class="sheet-input" placeholder="Correo" />
          </div>
          <input v-model="inviteForm.address" type="text" class="sheet-input" placeholder="Direccion" />
          <button class="sheet-button sheet-button--primary" type="button" @click="submitInvite">Enviar invitacion</button>
        </div>

        <div class="sheet-block">
          <p class="sheet-label">Editar miembro</p>
          <select class="sheet-input" @change="handleEditChange">
            <option value="">Selecciona un miembro</option>
            <option v-for="player in [...props.rosterManagement.active_players, ...props.rosterManagement.inactive_players]" :key="player.id" :value="player.id">{{ player.name }}</option>
          </select>
          <input v-model="editForm.display_name" type="text" class="sheet-input" placeholder="Nombre visible" />
          <input v-model="editForm.jersey_number" type="number" min="0" max="99" class="sheet-input" placeholder="Numero" />
          <button class="sheet-button sheet-button--secondary" type="button" @click="submitEdit">Actualizar miembro</button>
        </div>

        <div class="sheet-block">
          <p class="sheet-label">Activos</p>
          <article v-for="player in props.rosterManagement.active_players" :key="player.id" class="sheet-row">
            <div><p class="sheet-row__name">{{ player.name }}</p><p class="sheet-row__meta">#{{ player.jersey_number ?? 'S/N' }}</p></div>
            <button class="sheet-chip sheet-chip--danger" type="button" @click="toggleStatus(player.id, false)">Dar de baja</button>
          </article>
        </div>

        <div class="sheet-block">
          <p class="sheet-label">Inactivos</p>
          <article v-for="player in props.rosterManagement.inactive_players" :key="player.id" class="sheet-row">
            <div><p class="sheet-row__name">{{ player.name }}</p><p class="sheet-row__meta">#{{ player.jersey_number ?? 'S/N' }}</p></div>
            <button class="sheet-chip sheet-chip--success" type="button" @click="toggleStatus(player.id, true)">Reactivar</button>
          </article>
        </div>

        <p class="sheet-note">Credito por referido: {{ new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(props.rosterManagement.referral_credit_amount_cents / 100) }}</p>
        <button class="sheet-button sheet-button--secondary" type="button" @click="close">Cerrar</button>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(3,7,18,.72);padding:16px}
.sheet-panel,.sheet-block,.sheet-grid{display:flex;flex-direction:column}
.sheet-panel{width:min(100%,480px);gap:16px;max-height:calc(100vh - 32px);overflow-y:auto;border:1px solid rgba(255,255,255,.06);border-radius:28px 28px 20px 20px;background:#1a243a;padding:14px 16px 20px}
.sheet-handle{width:52px;height:5px;margin:0 auto;border-radius:999px;background:rgba(255,255,255,.16)}
.sheet-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.sheet-kicker,.sheet-label{color:#e5b849}
.sheet-input{min-height:48px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0e1628;padding:0 14px;color:#f8fafc}
.sheet-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.sheet-row__name,.sheet-row__meta,.sheet-note{margin:0}
.sheet-row__name{font-size:15px;font-weight:700;color:#f8fafc}
.sheet-row__meta,.sheet-note{font-size:13px;line-height:1.6;color:#94a3b8}
.sheet-button,.sheet-chip{min-height:44px;border-radius:12px;border:1px solid rgba(255,255,255,.06);font-size:13px;font-weight:700}
.sheet-button--primary,.sheet-chip--success{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.sheet-button--secondary{background:#0e1628;color:#f8fafc}
.sheet-chip{padding:0 12px}
.sheet-chip--danger{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
</style>
