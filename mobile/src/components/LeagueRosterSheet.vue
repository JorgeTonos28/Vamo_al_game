<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import {
  addLeaguePlayer,
  setLeaguePlayerStatus,
  updateLeaguePlayer,
  type LeagueRosterManagement,
} from '@/services/league'

type RosterTab = 'invite' | 'edit' | 'active' | 'inactive'

const props = defineProps<{ isOpen: boolean; rosterManagement: LeagueRosterManagement }>()
const emit = defineEmits<{ (event: 'update:isOpen', value: boolean): void; (event: 'changed'): void }>()

const tabs: Array<{ key: RosterTab; label: string }> = [
  { key: 'invite', label: 'Invitar' },
  { key: 'edit', label: 'Editar' },
  { key: 'active', label: 'Activos' },
  { key: 'inactive', label: 'Inactivos' },
]

const activeTab = ref<RosterTab>('invite')
const touchStartX = ref<number | null>(null)
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
const editSearch = ref('')
const editForm = reactive({ jersey_number: '' })
const allPlayers = computed(() => [...props.rosterManagement.active_players, ...props.rosterManagement.inactive_players])
const filteredPlayers = computed(() => {
  const tokens = editSearch.value
    .trim()
    .toLocaleLowerCase()
    .split(/\s+/)
    .filter(Boolean)

  if (tokens.length === 0) {
    return allPlayers.value
  }

  return allPlayers.value.filter((player) => {
    const searchable = `${player.name} ${player.jersey_number ?? ''}`.toLocaleLowerCase()
    return tokens.every((token) => searchable.includes(token))
  })
})
const selectedPlayer = computed(() => allPlayers.value.find((player) => player.id === editPlayerId.value) ?? null)

watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      activeTab.value = 'invite'
    }
  },
)

function close(): void { emit('update:isOpen', false) }

function setTab(tab: RosterTab): void {
  activeTab.value = tab
}

function moveTab(direction: 'previous' | 'next'): void {
  const currentIndex = tabs.findIndex((tab) => tab.key === activeTab.value)
  const nextIndex = direction === 'next' ? currentIndex + 1 : currentIndex - 1

  if (nextIndex < 0 || nextIndex >= tabs.length) {
    return
  }

  activeTab.value = tabs[nextIndex].key
}

function handleTouchStart(event: TouchEvent): void {
  touchStartX.value = event.changedTouches[0]?.clientX ?? null
}

function handleTouchEnd(event: TouchEvent): void {
  const startX = touchStartX.value
  const endX = event.changedTouches[0]?.clientX

  touchStartX.value = null

  if (startX === null || endX === undefined) {
    return
  }

  const deltaX = endX - startX

  if (Math.abs(deltaX) < 48) {
    return
  }

  if (deltaX < 0) {
    moveTab('next')
    return
  }

  moveTab('previous')
}

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
  const player = allPlayers.value.find((entry) => entry.id === playerId)

  if (!player) return

  editPlayerId.value = playerId
  editSearch.value = player.name
  editForm.jersey_number = player.jersey_number?.toString() ?? ''
  activeTab.value = 'edit'
}

async function submitEdit(): Promise<void> {
  if (!editPlayerId.value || !editSearch.value.trim()) return

  await updateLeaguePlayer(editPlayerId.value, {
    display_name: editSearch.value.trim(),
    jersey_number: editForm.jersey_number ? Number(editForm.jersey_number) : null,
  })

  editPlayerId.value = null
  editSearch.value = ''
  editForm.jersey_number = ''
  emit('changed')
}

async function toggleStatus(playerId: number, active: boolean): Promise<void> {
  await setLeaguePlayerStatus(playerId, active)
  emit('changed')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="props.isOpen" class="sheet-backdrop" @click.self="close">
      <section class="sheet-panel">
        <div class="sheet-handle" />
        <p class="app-kicker sheet-kicker">Gestionar miembros</p>
        <p class="sheet-copy">Desliza a izquierda o derecha para cambiar de seccion.</p>

        <div class="sheet-tabs">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :class="['sheet-tab', activeTab === tab.key ? 'sheet-tab--active' : '']"
            type="button"
            @click="setTab(tab.key)"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="sheet-content" @touchstart="handleTouchStart" @touchend="handleTouchEnd">
          <div v-if="activeTab === 'invite'" class="sheet-block">
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

          <div v-else-if="activeTab === 'edit'" class="sheet-block">
            <p class="sheet-label">Editar miembro</p>
            <input v-model="editSearch" type="text" class="sheet-input" placeholder="Busca por nombre o numero" />
            <div class="sheet-list">
              <button
                v-for="player in filteredPlayers"
                :key="player.id"
                :class="['sheet-row', editPlayerId === player.id ? 'sheet-row--active' : '']"
                type="button"
                @click="loadEditPlayer(player.id)"
              >
                <div>
                  <p class="sheet-row__name">{{ player.name }}</p>
                  <p class="sheet-row__meta">#{{ player.jersey_number ?? 'S/N' }}</p>
                </div>
              </button>
              <p v-if="filteredPlayers.length === 0" class="sheet-note">No encontramos miembros con ese filtro.</p>
            </div>
            <input v-model="editForm.jersey_number" type="number" min="0" max="99" class="sheet-input" placeholder="Numero" />
            <button class="sheet-button sheet-button--secondary" :disabled="!selectedPlayer || !editSearch.trim()" type="button" @click="submitEdit">Actualizar miembro</button>
          </div>

          <div v-else-if="activeTab === 'active'" class="sheet-block">
            <div class="sheet-heading">
              <p class="sheet-label">Activos</p>
              <span class="sheet-count">{{ props.rosterManagement.active_players.length }}</span>
            </div>
            <article v-for="player in props.rosterManagement.active_players" :key="player.id" class="sheet-row sheet-row--static">
              <div>
                <p class="sheet-row__name">{{ player.name }}</p>
                <p class="sheet-row__meta">#{{ player.jersey_number ?? 'S/N' }}</p>
              </div>
              <div class="sheet-row__actions">
                <button class="sheet-chip sheet-chip--warning" type="button" @click="loadEditPlayer(player.id)">Editar</button>
                <button class="sheet-chip sheet-chip--danger" type="button" @click="toggleStatus(player.id, false)">Dar de baja</button>
              </div>
            </article>
            <p v-if="props.rosterManagement.active_players.length === 0" class="sheet-note">No hay miembros activos registrados todavia.</p>
          </div>

          <div v-else class="sheet-block">
            <div class="sheet-heading">
              <p class="sheet-label">Inactivos</p>
              <span class="sheet-count">{{ props.rosterManagement.inactive_players.length }}</span>
            </div>
            <article v-for="player in props.rosterManagement.inactive_players" :key="player.id" class="sheet-row sheet-row--static">
              <div>
                <p class="sheet-row__name">{{ player.name }}</p>
                <p class="sheet-row__meta">#{{ player.jersey_number ?? 'S/N' }}</p>
              </div>
              <button class="sheet-chip sheet-chip--success" type="button" @click="toggleStatus(player.id, true)">Reactivar</button>
            </article>
            <p v-if="props.rosterManagement.inactive_players.length === 0" class="sheet-note">No hay miembros dados de baja.</p>
          </div>
        </div>

        <p class="sheet-note">
          Credito por referido:
          {{ new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(props.rosterManagement.referral_credit_amount_cents / 100) }}
        </p>
        <button class="sheet-button sheet-button--secondary" type="button" @click="close">Cerrar</button>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(3, 7, 18, 0.72);
  padding: 16px;
}

.sheet-panel,
.sheet-block,
.sheet-heading,
.sheet-grid,
.sheet-row__actions,
.sheet-tabs {
  display: flex;
}

.sheet-panel {
  width: min(100%, 480px);
  max-height: calc(100vh - 32px);
  flex-direction: column;
  gap: 14px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 28px 28px 20px 20px;
  background: #1a243a;
  padding: 14px 16px 20px;
}

.sheet-block {
  min-height: 0;
  flex-direction: column;
  gap: 12px;
}

.sheet-handle {
  width: 52px;
  height: 5px;
  margin: 0 auto;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
}

.sheet-tabs {
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.sheet-tab {
  min-height: 44px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: #0e1628;
  padding: 0 16px;
  color: #94a3b8;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

.sheet-tab--active {
  border-color: rgba(229, 184, 73, 0.28);
  background: rgba(229, 184, 73, 0.12);
  color: #f8fafc;
}

.sheet-content,
.sheet-list,
.sheet-tabs {
  scrollbar-width: thin;
  scrollbar-color: rgba(229, 184, 73, 0.5) rgba(14, 22, 40, 0.92);
}

.sheet-content::-webkit-scrollbar,
.sheet-list::-webkit-scrollbar,
.sheet-tabs::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.sheet-content::-webkit-scrollbar-track,
.sheet-list::-webkit-scrollbar-track,
.sheet-tabs::-webkit-scrollbar-track {
  border-radius: 999px;
  background: rgba(14, 22, 40, 0.92);
}

.sheet-content::-webkit-scrollbar-thumb,
.sheet-list::-webkit-scrollbar-thumb,
.sheet-tabs::-webkit-scrollbar-thumb {
  border: 2px solid rgba(14, 22, 40, 0.92);
  border-radius: 999px;
  background: rgba(229, 184, 73, 0.5);
}

.sheet-content {
  min-height: 0;
  flex: 1;
  overflow-y: auto;
  padding-right: 2px;
}

.sheet-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.sheet-list {
  max-height: 220px;
  overflow-y: auto;
  padding-right: 2px;
}

.sheet-heading {
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.sheet-kicker,
.sheet-label {
  color: #e5b849;
}

.sheet-copy,
.sheet-row__meta,
.sheet-note,
.sheet-count {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.sheet-input {
  min-height: 48px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: #0e1628;
  padding: 0 14px;
  color: #f8fafc;
}

.sheet-row {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  background: #0e1628;
  padding: 14px;
  text-align: left;
}

.sheet-row--active {
  border-color: rgba(229, 184, 73, 0.28);
  background: rgba(229, 184, 73, 0.12);
}

.sheet-row--static {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
}

.sheet-row__actions {
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.sheet-row__name {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #f8fafc;
}

.sheet-button,
.sheet-chip {
  min-height: 44px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  font-size: 13px;
  font-weight: 700;
}

.sheet-button--primary,
.sheet-chip--success {
  background: rgba(74, 222, 128, 0.12);
  border-color: rgba(74, 222, 128, 0.28);
  color: #4ade80;
}

.sheet-button--secondary {
  background: #0e1628;
  color: #f8fafc;
}

.sheet-chip {
  padding: 0 12px;
}

.sheet-chip--warning {
  background: rgba(229, 184, 73, 0.12);
  border-color: rgba(229, 184, 73, 0.28);
  color: #f8fafc;
}

.sheet-chip--danger {
  background: rgba(248, 113, 113, 0.12);
  border-color: rgba(248, 113, 113, 0.28);
  color: #fca5a5;
}
</style>
