<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleAlert,
    CircleDot,
    ListOrdered,
    RefreshCcw,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import LeagueRosterManagerDialog from '@/components/league/LeagueRosterManagerDialog.vue';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/league';
import type { BreadcrumbItem } from '@/types';

type PlayerRow = {
    id: number;
    name: string;
    jersey_number: number | null;
    attendance_count: number;
    arrival_order: number | null;
    has_arrived: boolean;
    current_cut_paid: boolean;
    status_tone: string;
    status_message: string;
};

type GuestRow = {
    id: number;
    name: string;
    arrival_order: number;
    guest_fee_paid: boolean;
};

type RosterManagement = {
    can_manage: boolean;
    active_players: Array<{ id: number; name: string; jersey_number: number | null }>;
    inactive_players: Array<{ id: number; name: string; jersey_number: number | null }>;
    referral_options: Array<{ id: number; name: string }>;
    referral_credit_amount_cents: number;
};

type ModulePayload = {
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    cut: {
        id: number;
        label: string;
        starts_on: string;
        ends_on: string;
        due_on: string;
        is_past_due: boolean;
        member_fee_amount_cents: number;
        guest_fee_amount_cents: number;
    };
    session: {
        id: number | null;
        status: string;
        session_date: string | null;
        prepared_at: string | null;
        started_at: string | null;
        counts: {
            arrived_members: number;
            total_members: number;
            guests: number;
        };
        prepared_pool: Array<{ id: number; name: string; entry_type: string }>;
        prepared_queue: Array<{ id: number; name: string; entry_type: string }>;
    };
    players: PlayerRow[];
    guests: GuestRow[];
    roster_management: RosterManagement;
};

const props = defineProps<{
    module: ModulePayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Llegada',
        href: '/liga/llegada',
    },
];

const guestForm = reactive({
    guest_name: '',
});
const selectedPlayer = ref<PlayerRow | null>(null);
const prepareDialogOpen = ref(false);
const guestPayments = reactive<Record<number, boolean>>({});

const canManageArrival = computed(() => props.module.role.can_manage);
const sortedPlayers = computed(() =>
    [...props.module.players].sort((left, right) => {
        if (left.has_arrived !== right.has_arrived) {
            return left.has_arrived ? -1 : 1;
        }

        if (left.current_cut_paid !== right.current_cut_paid) {
            return left.current_cut_paid ? -1 : 1;
        }

        return left.name.localeCompare(right.name);
    }),
);

function statusIcon(player: PlayerRow) {
    if (player.current_cut_paid) {
        return CheckCircle2;
    }

    return props.module.cut.is_past_due ? CircleAlert : CircleDot;
}

function enterPlayer(player: PlayerRow): void {
    if (!canManageArrival.value || props.module.session.status === 'prepared') {
        return;
    }

    if (player.has_arrived || player.current_cut_paid) {
        router.post(
            `/liga/llegada/players/${player.id}/toggle`,
            {},
            { preserveScroll: true },
        );

        return;
    }

    selectedPlayer.value = player;
}

function submitSelectedPlayer(paid: boolean): void {
    if (!selectedPlayer.value || !canManageArrival.value) {
        return;
    }

    router.post(
        `/liga/llegada/players/${selectedPlayer.value.id}/toggle`,
        { paid },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedPlayer.value = null;
            },
        },
    );
}

function addGuest(): void {
    if (!canManageArrival.value || !guestForm.guest_name.trim()) {
        return;
    }

    router.post(
        '/liga/llegada/guests',
        {
            guest_name: guestForm.guest_name,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                guestForm.guest_name = '';
            },
        },
    );
}

function toggleGuestPayment(guest: GuestRow): void {
    if (!canManageArrival.value) {
        return;
    }

    router.patch(
        `/liga/llegada/guests/${guest.id}`,
        {
            guest_fee_paid: !guest.guest_fee_paid,
        },
        {
            preserveScroll: true,
        },
    );
}

function deleteGuest(guest: GuestRow): void {
    if (!canManageArrival.value) {
        return;
    }

    router.delete(`/liga/llegada/guests/${guest.id}`, {
        preserveScroll: true,
    });
}

function openPrepareDialog(): void {
    if (!canManageArrival.value) {
        return;
    }

    props.module.guests.forEach((guest) => {
        guestPayments[guest.id] = guest.guest_fee_paid;
    });
    prepareDialogOpen.value = true;
}

function prepareSession(): void {
    if (!canManageArrival.value) {
        return;
    }

    router.post(
        '/liga/llegada/prepare',
        {
            guest_payments: props.module.guests.map((guest) => ({
                id: guest.id,
                paid: Boolean(guestPayments[guest.id]),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                prepareDialogOpen.value = false;
            },
        },
    );
}

function resetSession(): void {
    if (!canManageArrival.value) {
        return;
    }

    if (!window.confirm('Reiniciar la lista de llegada de hoy?')) {
        return;
    }

    router.post(
        '/liga/llegada/reset',
        {},
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Llegada" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.module.league.name"
        :league-emoji="props.module.league.emoji"
        :role-label="props.module.role.label"
        active-module="llegada"
        :can-manage-league="props.module.role.can_manage"
    >
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_340px]">
            <article class="app-surface space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-3">
                        <p class="app-kicker text-[#E5B849]">Corte activo</p>
                        <h1 class="app-display text-[42px] leading-[0.92] text-[#F8FAFC]">
                            {{ props.module.cut.label }}
                        </h1>
                        <p class="text-[14px] leading-7 text-[#94A3B8]">
                            {{
                                props.module.cut.is_past_due
                                    ? 'El corte ya vencio. Solo mantienen prioridad quienes estan al dia.'
                                    : 'Todavia estas dentro del plazo. Todos los miembros siguen entrando con prioridad de corte.'
                            }}
                        </p>
                    </div>

                    <div
                        :class="
                            props.module.cut.is_past_due
                                ? 'app-badge-negative'
                                : 'app-badge-positive'
                        "
                    >
                        {{ props.module.cut.is_past_due ? 'Plazo vencido' : 'Prioridad abierta' }}
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                        <p class="app-kicker">Miembros</p>
                        <p class="mt-3 text-[28px] font-semibold text-[#F8FAFC]">
                            {{ props.module.session.counts.arrived_members }}/{{ props.module.session.counts.total_members }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">Llegadas marcadas hoy.</p>
                    </div>
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                        <p class="app-kicker">Invitados</p>
                        <p class="mt-3 text-[28px] font-semibold text-[#F8FAFC]">
                            {{ props.module.session.counts.guests }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">
                            Cobro por invitado: {{ formatMoney(props.module.cut.guest_fee_amount_cents) }}.
                        </p>
                    </div>
                    <div class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                        <p class="app-kicker">Jornada</p>
                        <p class="mt-3 text-[20px] font-semibold text-[#F8FAFC]">
                            {{ props.module.session.status === 'prepared' ? 'Preparada' : 'Abierta' }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">
                            {{
                                props.module.session.status === 'prepared'
                                    ? 'La cola inicial ya quedo sembrada para Juego.'
                                    : 'Necesitas al menos 10 miembros para iniciar.'
                            }}
                        </p>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <p class="app-kicker text-[#E5B849]">Acciones</p>

                <div v-if="canManageArrival" class="grid gap-3">
                    <LeagueRosterManagerDialog
                        v-if="props.module.roster_management.can_manage"
                        :roster-management="props.module.roster_management"
                        trigger-label="Gestionar miembros"
                    />

                    <Button
                        type="button"
                        class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        :disabled="props.module.session.status === 'prepared'"
                        @click="openPrepareDialog"
                    >
                        Iniciar jornada
                    </Button>

                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[12px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-4 text-sm font-semibold text-[#FCA5A5]"
                        @click="resetSession"
                    >
                        <RefreshCcw class="size-4" />
                        Reiniciar lista de llegada
                    </button>
                </div>

                <p
                    v-else
                    class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4 text-[13px] leading-6 text-[#94A3B8]"
                >
                    Tu rol en esta liga es de solo lectura. Puedes ver la cola, los miembros y los invitados, pero no ejecutar acciones operativas.
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_340px]">
            <article class="app-surface">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Miembros</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            La prioridad cambia segun pago y fecha de corte, pero la llegada siempre conserva orden interno.
                        </p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.module.session.counts.arrived_members }}/{{ props.module.session.counts.total_members }}
                    </span>
                </div>

                <div class="mt-5 app-divider-list">
                    <div
                        v-for="player in sortedPlayers"
                        :key="player.id"
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-full border border-white/6 bg-[#0E1628]">
                                <component
                                    :is="statusIcon(player)"
                                    :class="
                                        player.current_cut_paid
                                            ? 'size-4 text-[#4ADE80]'
                                            : props.module.cut.is_past_due
                                              ? 'size-4 text-[#FCA5A5]'
                                              : 'size-4 text-[#E5B849]'
                                    "
                                />
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-[15px] font-semibold text-[#F8FAFC]">
                                        {{ player.name }}
                                    </p>
                                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-2 py-0.5 text-[11px] text-[#94A3B8]">
                                        #{{ player.jersey_number ?? 'S/N' }}
                                    </span>
                                    <span
                                        v-if="player.has_arrived"
                                        class="rounded-full border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-2 py-0.5 text-[11px] font-semibold text-[#4ADE80]"
                                    >
                                        Llegada #{{ player.arrival_order }}
                                    </span>
                                </div>
                                <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                                    {{ player.status_message }}
                                </p>
                                <p class="mt-1 text-[11px] text-[#64748B]">
                                    {{ player.attendance_count }} jornadas registradas.
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="canManageArrival"
                            type="button"
                            class="inline-flex min-h-12 items-center justify-center rounded-[12px] border px-4 text-sm font-semibold transition active:scale-[0.97] active:opacity-80"
                            :class="
                                player.has_arrived
                                    ? 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#FCA5A5]'
                                    : player.current_cut_paid
                                      ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]'
                                      : 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                            "
                            :disabled="props.module.session.status === 'prepared'"
                            @click="enterPlayer(player)"
                        >
                            {{
                                player.has_arrived
                                    ? 'Quitar llegada'
                                    : player.current_cut_paid
                                      ? 'Confirmar llegada'
                                      : 'Registrar llegada'
                            }}
                        </button>

                        <span
                            v-else
                            class="inline-flex min-h-12 items-center justify-center rounded-[12px] border border-white/6 bg-[#0E1628] px-4 text-sm font-semibold text-[#94A3B8]"
                        >
                            {{ player.has_arrived ? `Llegada #${player.arrival_order}` : 'Solo lectura' }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Invitados</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Los invitados sin pago confirmado salen automaticamente al iniciar la jornada.
                        </p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.module.guests.length }}
                    </span>
                </div>

                <div class="grid gap-3">
                    <div
                        v-if="canManageArrival"
                        class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <input
                            v-model="guestForm.guest_name"
                            type="text"
                            placeholder="Nombre del invitado"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none"
                        />
                        <button
                            type="button"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[12px] border border-white/6 bg-[#131B2F] px-4 text-sm font-semibold text-[#F8FAFC]"
                            :disabled="props.module.session.status === 'prepared'"
                            @click="addGuest"
                        >
                            <UserPlus class="size-4" />
                            Agregar
                        </button>
                    </div>

                    <p
                        v-else
                        class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
                    >
                        Los invitados se muestran solo como referencia para miembros de la liga.
                    </p>

                    <div
                        v-if="props.module.guests.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
                    >
                        No hay invitados en la lista de llegada.
                    </div>

                    <div
                        v-for="guest in props.module.guests"
                        :key="guest.id"
                        class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#F8FAFC]">
                                    {{ guest.name }}
                                </p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">
                                    Llegada #{{ guest.arrival_order }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="min-h-10 rounded-[10px] border px-3 text-xs font-semibold"
                                    :class="
                                        guest.guest_fee_paid
                                            ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]'
                                            : 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                                    "
                                    :disabled="!canManageArrival"
                                    @click="toggleGuestPayment(guest)"
                                >
                                    {{ guest.guest_fee_paid ? 'Pago confirmado' : 'Pendiente' }}
                                </button>
                                <button
                                    v-if="canManageArrival"
                                    type="button"
                                    class="min-h-10 rounded-[10px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-3 text-xs font-semibold text-[#FCA5A5]"
                                    @click="deleteGuest(guest)"
                                >
                                    Quitar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-if="props.module.session.status === 'prepared'"
            class="grid gap-4 xl:grid-cols-2"
        >
            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <Users class="size-5 text-[#E5B849]" />
                    <p class="app-kicker text-[#E5B849]">Pool inicial</p>
                </div>
                <div class="grid gap-2">
                    <div
                        v-for="entry in props.module.session.prepared_pool"
                        :key="entry.id"
                        class="rounded-[12px] border border-white/6 bg-[#0E1628] px-4 py-3 text-sm text-[#F8FAFC]"
                    >
                        {{ entry.name }}
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center gap-3">
                    <ListOrdered class="size-5 text-[#E5B849]" />
                    <p class="app-kicker text-[#E5B849]">Cola inicial</p>
                </div>
                <div class="grid gap-2">
                    <div
                        v-for="(entry, index) in props.module.session.prepared_queue"
                        :key="entry.id"
                        class="rounded-[12px] border border-white/6 bg-[#0E1628] px-4 py-3 text-sm text-[#F8FAFC]"
                    >
                        {{ index + 1 }}. {{ entry.name }}
                    </div>
                </div>
            </article>
        </section>

        <Dialog :open="selectedPlayer !== null" @update:open="selectedPlayer = null">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">
                        Registrar llegada
                    </DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        {{
                            selectedPlayer
                                ? `${selectedPlayer.name} aun no esta al dia. Si pago ahora mismo, conserva prioridad.`
                                : ''
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="grid gap-2 sm:grid-cols-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]"
                        @click="submitSelectedPlayer(false)"
                    >
                        Llego sin pagar
                    </Button>
                    <Button
                        type="button"
                        class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        @click="submitSelectedPlayer(true)"
                    >
                        Pago y llego
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="prepareDialogOpen" @update:open="prepareDialogOpen = false">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">
                        Iniciar jornada
                    </DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Confirma el pago de invitados antes de preparar la cola inicial. Los pendientes saldran de la lista.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <div
                        v-if="props.module.guests.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                    >
                        No hay invitados para validar. La jornada se iniciara solo con miembros.
                    </div>
                    <div
                        v-for="guest in props.module.guests"
                        :key="guest.id"
                        class="flex items-center justify-between rounded-[14px] border border-white/6 bg-[#131B2F] p-4"
                    >
                        <div>
                            <p class="text-sm font-semibold text-[#F8FAFC]">
                                {{ guest.name }}
                            </p>
                            <p class="mt-1 text-[12px] text-[#94A3B8]">
                                {{ formatMoney(props.module.cut.guest_fee_amount_cents) }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="min-h-10 rounded-[10px] border px-3 text-xs font-semibold"
                            :class="
                                guestPayments[guest.id]
                                    ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]'
                                    : 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#FCA5A5]'
                            "
                            @click="guestPayments[guest.id] = !guestPayments[guest.id]"
                        >
                            {{ guestPayments[guest.id] ? 'Pagado' : 'Pendiente' }}
                        </button>
                    </div>
                </div>

                <DialogFooter class="grid gap-2 sm:grid-cols-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]"
                        @click="prepareDialogOpen = false"
                    >
                        Volver
                    </Button>
                    <Button
                        type="button"
                        class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        @click="prepareSession"
                    >
                        Confirmar jornada
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueShellLayout>
</template>
