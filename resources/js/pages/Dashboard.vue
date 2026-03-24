<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    CircleDollarSign,
    Clock3,
    ShieldCheck,
    Target,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, TenancyContext, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const tenancy = computed(() => page.props.tenancy as TenancyContext | null);
const activeLeague = computed(() => tenancy.value?.active_league ?? null);

const heroTitle = computed(() => {
    if (activeLeague.value) {
        return activeLeague.value.name;
    }

    if (tenancy.value?.guest_mode) {
        return 'Modo invitado';
    }

    return 'Panel base';
});

const heroDescription = computed(() => {
    if (activeLeague.value) {
        return `Estas operando en nombre de ${activeLeague.value.name}. Toda la informacion del shell actual ya responde a la liga seleccionada y servira de base para los modulos deportivos.`;
    }

    if (tenancy.value?.guest_mode) {
        return 'Tu cuenta no forma parte de una liga activa. Por ahora veras tu panel base y ajustes personales mientras se completa tu acceso a una liga.';
    }

    return 'Tu cuenta mantiene acceso al panel base y a ajustes mientras se definen los modulos de negocio siguientes.';
});

const quickCards = computed(() => [
    {
        title: 'Rol visible',
        value:
            activeLeague.value?.role_label ??
            user.value.account_role_label ??
            'Invitado',
        description: 'Contexto actual con el que entras al sistema.',
        icon: ShieldCheck,
    },
    {
        title: 'Ligas activas',
        value: `${tenancy.value?.available_leagues.length ?? 0}`,
        description: 'Cantidad de ligas a las que puedes cambiar desde el header.',
        icon: Users,
    },
    {
        title: 'Estado',
        value: activeLeague.value ? 'Operativo' : 'Base',
        description: 'Disponibilidad actual del entorno regular.',
        icon: Trophy,
    },
]);

const dayStats = computed(() => [
    {
        title: 'Usuario',
        value: user.value.name,
        description: 'Cuenta autenticada en esta sesion.',
    },
    {
        title: 'Liga activa',
        value: activeLeague.value?.name ?? 'Sin liga',
        description: 'Tenant que concentra la informacion compartida.',
    },
    {
        title: 'Switch multi-tenant',
        value: tenancy.value?.can_switch ? 'Disponible' : 'No aplica',
        description: 'Se habilita cuando la cuenta tiene mas de una liga activa.',
    },
    {
        title: 'Correo verificado',
        value: user.value.email_verified_at ? 'Si' : 'Pendiente',
        description: 'La verificacion sigue siendo obligatoria para entrar.',
    },
]);

const moduleHighlights = [
    {
        title: 'Contexto por liga',
        description:
            'El shell ya se adapta al tenant activo y evita mezclar informacion entre ligas.',
        icon: Users,
    },
    {
        title: 'Ajustes disponibles',
        description:
            'Perfil, seguridad y apariencia siguen disponibles para administradores de ligas, miembros e invitados.',
        icon: Target,
    },
    {
        title: 'Roles flexibles',
        description:
            'Una misma cuenta puede tener rol primario de cuenta y roles distintos segun cada liga.',
        icon: Trophy,
    },
    {
        title: 'Base compartida',
        description:
            'La resolucion de tenant y la liga activa ya quedan expuestas desde backend para web y movil.',
        icon: CircleDollarSign,
    },
];
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-stack">
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_340px]">
                <article class="app-surface relative overflow-hidden">
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(229,184,73,0.12),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(74,222,128,0.12),transparent_32%)]"
                    />

                    <div class="relative space-y-6">
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="max-w-2xl space-y-3">
                                <p class="app-kicker text-[#E5B849]">
                                    Contexto activo
                                </p>
                                <div class="space-y-3">
                                    <h1
                                        class="app-display text-[54px] leading-[0.9] text-[#F8FAFC] md:text-[72px]"
                                    >
                                        {{ heroTitle }}
                                    </h1>
                                    <p
                                        class="text-[15px] leading-7 text-[#94A3B8]"
                                    >
                                        {{ heroDescription }}
                                    </p>
                                </div>
                            </div>

                            <div
                                :class="
                                    activeLeague
                                        ? 'app-badge-positive'
                                        : 'app-badge-negative'
                                "
                            >
                                <ShieldCheck class="size-3.5" />
                                {{
                                    activeLeague
                                        ? 'Tenant cargado'
                                        : 'Cuenta base'
                                }}
                            </div>
                        </div>

                        <div
                            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]"
                        >
                            <div
                                class="space-y-4 rounded-[24px] border border-white/6 bg-[#0E1628] p-5"
                            >
                                <div
                                    class="grid grid-cols-[1fr_auto_1fr] items-end gap-3"
                                >
                                    <div class="space-y-2">
                                        <p class="app-kicker text-[#94A3B8]">
                                            Tenant
                                        </p>
                                        <p
                                            class="app-display text-[28px] leading-none text-[#4ADE80] md:text-[34px]"
                                        >
                                            {{ activeLeague?.slug ?? 'guest' }}
                                        </p>
                                    </div>
                                    <p class="app-kicker pb-4 text-[#94A3B8]">
                                        /
                                    </p>
                                    <div class="space-y-2 text-right">
                                        <p class="app-kicker text-[#94A3B8]">
                                            Usuario
                                        </p>
                                        <p
                                            class="app-display text-[28px] leading-none text-[#E5B849] md:text-[34px]"
                                        >
                                            {{ user.first_name ?? user.name }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        disabled
                                        class="min-h-[88px] rounded-[12px] border border-[rgba(74,222,128,0.3)] bg-[rgba(74,222,128,0.12)] px-4 text-left text-[15px] font-semibold text-[#4ADE80]"
                                    >
                                        Panel base
                                    </button>
                                    <button
                                        type="button"
                                        disabled
                                        class="min-h-[88px] rounded-[12px] border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-4 text-left text-[15px] font-semibold text-[#E5B849]"
                                    >
                                        Ajustes listos
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    class="rounded-[24px] border border-white/6 bg-[#0E1628] p-4"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        Rol actual
                                    </p>
                                    <p
                                        class="mt-3 text-[18px] font-semibold text-[#F8FAFC]"
                                    >
                                        {{
                                            activeLeague?.role_label ??
                                            user.account_role_label
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-[13px] leading-6 text-[#94A3B8]"
                                    >
                                        La interfaz regular ya distingue entre
                                        rol de cuenta y rol dentro de la liga.
                                    </p>
                                </div>

                                <div
                                    class="rounded-[24px] border border-white/6 bg-[#0E1628] p-4"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        Base lista
                                    </p>
                                    <div class="mt-4 space-y-3">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Multi-tenant
                                            </span>
                                            <span
                                                class="text-[13px] font-semibold text-[#F8FAFC]"
                                            >
                                                Activo
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Roles
                                            </span>
                                            <span
                                                class="text-[13px] font-semibold text-[#F8FAFC]"
                                            >
                                                Activos
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Modulos
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#E5B849]"
                                            >
                                                <Clock3 class="size-3.5" />
                                                En construccion
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <article
                        v-for="card in quickCards"
                        :key="card.title"
                        class="app-surface space-y-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker text-[#E5B849]">
                                {{ card.title }}
                            </p>
                            <component
                                :is="card.icon"
                                class="size-5 text-[#E5B849]"
                            />
                        </div>

                        <p
                            class="app-display text-[42px] leading-none text-[#F8FAFC]"
                        >
                            {{ card.value }}
                        </p>
                        <p class="text-[13px] leading-6 text-[#94A3B8]">
                            {{ card.description }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="item in dayStats"
                    :key="item.title"
                    class="app-surface space-y-3"
                >
                    <p class="app-kicker">{{ item.title }}</p>
                    <p
                        class="text-[34px] leading-none font-semibold text-[#F8FAFC]"
                    >
                        {{ item.value }}
                    </p>
                    <p class="text-[13px] leading-6 text-[#94A3B8]">
                        {{ item.description }}
                    </p>
                </article>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="item in moduleHighlights"
                    :key="item.title"
                    class="app-surface transition-transform duration-300 hover:-translate-y-1"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                        >
                            <component
                                :is="item.icon"
                                class="size-5 text-[#E5B849]"
                            />
                        </div>

                        <div class="space-y-2">
                            <p class="app-kicker">{{ item.title }}</p>
                            <p class="text-[13px] leading-6 text-[#94A3B8]">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
