<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleAlert,
    CircleDot,
    ListOrdered,
    RefreshCcw,
    UserPlus,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
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
    session_entry_id: number | null;
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

type QueuePreviewEntry = {
    id: number;
    position: number;
    name: string;
    is_guest: boolean;
    jersey_number: number | null;
    arrival_order: number;
    preferred_position: string | null;
};

type RosterManagement = {
    can_manage: boolean;
    active_players: Array<{
        id: number;
        name: string;
        jersey_number: number | null;
        first_name: string;
        last_name: string;
        document_id: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        account_role: 'league_admin' | 'member';
        invitation_pending: boolean;
    }>;
    inactive_players: Array<{
        id: number;
        name: string;
        jersey_number: number | null;
        first_name: string;
        last_name: string;
        document_id: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        account_role: 'league_admin' | 'member';
        invitation_pending: boolean;
    }>;
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
            paid_guests: number;
            draft_ready_entries: number;
            draft_ready: boolean;
        };
        prepared_pool: Array<{ id: number; name: string; entry_type: string }>;
        prepared_queue: Array<{ id: number; name: string; entry_type: string }>;
    };
    players: PlayerRow[];
    guests: GuestRow[];
    queue_preview: {
        can_reorder: boolean;
        entries: QueuePreviewEntry[];
    };
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
const prepareError = ref('');
const prepareSubmitting = ref(false);
const guestPayments = reactive<Record<number, boolean>>({});
const queuePreviewEntries = ref<QueuePreviewEntry[]>([]);
const reorderDialogOpen = ref(false);
const reorderEntryId = ref<number | null>(null);
const reorderTargetPosition = ref<number>(1);
const reorderError = ref('');
const reorderSubmitting = ref(false);

const canManageArrival = computed(() => props.module.role.can_manage);
const sortedPlayers = computed(() => props.module.players);
const liveArrivalLocked = computed(() =>
    ['prepared', 'in_progress'].includes(props.module.session.status),
);
const prepareLocked = computed(() =>
    ['prepared', 'in_progress'].includes(props.module.session.status),
);
const draftStatus = computed(() => {
    if (!canManageArrival.value) {
        return null;
    }

    return props.module.session.counts.draft_ready
        ? { label: 'Draft listo', className: 'app-badge-positive' }
        : { label: 'Sin draft', className: 'app-badge-negative' };
});

watch(
    () => props.module.queue_preview.entries,
    (entries) => {
        queuePreviewEntries.value = entries.map((entry) => ({ ...entry }));
    },
    { immediate: true, deep: true },
);

const queuePreviewPositionById = computed(
    () => new Map(queuePreviewEntries.value.map((entry) => [entry.id, entry.position])),
);
const arrivalDisplayPositionByEntryId = computed(() => {
    const positions = new Map<number, number>();
    const queuedIds = new Set<number>();
    let nextPosition = 1;

    queuePreviewEntries.value.forEach((entry) => {
        positions.set(entry.id, nextPosition);
        queuedIds.add(entry.id);
        nextPosition += 1;
    });

    props.module.guests
        .filter((guest) => !queuedIds.has(guest.id))
        .sort((left, right) => left.arrival_order - right.arrival_order)
        .forEach((guest) => {
            positions.set(guest.id, nextPosition);
            nextPosition += 1;
        });

    return positions;
});

function guestQueueLabel(guest: GuestRow): string {
    const queuedPosition = arrivalDisplayPositionByEntryId.value.get(guest.id);

    if (queuedPosition !== undefined) {
        return `Cola #${queuedPosition}`;
    }

    return `Llegada #${guest.arrival_order}`;
}

function queuePreviewMeta(entry: QueuePreviewEntry): string {
    const labels = [`Cola #${entry.position}`];

    if (!entry.is_guest) {
        labels.push(`Llegada #${entry.arrival_order}`);
    }

    if (entry.preferred_position) {
        labels.push(entry.preferred_position);
    }

    return labels.join(' · ');
}

function memberQueueLabel(player: PlayerRow): string {
    if (player.session_entry_id === null) {
        return 'Solo lectura';
    }

    const queuedPosition = arrivalDisplayPositionByEntryId.value.get(player.session_entry_id);

    if (queuedPosition !== undefined) {
        return `Cola #${queuedPosition}`;
    }

    return `Llegada #${player.arrival_order}`;
}

const reorderableEntries = computed(() => queuePreviewEntries.value);
const selectedReorderEntry = computed(() =>
    reorderableEntries.value.find((entry) => entry.id === reorderEntryId.value) ?? null,
);
const arrivedMembersCount = computed(() => props.module.session.counts.arrived_members);

function openReorderDialog(entryId: number): void {
    if (!props.module.queue_preview.can_reorder) {
        return;
    }

    const entry = reorderableEntries.value.find((item) => item.id === entryId);

    if (!entry) {
        return;
    }

    reorderEntryId.value = entry.id;
    reorderTargetPosition.value = entry.position;
    reorderError.value = '';
    reorderDialogOpen.value = true;
}

function closeReorderDialog(): void {
    reorderDialogOpen.value = false;
    reorderEntryId.value = null;
    reorderTargetPosition.value = 1;
    reorderError.value = '';
    reorderSubmitting.value = false;
}

function applyPregameReorder(entryId: number, targetPosition: number): number[] {
    const reordered = [...reorderableEntries.value];
    const fromIndex = reordered.findIndex((entry) => entry.id === entryId);
    const toIndex = targetPosition - 1;

    if (fromIndex === -1 || toIndex < 0 || toIndex >= reordered.length) {
        return reordered.map((entry) => entry.id);
    }

    const [movedEntry] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, movedEntry);

    return reordered.map((entry) => entry.id);
}

function submitReorderDialog(): void {
    const entry = selectedReorderEntry.value;

    if (!entry || reorderSubmitting.value) {
        return;
    }

    if (entry.is_guest && arrivedMembersCount.value > 10 && reorderTargetPosition.value < 11) {
        reorderError.value = 'Con mas de 10 miembros llegados, los invitados solo pueden ir desde la posicion 11.';

        return;
    }

    reorderError.value = '';
    reorderSubmitting.value = true;

    router.post(
        '/liga/llegada/cola/reorder',
        {
            entry_ids: applyPregameReorder(entry.id, reorderTargetPosition.value),
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                reorderError.value = String(
                    errors.entry_ids ?? errors.session ?? 'No se pudo actualizar la posicion.',
                );
            },
            onFinish: () => {
                reorderSubmitting.value = false;
            },
            onSuccess: () => {
                closeReorderDialog();
            },
        },
    );
}

function statusIcon(player: PlayerRow) {
    if (player.current_cut_paid) {
        return CheckCircle2;
    }

    return props.module.cut.is_past_due ? CircleAlert : CircleDot;
}

function enterPlayer(player: PlayerRow): void {
    if (!canManageArrival.value) {
        return;
    }

    if (player.has_arrived) {
        if (liveArrivalLocked.value) {
            return;
        }

        router.post(
            `/liga/llegada/players/${player.id}/toggle`,
            {},
            { preserveScroll: true },
        );

        return;
    }

    if (player.current_cut_paid) {
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
    if (!canManageArrival.value || prepareLocked.value) {
        return;
    }

    prepareError.value = '';
    props.module.guests.forEach((guest) => {
        guestPayments[guest.id] = guest.guest_fee_paid;
    });
    prepareDialogOpen.value = true;
}

function prepareSession(): void {
    if (!canManageArrival.value || prepareSubmitting.value) {
        return;
    }

    prepareError.value = '';
    router.post(
        '/liga/llegada/prepare',
        {
            guest_payments: props.module.guests.map((guest) => ({
                id: guest.id,
                paid: Boolean(guestPayments[guest.id]),
            })),
        },
        {
            preserveScroll: false,
            onStart: () => {
                prepareSubmitting.value = true;
            },
            onError: (errors) => {
                prepareError.value = String(
                    errors.session ??
                        errors.guest_payments ??
                        'No se pudo iniciar la jornada. Verifica la lista y vuelve a intentar.',
                );
            },
            onFinish: () => {
                prepareSubmitting.value = false;
            },
        },
    );
}

function resetSession(): void {
    if (!canManageArrival.value) {
        return;
    }

    if (!window.confirm('¿Reiniciar la lista de llegada de hoy?')) {
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
                        <h1 class="app-display app-module-title text-[#F8FAFC]">
                            {{ props.module.cut.label }}
                        </h1>
                        <p class="text-[14px] leading-7 text-[#94A3B8]">
                            {{
                                props.module.cut.is_past_due
                                    ? 'El corte ya venció. Solo mantienen prioridad quienes están al día.'
                                    : 'Todavía estás dentro del plazo. Todos los miembros siguen entrando con prioridad de corte.'
                            }}
                        </p>
                    </div>

                    <div
                        :class="
                            draftStatus?.className ??
                            (props.module.cut.is_past_due
                                ? 'app-badge-negative'
                                : 'app-badge-positive')
                        "
                        class="min-w-0 justify-center px-4 text-center sm:min-w-[172px] sm:px-5 sm:whitespace-nowrap"
                    >
                        {{
                            draftStatus?.label ??
                            (props.module.cut.is_past_due
                                ? 'Plazo vencido'
                                : 'Corte activo')
                        }}
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <p class="app-kicker">Miembros</p>
                        <p
                            class="mt-3 text-[28px] font-semibold text-[#F8FAFC]"
                        >
                            {{ props.module.session.counts.arrived_members }}/{{
                                props.module.session.counts.total_members
                            }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">
                            Llegadas marcadas hoy.
                        </p>
                    </div>
                    <div
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <p class="app-kicker">Invitados</p>
                        <p
                            class="mt-3 text-[28px] font-semibold text-[#F8FAFC]"
                        >
                            {{ props.module.session.counts.guests }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">
                            Cobro por invitado:
                            {{
                                formatMoney(
                                    props.module.cut.guest_fee_amount_cents,
                                )
                            }}.
                        </p>
                    </div>
                    <div
                        class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <p class="app-kicker">Jornada</p>
                        <p
                            class="mt-3 text-[20px] font-semibold text-[#F8FAFC]"
                        >
                            {{
                                props.module.session.status === 'in_progress'
                                    ? 'En juego'
                                    : props.module.session.status === 'prepared'
                                      ? 'Preparada'
                                      : 'Abierta'
                            }}
                        </p>
                        <p class="mt-2 text-[12px] text-[#94A3B8]">
                            {{
                                prepareLocked
                                    ? 'La jornada ya está activa. Las llegadas nuevas se agregan directo a la cola operativa.'
                                    : `Necesitas al menos 10 integrantes listos para iniciar. Ahora mismo hay ${props.module.session.counts.draft_ready_entries}.`
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
                        :disabled="prepareLocked"
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
                    Tu rol en esta liga es de solo lectura. Puedes ver la cola,
                    los miembros y los invitados, pero no ejecutar acciones
                    operativas.
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_340px]">
            <article class="app-surface">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Miembros</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Antes del primer juego, los miembros solo protegen
                            las primeras 10 posiciones. Después de ahí, la cola
                            conserva el orden ya establecido.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]"
                    >
                        {{ props.module.session.counts.arrived_members }}/{{
                            props.module.session.counts.total_members
                        }}
                    </span>
                </div>

                <div class="app-divider-list mt-5">
                    <div
                        v-for="player in sortedPlayers"
                        :key="player.id"
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-full border border-white/6 bg-[#0E1628]"
                            >
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
                                    <p
                                        class="text-[15px] font-semibold text-[#F8FAFC]"
                                    >
                                        {{ player.name }}
                                    </p>
                                    <span
                                        class="rounded-full border border-white/6 bg-[#0E1628] px-2 py-0.5 text-[11px] text-[#94A3B8]"
                                    >
                                        #{{ player.jersey_number ?? 'S/N' }}
                                    </span>
                                    <button
                                        v-if="player.has_arrived"
                                        type="button"
                                        class="rounded-full border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-2 py-0.5 text-[11px] font-semibold text-[#4ADE80] transition active:scale-[0.97] active:opacity-80"
                                        :disabled="!props.module.queue_preview.can_reorder || player.session_entry_id === null"
                                        @click="player.session_entry_id !== null ? openReorderDialog(player.session_entry_id) : null"
                                    >
                                        {{ memberQueueLabel(player) }}
                                    </button>
                                </div>
                                <p
                                    class="mt-2 text-[13px] leading-6 text-[#94A3B8]"
                                >
                                    {{ player.status_message }}
                                </p>
                                <p class="mt-1 text-[11px] text-[#64748B]">
                                    {{ player.attendance_count }} jornadas
                                    registradas.
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="canManageArrival"
                            type="button"
                            class="inline-flex min-h-12 items-center justify-center rounded-[12px] border px-4 text-sm font-semibold transition active:scale-[0.97] active:opacity-80"
                            :class="
                                player.has_arrived && !liveArrivalLocked
                                    ? 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#FCA5A5]'
                                    : player.has_arrived
                                      ? 'border-white/6 bg-[#0E1628] text-[#94A3B8]'
                                      : player.current_cut_paid
                                        ? 'border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] text-[#4ADE80]'
                                        : 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                            "
                            :disabled="player.has_arrived && liveArrivalLocked"
                            @click="enterPlayer(player)"
                        >
                            {{
                                player.has_arrived && liveArrivalLocked
                                    ? 'Ya registrado'
                                    : player.has_arrived
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
                            {{
                                player.has_arrived
                                    ? memberQueueLabel(player)
                                    : 'Solo lectura'
                            }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="app-kicker text-[#E5B849]">Invitados</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Los invitados solo entran al pool o a la cola si
                            tienen el pago confirmado.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]"
                    >
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
                        Los invitados se muestran solo como referencia para
                        miembros de la liga.
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
                                <button
                                    type="button"
                                    class="mt-1 rounded-full border border-white/6 bg-[#131B2F] px-2 py-0.5 text-[12px] text-[#94A3B8] transition active:scale-[0.97] active:opacity-80"
                                    :disabled="!props.module.queue_preview.can_reorder"
                                    @click="openReorderDialog(guest.id)"
                                >
                                    {{ guestQueueLabel(guest) }}
                                </button>
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
                                    {{
                                        guest.guest_fee_paid
                                            ? 'Pago confirmado'
                                            : 'Pendiente'
                                    }}
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

        <section class="app-surface space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <ListOrdered class="size-5 text-[#E5B849]" />
                        <p class="app-kicker text-[#E5B849]">Cola inicial</p>
                    </div>
                    <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                        Las primeras 10 posiciones alimentan el draft. Usa el
                        numero de llegada en miembros o invitados para mover a
                        alguien a otra posicion antes del primer juego.
                    </p>
                </div>
                <span
                    class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]"
                >
                    {{ queuePreviewEntries.length }}
                </span>
            </div>

            <div
                v-if="queuePreviewEntries.length === 0"
                class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]"
            >
                La cola inicial se llena con los miembros llegados y los
                invitados pagados.
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="entry in queuePreviewEntries"
                    :key="entry.id"
                    class="flex items-center justify-between gap-3 rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p
                                    class="text-[15px] font-semibold text-[#F8FAFC]"
                                >
                                    {{ entry.name }}
                                </p>
                                <span
                                    class="rounded-full border border-white/6 bg-[#131B2F] px-2 py-0.5 text-[11px] text-[#94A3B8]"
                                >
                                    {{
                                        entry.is_guest
                                            ? 'Invitado'
                                            : `#${entry.jersey_number ?? 'S/N'}`
                                    }}
                                </span>
                                <span
                                    v-if="entry.position <= 10"
                                    class="rounded-full border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-2 py-0.5 text-[11px] font-semibold text-[#4ADE80]"
                                >
                                    Draft
                                </span>
                            </div>
                            <p class="mt-2 text-[12px] text-[#94A3B8]">
                                {{ queuePreviewMeta(entry) }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full border border-white/6 bg-[#131B2F] px-3 py-1 text-[11px] font-semibold text-[#F8FAFC]"
                    >
                        #{{ entry.position }}
                    </span>
                </div>
            </div>
        </section>

        <Dialog :open="reorderDialogOpen" @update:open="(open) => { if (!open) closeReorderDialog(); }">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC] sm:max-w-[420px]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">
                        Cambiar posicion
                    </DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        {{
                            selectedReorderEntry
                                ? `Mueve a ${selectedReorderEntry.name} dentro de la cola inicial.`
                                : ''
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-[#F8FAFC]">
                            Nueva posicion
                        </label>
                        <select
                            v-model.number="reorderTargetPosition"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none"
                        >
                            <option
                                v-for="entry in reorderableEntries"
                                :key="`position-${entry.id}`"
                                :value="entry.position"
                            >
                                Posicion #{{ entry.position }}
                            </option>
                        </select>
                        <p
                            v-if="selectedReorderEntry?.is_guest && arrivedMembersCount > 10"
                            class="text-[12px] leading-5 text-[#FCA5A5]"
                        >
                            Este invitado solo puede colocarse desde la posicion 11 en adelante.
                        </p>
                        <p
                            v-if="reorderError"
                            class="rounded-[12px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-3 py-2 text-[12px] text-[#FCA5A5]"
                        >
                            {{ reorderError }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        class="border border-white/8 bg-[#0E1628]"
                        @click="closeReorderDialog"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        class="bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        :disabled="reorderSubmitting"
                        @click="submitReorderDialog"
                    >
                        {{ reorderSubmitting ? 'Moviendo...' : 'Guardar posicion' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="selectedPlayer !== null"
            @update:open="selectedPlayer = null"
        >
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">
                        Registrar llegada
                    </DialogTitle>
                    <DialogDescription
                        class="text-[13px] leading-6 text-[#94A3B8]"
                    >
                        {{
                            selectedPlayer
                                ? `${selectedPlayer.name} aún no está al día. Si paga ahora mismo, conserva prioridad.`
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
                        Llegó sin pagar
                    </Button>
                    <Button
                        type="button"
                        class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                        @click="submitSelectedPlayer(true)"
                    >
                        Pagó y llegó
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="prepareDialogOpen"
            @update:open="prepareDialogOpen = false"
        >
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">
                        Iniciar jornada
                    </DialogTitle>
                    <DialogDescription
                        class="text-[13px] leading-6 text-[#94A3B8]"
                    >
                        Confirma el pago de invitados antes de preparar la cola
                        inicial. Solo los pagos quedarán habilitados para
                        entrar.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <div
                        v-if="prepareError"
                        class="rounded-[14px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] p-4 text-sm text-[#FCA5A5]"
                    >
                        {{ prepareError }}
                    </div>

                    <div
                        v-if="props.module.guests.length === 0"
                        class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                    >
                        No hay invitados para validar. La jornada se iniciará
                        solo con miembros.
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
                                {{
                                    formatMoney(
                                        props.module.cut.guest_fee_amount_cents,
                                    )
                                }}
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
                            @click="
                                guestPayments[guest.id] =
                                    !guestPayments[guest.id]
                            "
                        >
                            {{
                                guestPayments[guest.id] ? 'Pagado' : 'Pendiente'
                            }}
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
                        :disabled="prepareSubmitting"
                        @click="prepareSession"
                    >
                        {{
                            prepareSubmitting
                                ? 'Preparando...'
                                : 'Confirmar jornada'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueShellLayout>
</template>
