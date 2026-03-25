<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { Search } from 'lucide-vue-next';
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

type RosterTab = 'invite' | 'edit' | 'active' | 'inactive';

const props = defineProps<{
    rosterManagement: RosterManagement;
    triggerLabel?: string;
    triggerClass?: string;
}>();

const roleOptions = [
    { value: 'league_admin', label: 'Administrador' },
    { value: 'member', label: 'Miembro' },
];
const tabs: Array<{ key: RosterTab; label: string }> = [
    { key: 'invite', label: 'Invitar' },
    { key: 'edit', label: 'Editar' },
    { key: 'active', label: 'Activos' },
    { key: 'inactive', label: 'Inactivos' },
];

const dialogOpen = ref(false);
const activeTab = ref<RosterTab>('invite');
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
const editSearch = ref('');
const editForm = reactive({
    jersey_number: '',
});

const allPlayers = computed(() => [
    ...props.rosterManagement.active_players,
    ...props.rosterManagement.inactive_players,
]);
const filteredPlayers = computed(() => {
    const tokens = editSearch.value
        .trim()
        .toLocaleLowerCase()
        .split(/\s+/)
        .filter(Boolean);

    if (tokens.length === 0) {
        return allPlayers.value;
    }

    return allPlayers.value.filter((player) => {
        const searchable = `${player.name} ${player.jersey_number ?? ''}`.toLocaleLowerCase();

        return tokens.every((token) => searchable.includes(token));
    });
});
const selectedPlayer = computed(
    () => allPlayers.value.find((player) => player.id === editPlayerId.value) ?? null,
);
const showFilteredPlayers = computed(
    () =>
        editSearch.value.trim().length > 0
        && (
            !selectedPlayer.value
            || editSearch.value.trim().toLocaleLowerCase() !== selectedPlayer.value.name.toLocaleLowerCase()
        ),
);

watch(dialogOpen, (open) => {
    if (open) {
        activeTab.value = 'invite';
    }
});

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
    editSearch.value = player.name;
    editForm.jersey_number = player.jersey_number?.toString() ?? '';
    activeTab.value = 'edit';
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
            display_name: editSearch.value.trim(),
            jersey_number: editForm.jersey_number
                ? Number(editForm.jersey_number)
                : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editPlayerId.value = null;
                editSearch.value = '';
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
</script>

<template>
    <Dialog v-model:open="dialogOpen">
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
            class="max-h-[92vh] w-[min(92vw,46rem)] overflow-hidden border-white/8 bg-[#1A243A] p-0 text-[#F8FAFC]"
        >
            <div class="flex max-h-[92vh] flex-col">
                <DialogHeader class="space-y-3 border-b border-white/6 px-5 pb-4 pt-5">
                    <DialogTitle class="app-display text-[28px]">
                        Gestion de miembros
                    </DialogTitle>
                    <DialogDescription class="text-[13px] leading-6 text-[#94A3B8]">
                        Invita usuarios reales a la liga y administra el roster operativo desde un mismo panel.
                    </DialogDescription>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="min-h-11 rounded-full border px-4 text-sm font-semibold transition active:scale-[0.97] active:opacity-80"
                            :class="
                                activeTab === tab.key
                                    ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                                    : 'border-white/8 bg-[#0E1628] text-[#94A3B8]'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </DialogHeader>

                <div class="dialog-scroll min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <article
                        v-if="activeTab === 'invite'"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
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

                    <article
                        v-else-if="activeTab === 'edit'"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <p class="app-kicker text-[#E5B849]">Editar miembro</p>
                        <div class="mt-4 grid gap-3">
                            <div
                                class="flex min-h-12 items-center gap-3 rounded-[12px] border border-white/8 bg-[#131B2F] px-4"
                            >
                                <Search class="size-4 text-[#94A3B8]" />
                                <input
                                    v-model="editSearch"
                                    type="text"
                                    placeholder="Busca por nombre o numero"
                                    class="h-full w-full bg-transparent text-sm text-[#F8FAFC] outline-none"
                                />
                            </div>

                            <div
                                v-if="showFilteredPlayers"
                                class="dialog-scroll max-h-[220px] space-y-2 overflow-y-auto pr-1"
                            >
                                <button
                                    v-for="player in filteredPlayers"
                                    :key="player.id"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-[12px] border px-4 py-3 text-left text-sm font-semibold transition"
                                    :class="
                                        editPlayerId === player.id
                                            ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                                            : 'border-white/8 bg-[#131B2F] text-[#F8FAFC]'
                                    "
                                    @click="openEdit(player)"
                                >
                                    <span>{{ player.name }}</span>
                                    <span class="text-[12px] text-[#94A3B8]">
                                        #{{ player.jersey_number ?? 'S/N' }}
                                    </span>
                                </button>

                                <div
                                    v-if="filteredPlayers.length === 0"
                                    class="rounded-[12px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                                >
                                    No encontramos miembros con ese filtro.
                                </div>
                            </div>

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
                                :disabled="!selectedPlayer || !editSearch.trim()"
                                @click="submitEdit"
                            >
                                Actualizar miembro
                            </Button>
                        </div>
                    </article>

                    <article
                        v-else-if="activeTab === 'active'"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#E5B849]">Miembros activos</p>
                            <span class="text-[12px] text-[#94A3B8]">
                                {{ props.rosterManagement.active_players.length }}
                            </span>
                        </div>

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

                    <article
                        v-else
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
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
                </div>

                <DialogFooter class="border-t border-white/6 px-5 pb-5 pt-4">
                    <p class="text-[12px] text-[#94A3B8]">
                        Los referidos acumulan {{ formatMoney(props.rosterManagement.referral_credit_amount_cents) }} de credito por miembro.
                    </p>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.dialog-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(229, 184, 73, 0.5) rgba(14, 22, 40, 0.92);
}

.dialog-scroll::-webkit-scrollbar {
    width: 10px;
}

.dialog-scroll::-webkit-scrollbar-track {
    border-radius: 999px;
    background: rgba(14, 22, 40, 0.92);
}

.dialog-scroll::-webkit-scrollbar-thumb {
    border: 2px solid rgba(14, 22, 40, 0.92);
    border-radius: 999px;
    background: rgba(229, 184, 73, 0.5);
}

.dialog-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(229, 184, 73, 0.72);
}
</style>
