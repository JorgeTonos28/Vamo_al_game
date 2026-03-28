<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Lock,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, TenancyContext, User } from '@/types';

type LeagueHomePayload = {
    mode: 'operational' | 'guest' | 'no_league';
    league: { id: number; name: string; slug: string } | null;
    role: { value: string; label: string; can_manage: boolean } | null;
    requires_league_selection: boolean;
    summary: {
        cut_label: string;
        is_past_due: boolean;
        players_count: number;
        paid_players_count: number;
        pending_players_count: number;
        today_arrivals_count: number;
        today_guests_count: number;
        session_status: string;
    } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const tenancy = computed(() => page.props.tenancy as TenancyContext | null);
const leagueHome = computed(() => page.props.leagueHome as LeagueHomePayload);

const selectorMode = computed(
    () =>
        leagueHome.value.mode === 'operational' &&
        leagueHome.value.requires_league_selection,
);

const firstName = computed(
    () => user.value.first_name ?? user.value.name.split(' ')[0] ?? 'Jugador',
);

const genericCards = computed(() => {
    if (leagueHome.value.mode === 'guest' && leagueHome.value.league) {
        return [
            {
                title: 'Liga activa',
                value: leagueHome.value.league.name,
                description:
                    'Tu acceso actual es informativo. Los módulos operativos siguen reservados para miembros y administración.',
            },
            {
                title: 'Rol visible',
                value: leagueHome.value.role?.label ?? 'Invitado',
                description:
                    'Seguimos separando claramente el rol de cuenta y el rol dentro de la liga.',
            },
            {
                title: 'Switch de ligas',
                value: tenancy.value?.can_switch ? 'Disponible' : 'No aplica',
                description:
                    'Puedes cambiar de liga desde el header o volver aquí para revisar tu contexto.',
            },
        ];
    }

    return [
        {
            title: 'Cuenta activa',
            value: user.value.name,
            description:
                'Tu perfil ya puede completar ajustes personales mientras se asigna una liga.',
        },
        {
            title: 'Ligas visibles',
            value: `${tenancy.value?.available_leagues.length ?? 0}`,
            description:
                'Cuando tu usuario forme parte de una liga, este portal te llevará directo al contexto operativo.',
        },
        {
            title: 'Estado',
            value: 'Pendiente',
            description:
                'Todavía no hay una liga activa con módulos habilitados para esta cuenta.',
        },
    ];
});

function enterLeague(leagueId: number): void {
    router.post(
        '/active-league',
        { league_id: leagueId },
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-stack">
            <section
                v-if="selectorMode"
                class="mx-auto w-full max-w-[960px] rounded-[28px] border border-white/6 bg-[linear-gradient(180deg,rgba(26,36,58,0.96),rgba(14,22,40,0.94))] p-6 md:p-8"
            >
                <div
                    class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start"
                >
                    <div class="space-y-5">
                        <p class="app-kicker text-[#E5B849]">
                            Acceso a liga
                        </p>
                        <div class="space-y-3">
                            <h1
                                class="app-display text-[46px] leading-[0.9] text-[#F8FAFC] md:text-[68px]"
                            >
                                Hola, {{ firstName }}.
                            </h1>
                            <p class="text-[16px] leading-8 text-[#94A3B8]">
                                ¿A qué liga quieres acceder? Selecciona una y
                                entra directo al panel operativo con todos sus
                                módulos disponibles.
                            </p>
                        </div>

                        <div class="grid gap-3">
                            <button
                                v-for="league in tenancy?.available_leagues ?? []"
                                :key="league.id"
                                type="button"
                                class="group flex min-h-[92px] cursor-pointer items-center justify-between gap-4 rounded-[18px] border border-white/6 bg-[#131B2F] px-5 text-left transition hover:border-[rgba(229,184,73,0.24)] hover:bg-[#162038]"
                                @click="enterLeague(league.id)"
                            >
                                <div class="space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="text-[18px] font-semibold text-[#F8FAFC]"
                                        >
                                            {{ league.name }}
                                        </p>
                                        <span
                                            class="rounded-full border border-white/6 bg-[#0E1628] px-2.5 py-1 text-[11px] font-semibold text-[#94A3B8]"
                                        >
                                            {{ league.role_label }}
                                        </span>
                                    </div>
                                    <p class="text-[13px] text-[#94A3B8]">
                                        {{
                                            league.is_active
                                                ? 'Entrar con este contexto operativo.'
                                                : 'Esta liga tiene el acceso revocado en este momento.'
                                        }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex size-11 items-center justify-center rounded-full border border-[rgba(229,184,73,0.24)] bg-[rgba(229,184,73,0.1)] text-[#E5B849] transition group-hover:translate-x-1"
                                >
                                    <ArrowRight class="size-5" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <article class="rounded-[22px] border border-white/6 bg-[#0E1628] p-5">
                        <p class="app-kicker text-[#E5B849]">Lo que sigue</p>
                        <div class="mt-4 space-y-4">
                            <div class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4">
                                <div class="flex items-center gap-3">
                                    <ShieldCheck class="size-5 text-[#4ADE80]" />
                                    <p class="text-sm font-semibold text-[#F8FAFC]">
                                        Contexto aislado por liga
                                    </p>
                                </div>
                                <p class="mt-3 text-[13px] leading-6 text-[#94A3B8]">
                                    Toda la información visible cambia con la
                                    liga seleccionada y no se mezcla con otras.
                                </p>
                            </div>

                            <div class="rounded-[16px] border border-white/6 bg-[#131B2F] p-4">
                                <div class="flex items-center gap-3">
                                    <Users class="size-5 text-[#E5B849]" />
                                    <p class="text-sm font-semibold text-[#F8FAFC]">
                                        Llegada y Gestión listas
                                    </p>
                                </div>
                                <p class="mt-3 text-[13px] leading-6 text-[#94A3B8]">
                                    Esta entrega ya aterriza los dos primeros
                                    módulos de la liga con su base de negocio.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section
                v-else
                class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_320px]"
            >
                <article class="app-surface space-y-5">
                    <p class="app-kicker text-[#E5B849]">
                        {{
                            leagueHome.mode === 'guest'
                                ? 'Vista informativa'
                                : 'Sin liga operativa'
                        }}
                    </p>
                    <div class="space-y-3">
                        <h1
                            class="app-display text-[48px] leading-[0.92] text-[#F8FAFC] md:text-[72px]"
                        >
                            {{
                                leagueHome.mode === 'guest'
                                    ? leagueHome.league?.name
                                    : 'Panel base'
                            }}
                        </h1>
                        <p class="max-w-2xl text-[15px] leading-7 text-[#94A3B8]">
                            {{
                                leagueHome.mode === 'guest'
                                    ? 'Tu usuario pertenece a la liga seleccionada como invitado. Por ahora solo ve información general y datos personales del contexto activo.'
                                    : 'Tu cuenta todavía no tiene una liga operativa asignada. Mientras eso sucede, puedes mantener al día tus ajustes personales.'
                            }}
                        </p>
                    </div>
                </article>

                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <Lock class="size-5 text-[#E5B849]" />
                        <p class="app-kicker text-[#E5B849]">Estado actual</p>
                    </div>
                    <p class="text-[14px] leading-7 text-[#94A3B8]">
                        {{
                            leagueHome.mode === 'guest'
                                ? 'El acceso operativo sigue reservado para miembros y administradores de la liga.'
                                : 'Cuando tengas una liga activa, este portal dejará de ser base y te llevará directo al shell de liga.'
                        }}
                    </p>
                </article>
            </section>

            <section
                v-if="!selectorMode"
                class="grid gap-4 md:grid-cols-3"
            >
                <article
                    v-for="card in genericCards"
                    :key="card.title"
                    class="app-surface space-y-3"
                >
                    <p class="app-kicker">{{ card.title }}</p>
                    <p class="text-[24px] font-semibold text-[#F8FAFC]">
                        {{ card.value }}
                    </p>
                    <p class="text-[13px] leading-6 text-[#94A3B8]">
                        {{ card.description }}
                    </p>
                </article>
            </section>

            <section
                v-if="!selectorMode"
                class="app-surface flex items-start gap-4"
            >
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                >
                    <Sparkles class="size-5 text-[#E5B849]" />
                </div>
                <div class="space-y-2">
                    <p class="app-kicker">Siguiente paso</p>
                    <p class="text-[14px] leading-7 text-[#94A3B8]">
                        {{
                            leagueHome.mode === 'guest'
                                ? 'Cuando tu rol cambie a miembro o administrador, podrás entrar a la liga con el sidebar completo y sus módulos operativos.'
                                : 'La liga activa controlará toda la experiencia web y mobile una vez el usuario reciba membresía.'
                        }}
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
