<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/league';

type RosterPlayer = {
    id: number;
    name: string;
    jersey_number: number | null;
};

type ReferralOption = {
    id: number;
    name: string;
};

type RosterManagement = {
    can_manage: boolean;
    active_players: RosterPlayer[];
    inactive_players: RosterPlayer[];
    referral_options: ReferralOption[];
    referral_credit_amount_cents: number;
};

const props = defineProps<{
    rosterManagement: RosterManagement;
    triggerLabel?: string;
    triggerClass?: string;
}>();

const roleOptions = [
    { value: 'league_admin', label: 'Administrador' },
    { value: 'member', label: 'Miembro' },
];

const inviteForm = reactive({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    account_role: 'member',
});
const editPlayerId = ref<number | null>(null);
const editForm = reactive({
    display_name: '',
    jersey_number: '',
});

const allPlayers = computed(() => [
    ...props.rosterManagement.active_players,
    ...props.rosterManagement.inactive_players,
]);

function resetInviteForm(): void {
    inviteForm.first_name = '';
    inviteForm.last_name = '';
    inviteForm.document_id = '';
    inviteForm.phone = '';
    inviteForm.address = '';
    inviteForm.email = '';
    inviteForm.account_role = 'member';
}

function openEdit(player: RosterPlayer): void {
    editPlayerId.value = player.id;
    editForm.display_name = player.name;
    editForm.jersey_number = player.jersey_number?.toString() ?? '';
}

function submitInvite(): void {
    router.post(
        '/liga/gestion/players',
        {
            first_name: inviteForm.first_name,
            last_name: inviteForm.last_name,
            document_id: inviteForm.document_id || null,
            phone: inviteForm.phone || null,
            address: inviteForm.address || null,
            email: inviteForm.email,
            account_role: inviteForm.account_role,
        },
        {
            preserveScroll: true,
            onSuccess: resetInviteForm,
        },
    );
}

function submitEdit(): void {
    if (!editPlayerId.value) {
        return;
    }

    router.patch(
        `/liga/gestion/players/${editPlayerId.value}`,
        {
            display_name: editForm.display_name,
            jersey_number: editForm.jersey_number
                ? Number(editForm.jersey_number)
                : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editPlayerId.value = null;
                editForm.display_name = '';
                editForm.jersey_number = '';
            },
        },
    );
}

function toggleStatus(playerId: number, active: boolean): void {
    router.patch(
        `/liga/gestion/players/${playerId}/status`,
        { active },
        {
            preserveScroll: true,
        },
    );
}

function handleEditSelection(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const value = Number(target.value);
    const player = allPlayers.value.find((item) => item.id === value);

    if (player) {
        openEdit(player);
    }
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <button
                type="button"
                :class="
                    props.triggerClass ??
                    'inline-flex min-h-12 items-center justify-center rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm font-semibold text-[#F8FAFC] transition active:scale-[0.97] active:opacity-80'
                "
            >
                {{ props.triggerLabel ?? 'Gestionar miembros' }}
            </button>
        </DialogTrigger>

        <DialogContent
            class="max-h-[90vh] w-[min(92vw,72rem)] overflow-hidden border-white/8 bg-[#1A243A] text-[#F8FAFC]"
        >
            <DialogHeader class="space-y-3">
                <DialogTitle class="app-display text-[28px]">
                    Gestion de miembros
                </DialogTitle>
                <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                    Invita usuarios reales a la liga y administra el roster operativo desde el mismo panel.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-y-auto pr-1">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <section class="space-y-4">
                        <article class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                            <p class="app-kicker text-[#E5B849]">Invitar miembro</p>
                            <div class="mt-4 grid gap-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="inviteForm.first_name"
                                        type="text"
                                        placeholder="Nombre"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    />
                                    <input
                                        v-model="inviteForm.last_name"
                                        type="text"
                                        placeholder="Apellido"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="inviteForm.document_id"
                                        type="text"
                                        placeholder="Cedula"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    />
                                    <select
                                        v-model="inviteForm.account_role"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    >
                                        <option
                                            v-for="role in roleOptions"
                                            :key="role.value"
                                            :value="role.value"
                                        >
                                            {{ role.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="inviteForm.phone"
                                        type="text"
                                        placeholder="Telefono"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    />
                                    <input
                                        v-model="inviteForm.email"
                                        type="email"
                                        placeholder="Correo"
                                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    />
                                </div>
                                <input
                                    v-model="inviteForm.address"
                                    type="text"
                                    placeholder="Direccion"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                />
                                <Button
                                    type="button"
                                    class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                                    @click="submitInvite"
                                >
                                    Enviar invitacion
                                </Button>
                            </div>
                        </article>

                        <article class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                            <p class="app-kicker text-[#E5B849]">Miembros activos</p>
                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="player in props.rosterManagement.active_players"
                                    :key="player.id"
                                    class="rounded-[14px] border border-white/6 bg-[#131B2F] p-3"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-[#F8FAFC]">
                                                {{ player.name }}
                                            </p>
                                            <p class="mt-1 text-[12px] text-[#94A3B8]">
                                                #{{ player.jersey_number ?? 'Sin numero' }}
                                            </p>
                                        </div>

                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="min-h-10 rounded-[10px] border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-3 text-xs font-semibold text-[#F8FAFC]"
                                                @click="openEdit(player)"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="min-h-10 rounded-[10px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] px-3 text-xs font-semibold text-[#FCA5A5]"
                                                @click="toggleStatus(player.id, false)"
                                            >
                                                Dar de baja
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="props.rosterManagement.active_players.length === 0"
                                    class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                                >
                                    No hay miembros activos registrados todavia.
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="space-y-4">
                        <article class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                            <p class="app-kicker text-[#E5B849]">Editar miembro</p>
                            <div class="mt-4 grid gap-3">
                                <select
                                    :value="editPlayerId ?? ''"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                    @change="handleEditSelection"
                                >
                                    <option value="">Selecciona un miembro</option>
                                    <option
                                        v-for="player in allPlayers"
                                        :key="player.id"
                                        :value="player.id"
                                    >
                                        {{ player.name }}
                                    </option>
                                </select>
                                <input
                                    v-model="editForm.display_name"
                                    type="text"
                                    placeholder="Nombre visible"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                />
                                <input
                                    v-model="editForm.jersey_number"
                                    type="number"
                                    min="0"
                                    max="99"
                                    placeholder="Numero"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]"
                                    @click="submitEdit"
                                >
                                    Actualizar miembro
                                </Button>
                            </div>
                        </article>

                        <article class="rounded-[16px] border border-white/6 bg-[#0E1628] p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="app-kicker text-[#E5B849]">Miembros inactivos</p>
                                <span class="text-[12px] text-[#94A3B8]">
                                    {{ props.rosterManagement.inactive_players.length }}
                                </span>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="player in props.rosterManagement.inactive_players"
                                    :key="player.id"
                                    class="rounded-[14px] border border-white/6 bg-[#131B2F] p-3"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-[#F8FAFC]">
                                                {{ player.name }}
                                            </p>
                                            <p class="mt-1 text-[12px] text-[#94A3B8]">
                                                #{{ player.jersey_number ?? 'Sin numero' }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="min-h-10 rounded-[10px] border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-3 text-xs font-semibold text-[#4ADE80]"
                                            @click="toggleStatus(player.id, true)"
                                        >
                                            Reactivar
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="props.rosterManagement.inactive_players.length === 0"
                                    class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                                >
                                    No hay miembros dados de baja.
                                </div>
                            </div>
                        </article>
                    </section>
                </div>
            </div>

            <DialogFooter class="border-t border-white/6 pt-4">
                <p class="text-[12px] text-[#94A3B8]">
                    Los referidos acumulan {{ formatMoney(props.rosterManagement.referral_credit_amount_cents) }} de credito por miembro.
                </p>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
