<script setup lang="ts">
import { IonContent, IonPage, onIonViewWillEnter } from '@ionic/vue'
import { computed, reactive, ref } from 'vue'
import LeagueRosterSheet from '@/components/LeagueRosterSheet.vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { addLeagueArrivalGuest, deleteLeagueArrivalGuest, fetchLeagueArrival, prepareLeagueArrival, resetLeagueArrival, toggleLeagueArrivalPlayer, type LeagueArrivalPayload, updateLeagueArrivalGuest } from '@/services/league'

const payload = ref<LeagueArrivalPayload | null>(null)
const isLoading = ref(false)
const guestName = ref('')
const selectedPlayerId = ref<number | null>(null)
const prepareOpen = ref(false)
const rosterOpen = ref(false)
const guestPayments = reactive<Record<number, boolean>>({})
const canManageArrival = computed(() => payload.value?.role.can_manage ?? false)
const sortedPlayers = computed(() => [...(payload.value?.players ?? [])].sort((left, right) => left.has_arrived !== right.has_arrived ? (left.has_arrived ? -1 : 1) : left.current_cut_paid !== right.current_cut_paid ? (left.current_cut_paid ? -1 : 1) : left.name.localeCompare(right.name)))

async function loadPage(): Promise<void> { isLoading.value = true; try { payload.value = await fetchLeagueArrival() } finally { isLoading.value = false } }
onIonViewWillEnter(loadPage)

async function togglePlayer(playerId: number, paid?: boolean): Promise<void> { if (!canManageArrival.value) return; payload.value = await toggleLeagueArrivalPlayer(playerId, paid); selectedPlayerId.value = null }
async function addGuest(): Promise<void> { if (!canManageArrival.value || !guestName.value.trim()) return; payload.value = await addLeagueArrivalGuest(guestName.value); guestName.value = '' }
async function toggleGuest(guestId: number, paid: boolean): Promise<void> { if (!canManageArrival.value) return; payload.value = await updateLeagueArrivalGuest(guestId, !paid) }
async function removeGuest(guestId: number): Promise<void> { if (!canManageArrival.value) return; payload.value = await deleteLeagueArrivalGuest(guestId) }
function openPrepare(): void { if (!canManageArrival.value) return; (payload.value?.guests ?? []).forEach((guest) => { guestPayments[guest.id] = guest.guest_fee_paid }); prepareOpen.value = true }
async function prepareSession(): Promise<void> { if (!canManageArrival.value) return; payload.value = await prepareLeagueArrival((payload.value?.guests ?? []).map((guest) => ({ id: guest.id, paid: Boolean(guestPayments[guest.id]) }))); prepareOpen.value = false }
async function resetSession(): Promise<void> { if (!canManageArrival.value) return; payload.value = await resetLeagueArrival() }
function money(amountCents: number): string { return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(amountCents / 100) }
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="payload?.league.name ?? 'Llegada'" :description="payload?.cut.is_past_due ? 'Solo mantienen prioridad quienes estan al dia.' : 'Todos los miembros siguen con prioridad dentro del corte.'" />

          <section class="app-surface summary-grid">
            <article class="summary-card"><p class="app-kicker">Corte activo</p><p class="summary-card__value">{{ payload?.cut.label ?? 'Cargando...' }}</p></article>
            <article class="summary-card"><p class="app-kicker">Miembros</p><p class="summary-card__value">{{ payload?.session.counts.arrived_members ?? 0 }}/{{ payload?.session.counts.total_members ?? 0 }}</p></article>
            <article class="summary-card"><p class="app-kicker">Invitados</p><p class="summary-card__value">{{ payload?.session.counts.guests ?? 0 }}</p></article>
          </section>

          <section class="app-surface section-stack">
            <div class="section-head">
              <p class="app-kicker section-head__kicker">Miembros</p>
              <div v-if="canManageArrival" class="section-head__actions">
                <button v-if="payload?.roster_management.can_manage" class="action-button action-button--secondary" type="button" @click="rosterOpen = true">Miembros</button>
                <button class="action-button action-button--primary" type="button" @click="openPrepare">Iniciar</button>
              </div>
            </div>
            <p v-if="!canManageArrival" class="body-copy">Modo solo lectura para miembros de la liga.</p>
            <p v-if="isLoading" class="body-copy">Cargando llegada...</p>
            <button v-for="player in sortedPlayers" :key="player.id" class="member-row" :disabled="!canManageArrival" type="button" @click="player.has_arrived || player.current_cut_paid ? togglePlayer(player.id) : (selectedPlayerId = player.id)">
              <div><p class="member-row__name">{{ player.name }}</p><p class="member-row__copy">{{ player.status_message }}</p></div>
              <span :class="['member-chip', player.has_arrived ? 'member-chip--negative' : player.current_cut_paid ? 'member-chip--positive' : 'member-chip--warning']">{{ player.has_arrived ? `#${player.arrival_order}` : player.current_cut_paid ? 'Al dia' : canManageArrival ? 'Pendiente' : 'Ver' }}</span>
            </button>
          </section>

          <section class="app-surface section-stack">
            <div class="section-head">
              <p class="app-kicker section-head__kicker">Invitados</p>
              <button v-if="canManageArrival" class="action-button action-button--ghost" type="button" @click="resetSession">Reiniciar</button>
            </div>
            <div v-if="canManageArrival" class="guest-form">
              <input v-model="guestName" type="text" class="guest-form__input" placeholder="Nombre del invitado" />
              <button class="action-button action-button--secondary" type="button" @click="addGuest">Agregar</button>
            </div>
            <p v-else class="body-copy">Los invitados solo se muestran como referencia.</p>
            <article v-for="guest in payload?.guests ?? []" :key="guest.id" class="guest-row">
              <div><p class="member-row__name">{{ guest.name }}</p><p class="member-row__copy">Pago por invitado: {{ money(payload?.cut.guest_fee_amount_cents ?? 0) }}</p></div>
              <div class="guest-row__actions">
                <button :class="['member-chip', guest.guest_fee_paid ? 'member-chip--positive' : 'member-chip--warning']" type="button" :disabled="!canManageArrival" @click="toggleGuest(guest.id, guest.guest_fee_paid)">{{ guest.guest_fee_paid ? 'Pagado' : 'Pendiente' }}</button>
                <button v-if="canManageArrival" class="member-chip member-chip--negative" type="button" @click="removeGuest(guest.id)">Quitar</button>
              </div>
            </article>
          </section>

          <section v-if="payload?.session.status === 'prepared'" class="app-surface section-stack">
            <p class="app-kicker section-head__kicker">Cola inicial</p>
            <div class="queue-grid">
              <div><p class="body-copy">Pool</p><p v-for="entry in payload?.session.prepared_pool ?? []" :key="entry.id" class="queue-row">{{ entry.name }}</p></div>
              <div><p class="body-copy">Cola</p><p v-for="(entry, index) in payload?.session.prepared_queue ?? []" :key="entry.id" class="queue-row">{{ index + 1 }}. {{ entry.name }}</p></div>
            </div>
          </section>
        </div>
      </div>

      <div v-if="selectedPlayerId !== null && canManageArrival" class="overlay" @click.self="selectedPlayerId = null">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">Registrar llegada</p>
          <p class="body-copy">Si este miembro pago ahora, conserva prioridad.</p>
          <div class="overlay__actions">
            <button class="action-button action-button--secondary" type="button" @click="togglePlayer(selectedPlayerId, false)">Llego sin pagar</button>
            <button class="action-button action-button--primary" type="button" @click="togglePlayer(selectedPlayerId, true)">Pago y llego</button>
          </div>
        </section>
      </div>

      <div v-if="prepareOpen && canManageArrival" class="overlay" @click.self="prepareOpen = false">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">Iniciar jornada</p>
          <p class="body-copy">Confirma el cobro de invitados. Los pendientes saldran de la lista.</p>
          <div class="section-stack">
            <article v-for="guest in payload?.guests ?? []" :key="guest.id" class="guest-row">
              <p class="member-row__name">{{ guest.name }}</p>
              <button :class="['member-chip', guestPayments[guest.id] ? 'member-chip--positive' : 'member-chip--negative']" type="button" @click="guestPayments[guest.id] = !guestPayments[guest.id]">{{ guestPayments[guest.id] ? 'Pagado' : 'Pendiente' }}</button>
            </article>
          </div>
          <div class="overlay__actions">
            <button class="action-button action-button--secondary" type="button" @click="prepareOpen = false">Cerrar</button>
            <button class="action-button action-button--primary" type="button" @click="prepareSession">Confirmar</button>
          </div>
        </section>
      </div>

      <LeagueRosterSheet v-if="payload?.roster_management.can_manage" v-model:is-open="rosterOpen" :roster-management="payload.roster_management" @changed="loadPage" />
    </IonContent>
  </IonPage>
</template>

<style scoped>
.summary-grid,.section-stack,.summary-card,.guest-row__actions,.overlay__panel,.overlay__actions{display:flex;flex-direction:column}
.summary-grid,.section-stack,.overlay__panel,.overlay__actions{gap:12px}
.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}
.summary-card,.member-row,.guest-row,.queue-row{background:#0e1628;border:1px solid rgba(255,255,255,.06);border-radius:16px}
.summary-card{padding:14px}
.summary-card__value,.member-row__name,.member-row__copy,.queue-row,.body-copy{margin:0}
.summary-card__value{margin-top:10px;font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.body-copy,.member-row__copy{font-size:13px;line-height:1.6;color:#94a3b8}
.section-head,.member-row,.guest-row,.guest-form,.section-head__actions{display:flex;align-items:center;gap:12px}
.section-head{justify-content:space-between}
.section-head__actions{justify-content:flex-end}
.section-head__kicker,.overlay__kicker{color:#e5b849}
.member-row,.guest-row{padding:14px;text-align:left}
.member-row__name{font-size:15px;font-weight:700;color:#f8fafc}
.member-chip,.action-button{min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);font-size:12px;font-weight:700}
.member-chip{display:inline-flex;align-items:center;justify-content:center;padding:0 12px}
.member-chip--positive,.action-button--primary{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
.member-chip--negative{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
.guest-form{align-items:stretch}
.guest-form__input{width:100%;min-height:48px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0e1628;padding:0 14px;color:#f8fafc}
.overlay__actions .action-button{width:100%}
.action-button--secondary{background:#131b2f;color:#f8fafc}
.action-button--ghost{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
.queue-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
.queue-row{padding:12px 14px;font-size:13px;color:#f8fafc}
.overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(3,7,18,.72);padding:16px}
.overlay__panel{width:min(100%,480px);border:1px solid rgba(255,255,255,.06);border-radius:28px 28px 20px 20px;background:#1a243a;padding:18px 16px 20px}
</style>
