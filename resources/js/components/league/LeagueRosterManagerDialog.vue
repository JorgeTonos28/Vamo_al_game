<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
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
    first_name: string;
    last_name: string;
    document_id: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    account_role: 'league_admin' | 'member';
    invitation_pending: boolean;
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
type RosterField =
    | 'first_name'
    | 'last_name'
    | 'document_id'
    | 'phone'
    | 'address'
    | 'email'
    | 'jersey_number'
    | 'account_role';
type FormFieldErrors = Partial<Record<RosterField, string>>;

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
const inviteSubmitting = ref(false);
const editSubmitting = ref(false);
const feedbackTone = ref<'error' | null>(null);
const feedbackMessages = ref<string[]>([]);
const editPlayerId = ref<number | null>(null);
const editSearch = ref('');
const dialogScrollRef = ref<HTMLElement | null>(null);
const inviteFieldErrors = ref<FormFieldErrors>({});
const editFieldErrors = ref<FormFieldErrors>({});

const inviteForm = reactive({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    jersey_number: '',
    account_role: 'member' as 'league_admin' | 'member',
});
const editForm = reactive({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    jersey_number: '',
    account_role: 'member' as 'league_admin' | 'member',
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
        const searchable = [
            player.name,
            player.document_id ?? '',
            player.email ?? '',
            player.phone ?? '',
            player.jersey_number ?? '',
        ]
            .join(' ')
            .toLocaleLowerCase();

        return tokens.every((token) => searchable.includes(token));
    });
});
const selectedPlayer = computed(
    () =>
        allPlayers.value.find((player) => player.id === editPlayerId.value) ??
        null,
);
const showFilteredPlayers = computed(
    () =>
        editSearch.value.trim().length > 0 &&
        (!selectedPlayer.value ||
            editSearch.value.trim().toLocaleLowerCase() !==
                selectedPlayer.value.name.toLocaleLowerCase()),
);

watch(dialogOpen, (open) => {
    if (!open) {
        clearFeedback();
        clearFieldErrors();

        return;
    }

    activeTab.value = 'invite';
    clearFeedback();
    clearFieldErrors();
});

function clearFeedback(): void {
    feedbackTone.value = null;
    feedbackMessages.value = [];
}

function clearFieldErrors(): void {
    inviteFieldErrors.value = {};
    editFieldErrors.value = {};
}

function normalizeFieldErrors(
    errors: Record<string, string | string[]>,
): FormFieldErrors {
    return Object.entries(errors).reduce<FormFieldErrors>(
        (result, [field, value]) => {
            const messages = Array.isArray(value) ? value : [value];
            const firstMessage = messages.find(
                (entry) => String(entry).trim().length > 0,
            );

            if (firstMessage) {
                result[field as RosterField] = String(firstMessage);
            }

            return result;
        },
        {},
    );
}

function scrollFeedbackToTop(): void {
    nextTick(() => {
        dialogScrollRef.value?.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    });
}

function feedbackFromErrors(
    errors: Record<string, string | string[]>,
    form: 'invite' | 'edit',
): void {
    const messages = Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .map((value) => String(value))
        .filter((value) => value.trim().length > 0);

    if (form === 'invite') {
        inviteFieldErrors.value = normalizeFieldErrors(errors);
        editFieldErrors.value = {};
    } else {
        editFieldErrors.value = normalizeFieldErrors(errors);
        inviteFieldErrors.value = {};
    }

    feedbackTone.value = 'error';
    feedbackMessages.value =
        messages.length > 0
            ? messages
            : [
                  'No se pudo completar la acción. Verifica los datos e intenta de nuevo.',
              ];
    scrollFeedbackToTop();
}

function inputClass(hasError = false): string {
    return [
        'min-h-12 rounded-[12px] border bg-[#131B2F] px-4 text-sm text-[#F8FAFC] outline-none transition',
        hasError
            ? 'border-[rgba(248,113,113,0.48)] focus-visible:border-[rgba(248,113,113,0.68)]'
            : 'border-white/8',
    ].join(' ');
}

function resetInviteForm(): void {
    inviteForm.first_name = '';
    inviteForm.last_name = '';
    inviteForm.document_id = '';
    inviteForm.phone = '';
    inviteForm.address = '';
    inviteForm.email = '';
    inviteForm.jersey_number = '';
    inviteForm.account_role = 'member';
}

function resetEditForm(): void {
    editPlayerId.value = null;
    editSearch.value = '';
    editForm.first_name = '';
    editForm.last_name = '';
    editForm.document_id = '';
    editForm.phone = '';
    editForm.address = '';
    editForm.email = '';
    editForm.jersey_number = '';
    editForm.account_role = 'member';
}

function openEdit(player: RosterPlayer): void {
    editPlayerId.value = player.id;
    editSearch.value = player.name;
    editForm.first_name = player.first_name;
    editForm.last_name = player.last_name;
    editForm.document_id = player.document_id ?? '';
    editForm.phone = player.phone ?? '';
    editForm.address = player.address ?? '';
    editForm.email = player.email ?? '';
    editForm.jersey_number = player.jersey_number?.toString() ?? '';
    editForm.account_role = player.account_role;
    activeTab.value = 'edit';
    clearFeedback();
    editFieldErrors.value = {};
}

function submitInvite(): void {
    clearFeedback();
    clearFieldErrors();
    inviteSubmitting.value = true;

    router.post(
        '/liga/gestion/players',
        {
            first_name: inviteForm.first_name,
            last_name: inviteForm.last_name,
            document_id: inviteForm.document_id,
            phone: inviteForm.phone || null,
            address: inviteForm.address || null,
            email: inviteForm.email || null,
            jersey_number: inviteForm.jersey_number
                ? Number(inviteForm.jersey_number)
                : null,
            account_role: inviteForm.account_role,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetInviteForm();
                dialogOpen.value = false;
            },
            onError: (errors) => {
                feedbackFromErrors(errors, 'invite');
            },
            onFinish: () => {
                inviteSubmitting.value = false;
            },
        },
    );
}

function submitEdit(): void {
    if (!editPlayerId.value) {
        return;
    }

    clearFeedback();
    clearFieldErrors();
    editSubmitting.value = true;

    router.patch(
        `/liga/gestion/players/${editPlayerId.value}`,
        {
            first_name: editForm.first_name,
            last_name: editForm.last_name,
            document_id: editForm.document_id,
            phone: editForm.phone || null,
            address: editForm.address || null,
            email: editForm.email || null,
            jersey_number: editForm.jersey_number
                ? Number(editForm.jersey_number)
                : null,
            account_role: editForm.account_role,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetEditForm();
                activeTab.value = 'active';
            },
            onError: (errors) => {
                feedbackFromErrors(errors, 'edit');
            },
            onFinish: () => {
                editSubmitting.value = false;
            },
        },
    );
}

function toggleStatus(playerId: number, active: boolean): void {
    clearFeedback();

    router.patch(
        `/liga/gestion/players/${playerId}/status`,
        { active },
        {
            preserveScroll: true,
            onError: (errors) => {
                feedbackFromErrors(
                    errors,
                    activeTab.value === 'edit' ? 'edit' : 'invite',
                );
            },
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
                <DialogHeader
                    class="space-y-3 border-b border-white/6 px-5 pt-5 pb-4"
                >
                    <DialogTitle class="app-display text-[28px]">
                        Gestión de miembros
                    </DialogTitle>
                    <DialogDescription
                        class="text-[13px] leading-6 text-[#94A3B8]"
                    >
                        Registra miembros de la liga, envía invitaciones cuando
                        exista correo y administra el roster desde un mismo
                        panel.
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

                <div
                    ref="dialogScrollRef"
                    class="dialog-scroll min-h-0 flex-1 overflow-y-auto px-5 py-5"
                >
                    <div
                        v-if="
                            feedbackTone === 'error' &&
                            feedbackMessages.length > 0
                        "
                        class="mb-4 rounded-[16px] border border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] p-4 text-sm text-[#FCA5A5]"
                    >
                        <p class="font-semibold">
                            No se pudo completar la acción.
                        </p>
                        <ul class="mt-2 space-y-1">
                            <li
                                v-for="message in feedbackMessages"
                                :key="message"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <article
                        v-if="activeTab === 'invite'"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <p class="app-kicker text-[#E5B849]">Invitar miembro</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Nombre, apellido y cédula son obligatorios. Si no
                            agregas correo, el miembro entra a la liga sin
                            invitación por email.
                        </p>
                        <div class="mt-4 grid gap-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input
                                    v-model="inviteForm.first_name"
                                    type="text"
                                    placeholder="Nombre"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                inviteFieldErrors.first_name,
                                            ),
                                        )
                                    "
                                />
                                <input
                                    v-model="inviteForm.last_name"
                                    type="text"
                                    placeholder="Apellido"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                inviteFieldErrors.last_name,
                                            ),
                                        )
                                    "
                                />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <input
                                    v-model="inviteForm.document_id"
                                    type="text"
                                    placeholder="Cédula"
                                    :class="`${inputClass(Boolean(inviteFieldErrors.document_id))} sm:col-span-2`"
                                />
                                <input
                                    v-model="inviteForm.jersey_number"
                                    type="number"
                                    min="0"
                                    max="99"
                                    placeholder="Chaqueta"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                inviteFieldErrors.jersey_number,
                                            ),
                                        )
                                    "
                                />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input
                                    v-model="inviteForm.phone"
                                    type="text"
                                    placeholder="Teléfono"
                                    :class="
                                        inputClass(
                                            Boolean(inviteFieldErrors.phone),
                                        )
                                    "
                                />
                                <input
                                    v-model="inviteForm.email"
                                    type="email"
                                    placeholder="Correo"
                                    :class="
                                        inputClass(
                                            Boolean(inviteFieldErrors.email),
                                        )
                                    "
                                />
                            </div>
                            <div
                                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]"
                            >
                                <input
                                    v-model="inviteForm.address"
                                    type="text"
                                    placeholder="Dirección"
                                    :class="
                                        inputClass(
                                            Boolean(inviteFieldErrors.address),
                                        )
                                    "
                                />
                                <select
                                    v-model="inviteForm.account_role"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                inviteFieldErrors.account_role,
                                            ),
                                        )
                                    "
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
                            <Button
                                type="button"
                                class="min-h-12 rounded-[12px] bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                                :disabled="inviteSubmitting"
                                @click="submitInvite"
                            >
                                {{
                                    inviteSubmitting
                                        ? 'Guardando...'
                                        : 'Agregar miembro'
                                }}
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
                                    placeholder="Busca por nombre, cédula, correo o número"
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
                                    <span class="text-[12px] text-[#94A3B8]"
                                        >#{{
                                            player.jersey_number ?? 'S/N'
                                        }}</span
                                    >
                                </button>

                                <div
                                    v-if="filteredPlayers.length === 0"
                                    class="rounded-[12px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                                >
                                    No encontramos miembros con ese filtro.
                                </div>
                            </div>

                            <div v-if="selectedPlayer" class="grid gap-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="editForm.first_name"
                                        type="text"
                                        placeholder="Nombre"
                                        :class="
                                            inputClass(
                                                Boolean(
                                                    editFieldErrors.first_name,
                                                ),
                                            )
                                        "
                                    />
                                    <input
                                        v-model="editForm.last_name"
                                        type="text"
                                        placeholder="Apellido"
                                        :class="
                                            inputClass(
                                                Boolean(
                                                    editFieldErrors.last_name,
                                                ),
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <input
                                        v-model="editForm.document_id"
                                        type="text"
                                        placeholder="Cédula"
                                        :class="`${inputClass(Boolean(editFieldErrors.document_id))} sm:col-span-2`"
                                    />
                                    <input
                                        v-model="editForm.jersey_number"
                                        type="number"
                                        min="0"
                                        max="99"
                                        placeholder="Chaqueta"
                                        :class="
                                            inputClass(
                                                Boolean(
                                                    editFieldErrors.jersey_number,
                                                ),
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="editForm.phone"
                                        type="text"
                                        placeholder="Teléfono"
                                        :class="
                                            inputClass(
                                                Boolean(editFieldErrors.phone),
                                            )
                                        "
                                    />
                                    <input
                                        v-model="editForm.email"
                                        type="email"
                                        placeholder="Correo"
                                        :class="
                                            inputClass(
                                                Boolean(editFieldErrors.email),
                                            )
                                        "
                                    />
                                </div>
                                <div
                                    class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]"
                                >
                                    <input
                                        v-model="editForm.address"
                                        type="text"
                                        placeholder="Dirección"
                                        :class="
                                            inputClass(
                                                Boolean(
                                                    editFieldErrors.address,
                                                ),
                                            )
                                        "
                                    />
                                    <select
                                        v-model="editForm.account_role"
                                        :class="
                                            inputClass(
                                                Boolean(
                                                    editFieldErrors.account_role,
                                                ),
                                            )
                                        "
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
                                <Button
                                    type="button"
                                    variant="secondary"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] hover:bg-[#22304f]"
                                    :disabled="editSubmitting"
                                    @click="submitEdit"
                                >
                                    {{
                                        editSubmitting
                                            ? 'Guardando...'
                                            : 'Guardar cambios'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </article>

                    <article
                        v-else-if="activeTab === 'active'"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#E5B849]">
                                Miembros activos
                            </p>
                            <span class="text-[12px] text-[#94A3B8]">{{
                                props.rosterManagement.active_players.length
                            }}</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="player in props.rosterManagement
                                    .active_players"
                                :key="player.id"
                                class="rounded-[14px] border border-white/6 bg-[#131B2F] p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="text-sm font-semibold text-[#F8FAFC]"
                                            >
                                                {{ player.name }}
                                            </p>
                                            <span
                                                class="rounded-full border border-white/6 bg-[#0E1628] px-2 py-0.5 text-[11px] text-[#94A3B8]"
                                                >#{{
                                                    player.jersey_number ??
                                                    'S/N'
                                                }}</span
                                            >
                                            <span
                                                class="rounded-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-2 py-0.5 text-[11px] text-[#F8FAFC]"
                                            >
                                                {{
                                                    player.account_role ===
                                                    'league_admin'
                                                        ? 'Admin'
                                                        : 'Miembro'
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 text-[12px] text-[#94A3B8]"
                                        >
                                            {{
                                                player.document_id ??
                                                'Sin cédula'
                                            }}<span v-if="player.email">
                                                · {{ player.email }}</span
                                            >
                                        </p>
                                        <p
                                            v-if="player.invitation_pending"
                                            class="mt-1 text-[11px] text-[#E5B849]"
                                        >
                                            Invitación pendiente.
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
                                            @click="
                                                toggleStatus(player.id, false)
                                            "
                                        >
                                            Dar de baja
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    props.rosterManagement.active_players
                                        .length === 0
                                "
                                class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                            >
                                No hay miembros activos registrados todavía.
                            </div>
                        </div>
                    </article>

                    <article
                        v-else
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#E5B849]">
                                Miembros inactivos
                            </p>
                            <span class="text-[12px] text-[#94A3B8]">{{
                                props.rosterManagement.inactive_players.length
                            }}</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="player in props.rosterManagement
                                    .inactive_players"
                                :key="player.id"
                                class="rounded-[14px] border border-white/6 bg-[#131B2F] p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div>
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="text-sm font-semibold text-[#F8FAFC]"
                                            >
                                                {{ player.name }}
                                            </p>
                                            <span
                                                class="rounded-full border border-white/6 bg-[#0E1628] px-2 py-0.5 text-[11px] text-[#94A3B8]"
                                                >#{{
                                                    player.jersey_number ??
                                                    'S/N'
                                                }}</span
                                            >
                                            <span
                                                class="rounded-full border border-white/6 bg-[#0E1628] px-2 py-0.5 text-[11px] text-[#94A3B8]"
                                            >
                                                {{
                                                    player.account_role ===
                                                    'league_admin'
                                                        ? 'Admin'
                                                        : 'Miembro'
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 text-[12px] text-[#94A3B8]"
                                        >
                                            {{
                                                player.document_id ??
                                                'Sin cédula'
                                            }}<span v-if="player.email">
                                                · {{ player.email }}</span
                                            >
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
                                            class="min-h-10 rounded-[10px] border border-[rgba(74,222,128,0.28)] bg-[rgba(74,222,128,0.12)] px-3 text-xs font-semibold text-[#4ADE80]"
                                            @click="
                                                toggleStatus(player.id, true)
                                            "
                                        >
                                            Reactivar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    props.rosterManagement.inactive_players
                                        .length === 0
                                "
                                class="rounded-[14px] border border-dashed border-white/8 bg-[#131B2F] p-4 text-sm text-[#94A3B8]"
                            >
                                No hay miembros dados de baja.
                            </div>
                        </div>
                    </article>
                </div>

                <DialogFooter class="border-t border-white/6 px-5 pt-4 pb-5">
                    <p class="text-[12px] text-[#94A3B8]">
                        Los referidos acumulan
                        {{
                            formatMoney(
                                props.rosterManagement
                                    .referral_credit_amount_cents,
                            )
                        }}
                        de crédito por miembro.
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
