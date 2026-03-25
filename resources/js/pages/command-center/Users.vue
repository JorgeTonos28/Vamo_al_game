<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CommandCenterLayout from '@/layouts/CommandCenterLayout.vue';

const props = defineProps<{
    roleOptions: Array<{
        value: string;
        label: string;
    }>;
    leagueRoleOptions: Array<{
        value: string;
        label: string;
    }>;
    leagueOptions: Array<{
        id: number;
        name: string;
        slug: string;
    }>;
    users: Array<{
        id: number;
        name: string;
        email: string;
        account_role: string;
        account_role_label: string;
        league_memberships_count: number;
        active_league_id: number | null;
        memberships: Array<{
            id: number;
            league_id: number;
            league_name: string;
            league_slug: string;
            role: string;
            role_label: string;
            is_active: boolean;
        }>;
        can_assign_leagues: boolean;
        has_completed_onboarding: boolean;
        invited_at: string | null;
        created_at: string | null;
    }>;
}>();

const page = usePage();
const status = computed(
    () =>
        (page.props.flash as { status?: string } | undefined)?.status,
);

const form = useForm({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    account_role: '',
    league_id: '',
});
const assignmentForms = reactive<Record<number, { league_id: string; role: string }>>({});

const requiresLeagueAssignment = computed(() =>
    ['league_admin', 'member'].includes(form.account_role),
);

watch(
    () => form.account_role,
    (role) => {
        if (! ['league_admin', 'member'].includes(role)) {
            form.league_id = '';
        }
    },
);

const submit = () => {
    form.post('/command-center/users', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
};

function assignmentFormFor(userId: number) {
    if (!assignmentForms[userId]) {
        const user = props.users.find((entry) => entry.id === userId);
        const assignedLeagueIds = new Set(user?.memberships.map((membership) => membership.league_id) ?? []);
        const suggestedLeague = props.leagueOptions.find((league) => !assignedLeagueIds.has(league.id)) ?? props.leagueOptions[0];

        assignmentForms[userId] = {
            league_id: suggestedLeague ? String(suggestedLeague.id) : '',
            role: props.leagueRoleOptions[0]?.value ?? 'member',
        };
    }

    return assignmentForms[userId];
}

function assignLeague(userId: number): void {
    const assignment = assignmentFormFor(userId);

    router.post(`/command-center/users/${userId}/leagues`, {
        league_id: assignment.league_id,
        role: assignment.role,
    }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Usuarios" />

    <CommandCenterLayout>
        <div class="app-page-stack">
            <section class="app-surface space-y-5">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Invitar usuario</p>
                    <h1 class="text-[26px] font-semibold text-[#F8FAFC]">
                        Crear cuenta e iniciar onboarding
                    </h1>
                    <p class="app-body-copy">
                        Si el rol queda vacio, el sistema asigna invitado como
                        rol primario.
                    </p>
                </div>

                <p
                    v-if="status"
                    class="app-badge-positive inline-flex !bg-[rgba(74,222,128,0.12)]"
                >
                    {{ status }}
                </p>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="first_name">Nombre</Label>
                        <Input id="first_name" v-model="form.first_name" required />
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Apellido</Label>
                        <Input id="last_name" v-model="form.last_name" required />
                        <InputError :message="form.errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="document_id">Cedula</Label>
                        <Input id="document_id" v-model="form.document_id" />
                        <InputError :message="form.errors.document_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Telefono</Label>
                        <Input id="phone" v-model="form.phone" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="address">Direccion</Label>
                        <Input id="address" v-model="form.address" />
                        <InputError :message="form.errors.address" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo</Label>
                        <Input id="email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="account_role">Rol</Label>
                        <select
                            id="account_role"
                            v-model="form.account_role"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-3 text-sm text-[#F8FAFC] outline-none transition focus:border-[rgba(229,184,73,0.28)]"
                        >
                            <option value="">Invitado por defecto</option>
                            <option
                                v-for="role in roleOptions"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.account_role" />
                    </div>

                    <div v-if="requiresLeagueAssignment" class="grid gap-2 md:col-span-2">
                        <Label for="league_id">Liga inicial opcional</Label>
                        <select
                            id="league_id"
                            v-model="form.league_id"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-3 text-sm text-[#F8FAFC] outline-none transition focus:border-[rgba(229,184,73,0.28)]"
                        >
                            <option value="">Dejar sin liga por ahora</option>
                            <option
                                v-for="league in leagueOptions"
                                :key="league.id"
                                :value="league.id"
                            >
                                {{ league.name }}
                            </option>
                        </select>
                        <p class="text-[13px] text-[#94A3B8]">
                            Si seleccionas una liga activa, se crea la membresia inicial. Si no, podras asignarla mas tarde.
                        </p>
                        <InputError :message="form.errors.league_id" />
                    </div>

                    <div class="md:col-span-2">
                        <Button :disabled="form.processing" class="w-full md:w-auto">
                            Enviar invitacion
                        </Button>
                    </div>
                </form>
            </section>

            <section class="app-surface space-y-4">
                <div class="space-y-2">
                    <p class="app-kicker">Directorio de usuarios</p>
                    <p class="app-body-copy">
                        Estado de onboarding, rol primario y cantidad de ligas
                        asociadas.
                    </p>
                </div>

                <div class="app-divider-list">
                    <article
                        v-for="user in users"
                        :key="user.id"
                        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="space-y-1">
                            <p class="text-[15px] font-semibold text-[#F8FAFC]">
                                {{ user.name }}
                            </p>
                            <p class="text-[13px] text-[#94A3B8]">
                                {{ user.email }}
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-2 text-[12px]"
                        >
                            <span class="app-badge-positive">
                                {{ user.account_role_label }}
                            </span>
                            <span
                                class="rounded-full border border-white/8 px-3 py-1.5 text-[#94A3B8]"
                            >
                                {{ user.league_memberships_count }} ligas
                            </span>
                            <span
                                :class="
                                    user.has_completed_onboarding
                                        ? 'app-badge-positive'
                                        : 'app-badge-negative'
                                "
                            >
                                {{
                                    user.has_completed_onboarding
                                        ? 'Activo'
                                        : 'Pendiente'
                                }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-3 rounded-[14px] border border-white/6 bg-[#0E1628] p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-for="membership in user.memberships"
                                    :key="membership.id"
                                    class="rounded-full border px-3 py-1.5 text-[12px]"
                                    :class="
                                        membership.is_active
                                            ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                                            : 'border-[rgba(248,113,113,0.28)] bg-[rgba(248,113,113,0.12)] text-[#FCA5A5]'
                                    "
                                >
                                    {{ membership.league_name }} · {{ membership.role_label }}{{
                                        user.active_league_id === membership.league_id ? ' · Activa' : ''
                                    }}
                                </span>
                                <span
                                    v-if="user.memberships.length === 0"
                                    class="text-[12px] text-[#94A3B8]"
                                >
                                    Sin ligas asignadas.
                                </span>
                            </div>

                            <div
                                v-if="user.can_assign_leagues"
                                class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto]"
                            >
                                <select
                                    v-model="assignmentFormFor(user.id).league_id"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-3 text-sm text-[#F8FAFC] outline-none transition focus:border-[rgba(229,184,73,0.28)]"
                                >
                                    <option value="">Selecciona una liga activa</option>
                                    <option
                                        v-for="league in leagueOptions"
                                        :key="league.id"
                                        :value="league.id"
                                    >
                                        {{ league.name }}
                                    </option>
                                </select>
                                <select
                                    v-model="assignmentFormFor(user.id).role"
                                    class="min-h-12 rounded-[12px] border border-white/8 bg-[#131B2F] px-3 text-sm text-[#F8FAFC] outline-none transition focus:border-[rgba(229,184,73,0.28)]"
                                >
                                    <option
                                        v-for="role in leagueRoleOptions"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </option>
                                </select>
                                <Button
                                    class="min-h-12"
                                    :disabled="!assignmentFormFor(user.id).league_id"
                                    @click="assignLeague(user.id)"
                                >
                                    Guardar liga
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </CommandCenterLayout>
</template>
