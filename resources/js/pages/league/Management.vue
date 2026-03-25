<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Landmark, Receipt, Settings2, Users, Wallet } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import LeagueRosterManagerDialog from '@/components/league/LeagueRosterManagerDialog.vue';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatMoney } from '@/lib/league';
import type { BreadcrumbItem } from '@/types';

type RosterManagement = { can_manage: boolean; active_players: Array<{ id: number; name: string; jersey_number: number | null }>; inactive_players: Array<{ id: number; name: string; jersey_number: number | null }>; referral_options: Array<{ id: number; name: string }>; referral_credit_amount_cents: number };
type PaymentRow = { player: { id: number; name: string; jersey_number: number | null }; balance: { status: string; amount_paid_cents: number; extra_credit_cents: number; available_referral_credit_cents: number; previous_debt_cents: number; settlement_due_cents: number; status_tone: string; status_message: string } };
type ExpenseRow = { id: number; name: string; amount_cents: number; is_system_generated: boolean; is_fixed: boolean };
type ReferralGroup = { referrer: { id: number; name: string }; available_credit_cents: number; members: Array<{ id: number; name: string }> };
type ModulePayload = {
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string };
    cut_selector: { selected_cut_id: number; cuts: Array<{ id: number; label: string; is_active: boolean }> };
    summary: { selected_cut: { id: number; label: string; starts_on: string; ends_on: string; due_on: string; is_past_due: boolean }; income: { cash_payments_cents: number; guest_income_cents: number; total_cents: number }; expenses: { total_cents: number }; balance_cents: number };
    payments: PaymentRow[];
    expenses: ExpenseRow[];
    board: { members: Array<{ id: number; name: string; share_cents: number }>; share_cents: number };
    settings: { sessions_limit: number; game_days: string[]; cut_day: number; member_fee_amount_cents: number; guest_fee_amount_cents: number; referral_credit_amount_cents: number };
    referrals: ReferralGroup[];
    roster_management: RosterManagement;
};

const props = defineProps<{ module: ModulePayload }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gestion', href: '/liga/gestion' }];
const paymentFilter = ref<'all' | 'pending'>('all');
const selectedPayment = ref<PaymentRow | null>(null);
const paymentForm = reactive({ amount_cents: '', apply_referral_credit: false });
const expenseForm = reactive({ name: '', amount_cents: '', is_fixed: false });
const referralForm = reactive({ referrer_player_id: '', referred_player_id: '' });
const settingsForm = reactive({ sessions_limit: props.module.settings.sessions_limit, cut_day: props.module.settings.cut_day, game_days: [...props.module.settings.game_days], member_fee_amount_cents: props.module.settings.member_fee_amount_cents / 100, guest_fee_amount_cents: props.module.settings.guest_fee_amount_cents / 100, referral_credit_amount_cents: props.module.settings.referral_credit_amount_cents / 100 });
const gameDayOptions = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
const filteredPayments = computed(() => paymentFilter.value === 'all' ? props.module.payments : props.module.payments.filter((payment) => payment.balance.status !== 'paid'));

function onCutChange(event: Event): void { const target = event.target as HTMLSelectElement; router.get('/liga/gestion', { cut_id: Number(target.value) }, { preserveScroll: true, preserveState: true }); }
function openPaymentModal(payment: PaymentRow): void { selectedPayment.value = payment; paymentForm.amount_cents = String(Math.max(0, payment.balance.settlement_due_cents) / 100); paymentForm.apply_referral_credit = false; }
function submitPayment(): void { if (!selectedPayment.value) return; router.post(`/liga/gestion/payments/${selectedPayment.value.player.id}`, { cut_id: props.module.cut_selector.selected_cut_id, amount_cents: paymentForm.amount_cents ? Math.round(Number(paymentForm.amount_cents) * 100) : 0, apply_referral_credit: paymentForm.apply_referral_credit }, { preserveScroll: true, onSuccess: () => { selectedPayment.value = null; } }); }
function removePayment(): void { if (!selectedPayment.value) return; router.delete(`/liga/gestion/payments/${selectedPayment.value.player.id}`, { data: { cut_id: props.module.cut_selector.selected_cut_id }, preserveScroll: true, onSuccess: () => { selectedPayment.value = null; } }); }
function submitExpense(): void { if (!expenseForm.name.trim() || !expenseForm.amount_cents) return; router.post('/liga/gestion/expenses', { cut_id: props.module.cut_selector.selected_cut_id, name: expenseForm.name, amount_cents: Math.round(Number(expenseForm.amount_cents) * 100), is_fixed: expenseForm.is_fixed }, { preserveScroll: true, onSuccess: () => { expenseForm.name = ''; expenseForm.amount_cents = ''; expenseForm.is_fixed = false; } }); }
function deleteExpense(expenseId: number): void { router.delete(`/liga/gestion/expenses/${expenseId}`, { preserveScroll: true }); }
function submitReferral(): void { if (!referralForm.referrer_player_id || !referralForm.referred_player_id) return; router.post('/liga/gestion/referrals', { referrer_player_id: Number(referralForm.referrer_player_id), referred_player_id: Number(referralForm.referred_player_id) }, { preserveScroll: true, onSuccess: () => { referralForm.referrer_player_id = ''; referralForm.referred_player_id = ''; } }); }
function deleteReferral(referralId: number): void { router.delete(`/liga/gestion/referrals/${referralId}`, { preserveScroll: true }); }
function toggleGameDay(day: string): void { settingsForm.game_days = settingsForm.game_days.includes(day) ? settingsForm.game_days.filter((entry) => entry !== day) : [...settingsForm.game_days, day]; }
function updateSettings(): void { router.post('/liga/gestion/settings', { sessions_limit: settingsForm.sessions_limit, cut_day: settingsForm.cut_day, game_days: settingsForm.game_days, member_fee_amount_cents: Math.round(settingsForm.member_fee_amount_cents * 100), guest_fee_amount_cents: Math.round(settingsForm.guest_fee_amount_cents * 100), referral_credit_amount_cents: Math.round(settingsForm.referral_credit_amount_cents * 100) }, { preserveScroll: true }); }
function downloadReport(): void { window.open(`/liga/gestion/report?cut_id=${props.module.cut_selector.selected_cut_id}`, '_blank', 'noopener,noreferrer'); }
</script>

<template>
    <Head title="Gestion" />
    <LeagueShellLayout :breadcrumbs="breadcrumbs" :league-name="props.module.league.name" :league-emoji="props.module.league.emoji" :role-label="props.module.role.label" active-module="gestion" :can-manage-league="true">
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_320px]">
            <article class="app-surface space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-3">
                        <p class="app-kicker text-[#E5B849]">Gestion por corte</p>
                        <h1 class="app-display text-[42px] leading-[0.92] text-[#F8FAFC]">{{ props.module.summary.selected_cut.label }}</h1>
                    </div>
                    <select :value="props.module.cut_selector.selected_cut_id" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" @change="onCutChange">
                        <option v-for="cut in props.module.cut_selector.cuts" :key="cut.id" :value="cut.id">{{ cut.label }}{{ cut.is_active ? ' · activo' : '' }}</option>
                    </select>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"><p class="app-kicker">Entradas</p><p class="mt-3 text-[24px] font-semibold text-[#4ADE80]">{{ formatMoney(props.module.summary.income.total_cents) }}</p></div>
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"><p class="app-kicker">Gastos</p><p class="mt-3 text-[24px] font-semibold text-[#FCA5A5]">{{ formatMoney(props.module.summary.expenses.total_cents) }}</p></div>
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"><p class="app-kicker">Balance</p><p class="mt-3 text-[24px] font-semibold text-[#E5B849]">{{ formatMoney(props.module.summary.balance_cents) }}</p></div>
                </div>
            </article>
            <article class="app-surface space-y-3">
                <p class="app-kicker text-[#E5B849]">Acciones</p>
                <LeagueRosterManagerDialog :roster-management="props.module.roster_management" trigger-label="Gestionar miembros" />
                <Button type="button" class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]" @click="downloadReport"><Download class="mr-2 size-4" />Guardar reporte PDF</Button>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_340px]">
            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="app-kicker text-[#E5B849]">Registro de pagos</p>
                    <div class="flex gap-2">
                        <button type="button" class="min-h-10 rounded-[10px] border px-3 text-xs font-semibold" :class="paymentFilter === 'all' ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'" @click="paymentFilter = 'all'">Todos</button>
                        <button type="button" class="min-h-10 rounded-[10px] border px-3 text-xs font-semibold" :class="paymentFilter === 'pending' ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#0E1628] text-[#94A3B8]'" @click="paymentFilter = 'pending'">Pendientes</button>
                    </div>
                </div>
                <div class="space-y-3" :class="filteredPayments.length > 10 ? 'max-h-[680px] overflow-y-auto pr-1' : ''">
                    <button v-for="payment in filteredPayments" :key="payment.player.id" type="button" class="w-full rounded-[16px] border border-white/6 bg-[#0E1628] p-4 text-left transition hover:border-[rgba(229,184,73,0.24)]" @click="openPaymentModal(payment)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-full border border-white/6 bg-[#131B2F]"><CheckCircle2 v-if="payment.balance.status === 'paid'" class="size-4 text-[#4ADE80]" /><CircleAlert v-else class="size-4 text-[#E5B849]" /></div>
                                <div class="min-w-0"><p class="text-[15px] font-semibold text-[#F8FAFC]">{{ payment.player.name }}</p><p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">{{ payment.balance.status_message }}</p><p class="mt-2 text-[12px] text-[#94A3B8]">Pagado: {{ formatMoney(payment.balance.amount_paid_cents) }} · A favor: {{ formatMoney(payment.balance.extra_credit_cents) }}</p></div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="payment.balance.status_tone === 'paid' ? 'bg-[rgba(74,222,128,0.12)] text-[#4ADE80]' : payment.balance.status_tone === 'arrears' ? 'bg-[rgba(248,113,113,0.12)] text-[#FCA5A5]' : 'bg-[rgba(229,184,73,0.12)] text-[#E5B849]'">{{ payment.balance.status }}</span>
                        </div>
                    </button>
                </div>
            </article>
            <div class="grid gap-4">
                <article class="app-surface space-y-4"><div class="flex items-center gap-3"><Wallet class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Ingresos del corte</p></div><div class="grid gap-3"><div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">Cuotas cobradas</p><p class="mt-3 text-[18px] font-semibold text-[#4ADE80]">{{ formatMoney(props.module.summary.income.cash_payments_cents) }}</p></div><div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">Ingresos por invitados</p><p class="mt-3 text-[18px] font-semibold text-[#4ADE80]">{{ formatMoney(props.module.summary.income.guest_income_cents) }}</p></div></div></article>
                <article class="app-surface space-y-4"><div class="flex items-center gap-3"><Landmark class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Directiva</p></div><div class="grid gap-3 sm:grid-cols-2"><div v-for="member in props.module.board.members" :key="member.id" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">{{ member.name }}</p><p class="mt-3 text-[18px] font-semibold text-[#E5B849]">{{ formatMoney(member.share_cents) }}</p></div></div></article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3"><Receipt class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Gastos del corte</p></div>
                <div class="grid gap-3">
                    <div v-for="expense in props.module.expenses" :key="expense.id" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="text-sm font-semibold text-[#F8FAFC]">{{ expense.name }}</p><p class="mt-2 text-[12px] text-[#94A3B8]">{{ expense.is_system_generated ? 'Gasto fijo del sistema' : expense.is_fixed ? 'Gasto fijo' : 'Gasto manual' }}</p></div>
                            <div class="text-right"><p class="text-[16px] font-semibold text-[#F8FAFC]">{{ formatMoney(expense.amount_cents) }}</p><button v-if="!expense.is_system_generated" type="button" class="mt-2 text-[12px] font-semibold text-[#FCA5A5]" @click="deleteExpense(expense.id)">Eliminar</button></div>
                        </div>
                    </div>
                </div>
                <div class="grid gap-3 rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                    <input v-model="expenseForm.name" type="text" placeholder="Nombre del gasto" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none" />
                    <input v-model="expenseForm.amount_cents" type="number" min="1" placeholder="Monto en pesos" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none" />
                    <label class="flex items-center gap-3 rounded-[12px] border border-white/6 bg-[#131B2F] px-4 py-3 text-sm text-[#F8FAFC]"><input v-model="expenseForm.is_fixed" type="checkbox" class="size-4 rounded border-white/10 bg-[#0E1628]" />Marcar como gasto fijo</label>
                    <Button type="button" variant="secondary" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]" @click="submitExpense">Guardar gasto</Button>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3"><Users class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Referidos</p></div>
                <div v-if="props.module.referrals.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">Sin referidos registrados.</div>
                <div v-for="group in props.module.referrals" :key="group.referrer.id" class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-[#F8FAFC]">{{ group.referrer.name }}</p><p class="mt-2 text-[12px] text-[#94A3B8]">{{ group.members.map((member) => member.name).join(', ') }}</p></div><p class="text-[12px] font-semibold text-[#E5B849]">{{ formatMoney(group.available_credit_cents) }}</p></div>
                    <div class="mt-3 flex flex-wrap gap-2"><button v-for="member in group.members" :key="member.id" type="button" class="rounded-full border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-3 py-1 text-[11px] font-semibold text-[#FCA5A5]" @click="deleteReferral(member.id)">Quitar {{ member.name }}</button></div>
                </div>
                <div class="grid gap-3 rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                    <select v-model="referralForm.referrer_player_id" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"><option value="">Referidor</option><option v-for="player in props.module.roster_management.referral_options" :key="player.id" :value="player.id">{{ player.name }}</option></select>
                    <select v-model="referralForm.referred_player_id" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"><option value="">Referido</option><option v-for="player in props.module.roster_management.referral_options" :key="player.id" :value="player.id">{{ player.name }}</option></select>
                    <p class="text-[12px] text-[#94A3B8]">Credito actual por referido: {{ formatMoney(props.module.settings.referral_credit_amount_cents) }}.</p>
                    <Button type="button" variant="secondary" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]" @click="submitReferral">Registrar referido</Button>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3"><Settings2 class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Configurar jornadas</p></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm text-[#F8FAFC]"><span>Jornadas por corte</span><input v-model.number="settingsForm.sessions_limit" type="number" min="1" max="12" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" /></label>
                    <label class="grid gap-2 text-sm text-[#F8FAFC]"><span>Dias de Corte</span><input v-model.number="settingsForm.cut_day" type="number" min="1" max="30" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" /></label>
                    <label class="grid gap-2 text-sm text-[#F8FAFC]"><span>Cuota Membresia</span><input v-model.number="settingsForm.member_fee_amount_cents" type="number" min="1" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" /></label>
                    <label class="grid gap-2 text-sm text-[#F8FAFC]"><span>Cuota Invitados</span><input v-model.number="settingsForm.guest_fee_amount_cents" type="number" min="1" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" /></label>
                    <label class="grid gap-2 text-sm text-[#F8FAFC] sm:col-span-2"><span>Credito por referido</span><input v-model.number="settingsForm.referral_credit_amount_cents" type="number" min="1" class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none" /></label>
                </div>
                <div class="flex flex-wrap gap-2"><button v-for="day in gameDayOptions" :key="day" type="button" class="min-h-10 rounded-full border px-3 text-xs font-semibold" :class="settingsForm.game_days.includes(day) ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]' : 'border-white/6 bg-[#131B2F] text-[#94A3B8]'" @click="toggleGameDay(day)">{{ day }}</button></div>
                <Button type="button" class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]" @click="updateSettings">Guardar configuracion</Button>
            </article>
            <article class="app-surface space-y-4"><div class="flex items-center gap-3"><Wallet class="size-5 text-[#E5B849]" /><p class="app-kicker text-[#E5B849]">Balance de la liga</p></div><div class="grid gap-3"><div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">Total entradas</p><p class="mt-3 text-[24px] font-semibold text-[#4ADE80]">{{ formatMoney(props.module.summary.income.total_cents) }}</p></div><div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">Total gastos</p><p class="mt-3 text-[24px] font-semibold text-[#FCA5A5]">{{ formatMoney(props.module.summary.expenses.total_cents) }}</p></div><div class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"><p class="text-sm font-semibold text-[#F8FAFC]">Balance final</p><p class="mt-3 text-[24px] font-semibold text-[#E5B849]">{{ formatMoney(props.module.summary.balance_cents) }}</p></div></div></article>
        </section>

        <Dialog :open="selectedPayment !== null" @update:open="selectedPayment = null">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">{{ selectedPayment?.player.name }}</DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">{{ selectedPayment?.balance.status_message }}</DialogDescription>
                </DialogHeader>
                <div v-if="selectedPayment" class="grid gap-3">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-[14px] border border-white/6 bg-[#131B2F] p-4"><p class="text-[12px] text-[#94A3B8]">Monto pagado</p><p class="mt-2 text-[18px] font-semibold text-[#F8FAFC]">{{ formatMoney(selectedPayment.balance.amount_paid_cents) }}</p></div>
                        <div class="rounded-[14px] border border-white/6 bg-[#131B2F] p-4"><p class="text-[12px] text-[#94A3B8]">Monto a favor</p><p class="mt-2 text-[18px] font-semibold text-[#E5B849]">{{ formatMoney(selectedPayment.balance.extra_credit_cents) }}</p></div>
                        <div class="rounded-[14px] border border-white/6 bg-[#131B2F] p-4"><p class="text-[12px] text-[#94A3B8]">Pendiente total</p><p class="mt-2 text-[18px] font-semibold text-[#F8FAFC]">{{ formatMoney(selectedPayment.balance.settlement_due_cents) }}</p></div>
                    </div>
                    <input v-model="paymentForm.amount_cents" type="number" min="0" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none" placeholder="Monto en pesos" />
                    <label class="flex items-center gap-3 rounded-[12px] border border-white/6 bg-[#131B2F] px-4 py-3 text-sm text-[#F8FAFC]"><input v-model="paymentForm.apply_referral_credit" type="checkbox" class="size-4 rounded border-white/10 bg-[#0E1628]" />Aplicar credito disponible ({{ formatMoney(selectedPayment.balance.available_referral_credit_cents) }})</label>
                    <p v-if="selectedPayment.balance.previous_debt_cents > 0" class="rounded-[12px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-4 py-3 text-[13px] text-[#FCA5A5]">Tiene deuda anterior por {{ formatMoney(selectedPayment.balance.previous_debt_cents) }}.</p>
                </div>
                <DialogFooter class="grid gap-2 sm:grid-cols-3">
                    <Button type="button" variant="secondary" class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]" @click="selectedPayment = null">Cerrar</Button>
                    <Button type="button" class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]" @click="submitPayment">Guardar pago</Button>
                    <Button type="button" variant="destructive" class="min-h-12 rounded-[12px]" @click="removePayment">Eliminar cuota</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueShellLayout>
</template>
