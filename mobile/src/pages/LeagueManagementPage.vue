<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent, onIonViewWillEnter } from '@ionic/vue'
import { reactive, ref } from 'vue'
import LeagueRosterSheet from '@/components/LeagueRosterSheet.vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { addLeagueExpense, addLeagueReferral, deleteLeagueExpense, deleteLeagueReferral, downloadLeagueManagementReport, fetchLeagueManagement, recordLeaguePayment, removeLeaguePayment, type LeagueManagementPayload, updateLeagueSettings } from '@/services/league'

const payload = ref<LeagueManagementPayload | null>(null)
const isLoading = ref(false)
const paymentFilter = ref<'all' | 'pending'>('all')
const selectedPayment = ref<LeagueManagementPayload['payments'][number] | null>(null)
const rosterOpen = ref(false)
const paymentForm = reactive({ amount: '', apply_referral_credit: false })
const expenseForm = reactive({ name: '', amount: '', is_fixed: false })
const referralForm = reactive({ referrer_player_id: '', referred_player_id: '' })
const settingsForm = reactive({ sessions_limit: 4, cut_day: 15, game_days: ['Sabado'], member_fee_amount: 600, guest_fee_amount: 100, referral_credit_amount: 200 })
const gameDayOptions = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo']

function money(amountCents: number): string { return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(amountCents / 100) }

async function loadPage(cutId?: number): Promise<void> {
  isLoading.value = true
  try {
    payload.value = await fetchLeagueManagement(cutId)
    if (payload.value) {
      settingsForm.sessions_limit = payload.value.settings.sessions_limit
      settingsForm.cut_day = payload.value.settings.cut_day
      settingsForm.game_days = [...payload.value.settings.game_days]
      settingsForm.member_fee_amount = payload.value.settings.member_fee_amount_cents / 100
      settingsForm.guest_fee_amount = payload.value.settings.guest_fee_amount_cents / 100
      settingsForm.referral_credit_amount = payload.value.settings.referral_credit_amount_cents / 100
    }
  } finally {
    isLoading.value = false
  }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
  try {
    await loadPage(payload.value?.cut_selector.selected_cut_id)
  } finally {
    await (event.target as HTMLIonRefresherElement).complete()
  }
}

onIonViewWillEnter(() => loadPage())

async function onCutChange(event: Event): Promise<void> { const target = event.target as HTMLSelectElement; await loadPage(Number(target.value)) }
function filteredPayments(): LeagueManagementPayload['payments'] { const payments = payload.value?.payments ?? []; return paymentFilter.value === 'all' ? payments : payments.filter((payment) => payment.balance.status !== 'paid') }
function openPayment(payment: LeagueManagementPayload['payments'][number]): void { selectedPayment.value = payment; paymentForm.amount = String(Math.max(0, payment.balance.settlement_due_cents) / 100); paymentForm.apply_referral_credit = false }
async function savePayment(): Promise<void> { if (!payload.value || !selectedPayment.value) return; payload.value = await recordLeaguePayment(selectedPayment.value.player.id, { cut_id: payload.value.cut_selector.selected_cut_id, amount_cents: paymentForm.amount ? Math.round(Number(paymentForm.amount) * 100) : 0, apply_referral_credit: paymentForm.apply_referral_credit }); selectedPayment.value = null }
async function clearPayment(): Promise<void> { if (!payload.value || !selectedPayment.value) return; payload.value = await removeLeaguePayment(selectedPayment.value.player.id, payload.value.cut_selector.selected_cut_id); selectedPayment.value = null }
async function saveExpense(): Promise<void> { if (!payload.value || !expenseForm.name.trim() || !expenseForm.amount) return; payload.value = await addLeagueExpense({ cut_id: payload.value.cut_selector.selected_cut_id, name: expenseForm.name, amount_cents: Math.round(Number(expenseForm.amount) * 100), is_fixed: expenseForm.is_fixed }); expenseForm.name = ''; expenseForm.amount = ''; expenseForm.is_fixed = false }
async function removeExpense(expenseId: number): Promise<void> { payload.value = await deleteLeagueExpense(expenseId) }
async function saveReferral(): Promise<void> { if (!referralForm.referrer_player_id || !referralForm.referred_player_id) return; payload.value = await addLeagueReferral({ referrer_player_id: Number(referralForm.referrer_player_id), referred_player_id: Number(referralForm.referred_player_id) }); referralForm.referrer_player_id = ''; referralForm.referred_player_id = '' }
async function removeReferral(referralId: number): Promise<void> { payload.value = await deleteLeagueReferral(referralId) }
function toggleGameDay(day: string): void { settingsForm.game_days = settingsForm.game_days.includes(day) ? settingsForm.game_days.filter((entry) => entry !== day) : [...settingsForm.game_days, day] }
async function saveSettings(): Promise<void> { payload.value = await updateLeagueSettings({ sessions_limit: settingsForm.sessions_limit, cut_day: settingsForm.cut_day, game_days: settingsForm.game_days, member_fee_amount_cents: Math.round(settingsForm.member_fee_amount * 100), guest_fee_amount_cents: Math.round(settingsForm.guest_fee_amount * 100), referral_credit_amount_cents: Math.round(settingsForm.referral_credit_amount * 100) }) }
async function openReport(): Promise<void> { if (!payload.value) return; const url = await downloadLeagueManagementReport(payload.value.cut_selector.selected_cut_id); window.open(url, '_blank') }
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <IonRefresher slot="fixed" @ionRefresh="handleRefresh">
        <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
      </IonRefresher>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar :title="payload?.league.name ?? 'Gestion'" description="Balance, pagos, gastos, referidos y configuracion del corte." />

          <section class="app-surface section-stack">
            <div class="section-head">
              <div><p class="app-kicker section-head__kicker">Corte</p><p class="section-title">{{ payload?.summary.selected_cut.label ?? 'Cargando...' }}</p></div>
              <select class="sheet-input" :value="payload?.cut_selector.selected_cut_id" @change="onCutChange"><option v-for="cut in payload?.cut_selector.cuts ?? []" :key="cut.id" :value="cut.id">{{ cut.label }}{{ cut.is_active ? ' · activo' : '' }}</option></select>
            </div>
            <div class="summary-grid">
              <article class="summary-card"><p class="app-kicker">Entradas</p><p class="summary-card__value">{{ money(payload?.summary.income.total_cents ?? 0) }}</p></article>
              <article class="summary-card"><p class="app-kicker">Gastos</p><p class="summary-card__value">{{ money(payload?.summary.expenses.total_cents ?? 0) }}</p></article>
              <article class="summary-card"><p class="app-kicker">Balance</p><p class="summary-card__value">{{ money(payload?.summary.balance_cents ?? 0) }}</p></article>
            </div>
            <div class="action-grid"><button class="action-button action-button--secondary" type="button" @click="rosterOpen = true">Gestionar miembros</button><button class="action-button action-button--primary" type="button" @click="openReport">Reporte PDF</button></div>
          </section>

          <section class="app-surface section-stack">
            <div class="section-head">
              <p class="app-kicker section-head__kicker">Registro de pagos</p>
              <div class="action-grid action-grid--inline"><button class="member-chip" :class="paymentFilter === 'all' ? 'member-chip--warning' : 'member-chip--neutral'" type="button" @click="paymentFilter = 'all'">Todos</button><button class="member-chip" :class="paymentFilter === 'pending' ? 'member-chip--warning' : 'member-chip--neutral'" type="button" @click="paymentFilter = 'pending'">Pendientes</button></div>
            </div>
            <p v-if="isLoading" class="body-copy">Cargando gestion...</p>
            <button v-for="payment in filteredPayments()" :key="payment.player.id" class="data-row" type="button" @click="openPayment(payment)">
              <div><p class="data-row__name">{{ payment.player.name }}</p><p class="body-copy">{{ payment.balance.status_message }}</p><p class="body-copy">Pagado: {{ money(payment.balance.amount_paid_cents) }} · A favor: {{ money(payment.balance.extra_credit_cents) }}</p></div>
              <span :class="['member-chip', payment.balance.status === 'paid' ? 'member-chip--positive' : payment.balance.status_tone === 'arrears' ? 'member-chip--negative' : 'member-chip--warning']">{{ payment.balance.status }}</span>
            </button>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-head__kicker">Gastos del corte</p>
            <article v-for="expense in payload?.expenses ?? []" :key="expense.id" class="data-row">
              <div><p class="data-row__name">{{ expense.name }}</p><p class="body-copy">{{ expense.is_system_generated ? 'Gasto fijo del sistema' : expense.is_fixed ? 'Gasto fijo' : 'Gasto manual' }}</p></div>
              <div class="data-row__actions"><span class="member-chip member-chip--neutral">{{ money(expense.amount_cents) }}</span><button v-if="!expense.is_system_generated" class="member-chip member-chip--negative" type="button" @click="removeExpense(expense.id)">Eliminar</button></div>
            </article>
            <div class="section-stack"><input v-model="expenseForm.name" type="text" class="sheet-input" placeholder="Nombre del gasto" /><input v-model="expenseForm.amount" type="number" min="1" class="sheet-input" placeholder="Monto en pesos" /><label class="toggle-row"><input v-model="expenseForm.is_fixed" type="checkbox" /><span>Marcar como gasto fijo</span></label><button class="action-button action-button--secondary" type="button" @click="saveExpense">Agregar gasto</button></div>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-head__kicker">Referidos</p>
            <article v-for="group in payload?.referrals ?? []" :key="group.referrer.id" class="data-row">
              <div><p class="data-row__name">{{ group.referrer.name }}</p><p class="body-copy">{{ group.members.map((member) => member.name).join(', ') }}</p></div>
              <div class="data-row__actions"><span class="member-chip member-chip--warning">{{ money(group.available_credit_cents) }}</span><button v-for="member in group.members" :key="member.id" class="member-chip member-chip--negative" type="button" @click="removeReferral(member.id)">Quitar</button></div>
            </article>
            <div class="section-stack"><select v-model="referralForm.referrer_player_id" class="sheet-input"><option value="">Referidor</option><option v-for="player in payload?.roster_management.referral_options ?? []" :key="player.id" :value="player.id">{{ player.name }}</option></select><select v-model="referralForm.referred_player_id" class="sheet-input"><option value="">Referido</option><option v-for="player in payload?.roster_management.referral_options ?? []" :key="player.id" :value="player.id">{{ player.name }}</option></select><p class="body-copy">Credito actual por referido: {{ money(payload?.settings.referral_credit_amount_cents ?? 0) }}</p><button class="action-button action-button--secondary" type="button" @click="saveReferral">Registrar referido</button></div>
          </section>

          <section class="app-surface section-stack">
            <p class="app-kicker section-head__kicker">Configurar jornadas</p>
            <div class="summary-grid summary-grid--two">
              <label class="field-block"><span>Jornadas por corte</span><input v-model.number="settingsForm.sessions_limit" type="number" min="1" max="12" class="sheet-input" /></label>
              <label class="field-block"><span>Dias de Corte</span><input v-model.number="settingsForm.cut_day" type="number" min="1" max="30" class="sheet-input" /></label>
              <label class="field-block"><span>Cuota Membresia</span><input v-model.number="settingsForm.member_fee_amount" type="number" min="1" class="sheet-input" /></label>
              <label class="field-block"><span>Cuota Invitados</span><input v-model.number="settingsForm.guest_fee_amount" type="number" min="1" class="sheet-input" /></label>
              <label class="field-block field-block--full"><span>Credito por referido</span><input v-model.number="settingsForm.referral_credit_amount" type="number" min="1" class="sheet-input" /></label>
            </div>
            <div class="action-grid action-grid--wrap"><button v-for="day in gameDayOptions" :key="day" type="button" class="member-chip" :class="settingsForm.game_days.includes(day) ? 'member-chip--warning' : 'member-chip--neutral'" @click="toggleGameDay(day)">{{ day }}</button></div>
            <button class="action-button action-button--primary" type="button" @click="saveSettings">Guardar configuracion</button>
          </section>
        </div>
      </div>

      <div v-if="selectedPayment !== null" class="overlay" @click.self="selectedPayment = null">
        <section class="overlay__panel">
          <p class="app-kicker overlay__kicker">{{ selectedPayment?.player.name }}</p>
          <p class="body-copy">{{ selectedPayment?.balance.status_message }}</p>
          <div class="summary-grid summary-grid--three">
            <div class="summary-card"><p class="app-kicker">Pagado</p><p class="summary-card__value">{{ money(selectedPayment?.balance.amount_paid_cents ?? 0) }}</p></div>
            <div class="summary-card"><p class="app-kicker">A favor</p><p class="summary-card__value">{{ money(selectedPayment?.balance.extra_credit_cents ?? 0) }}</p></div>
            <div class="summary-card"><p class="app-kicker">Pendiente</p><p class="summary-card__value">{{ money(selectedPayment?.balance.settlement_due_cents ?? 0) }}</p></div>
          </div>
          <input v-model="paymentForm.amount" type="number" min="0" class="sheet-input" placeholder="Monto en pesos" />
          <label class="toggle-row"><input v-model="paymentForm.apply_referral_credit" type="checkbox" /><span>Aplicar credito disponible</span></label>
          <p v-if="selectedPayment?.balance.previous_debt_cents" class="body-copy">Deuda anterior: {{ money(selectedPayment.balance.previous_debt_cents) }}</p>
          <div class="overlay__actions"><button class="action-button action-button--secondary" type="button" @click="selectedPayment = null">Cerrar</button><button class="action-button action-button--primary" type="button" @click="savePayment">Guardar pago</button><button class="action-button action-button--danger" type="button" @click="clearPayment">Eliminar cuota</button></div>
        </section>
      </div>

      <LeagueRosterSheet v-if="payload?.roster_management.can_manage" v-model:is-open="rosterOpen" :roster-management="payload.roster_management" @changed="loadPage(payload?.cut_selector.selected_cut_id)" />
    </IonContent>
  </IonPage>
</template>

<style scoped>
.section-stack,.summary-card,.overlay__panel,.overlay__actions,.data-row__actions,.field-block{display:flex;flex-direction:column}
.section-stack,.overlay__panel,.overlay__actions,.field-block{gap:12px}
.section-head,.data-row,.action-grid,.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.section-head__kicker,.overlay__kicker{color:#e5b849}
.section-title,.summary-card__value,.data-row__name,.body-copy{margin:0}
.section-title,.data-row__name{font-size:16px;font-weight:700;color:#f8fafc}
.summary-grid{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
.summary-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}
.summary-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
.summary-card,.data-row{border:1px solid rgba(255,255,255,.06);border-radius:16px;background:#0e1628;padding:14px}
.summary-card__value{margin-top:10px;font-size:22px;line-height:1;font-weight:700;color:#f8fafc}
.body-copy,.toggle-row,.field-block span{font-size:13px;line-height:1.6;color:#94a3b8}
.data-row{align-items:flex-start}
.data-row__actions{gap:8px}
.sheet-input{min-height:48px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:#0e1628;padding:0 14px;color:#f8fafc}
.action-grid{width:100%}
.action-grid--inline,.action-grid--wrap{width:auto;justify-content:flex-end}
.action-grid--wrap{flex-wrap:wrap}
.field-block--full{grid-column:1/-1}
.action-button,.member-chip{min-height:42px;border-radius:12px;border:1px solid rgba(255,255,255,.06);font-size:12px;font-weight:700}
.action-button{width:100%}
.action-button--primary,.member-chip--positive{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.28);color:#4ade80}
.action-button--secondary,.member-chip--neutral{background:#131b2f;color:#f8fafc}
.action-button--danger,.member-chip--negative{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.28);color:#fca5a5}
.member-chip--warning{background:rgba(229,184,73,.12);border-color:rgba(229,184,73,.28);color:#f8fafc}
.member-chip{display:inline-flex;align-items:center;justify-content:center;padding:0 12px}
.overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;background:rgba(3,7,18,.72);padding:16px}
.overlay__panel{width:min(100%,480px);border:1px solid rgba(255,255,255,.06);border-radius:28px 28px 20px 20px;background:#1a243a;padding:18px 16px 20px}
</style>
