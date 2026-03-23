<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Shield,
    Trophy,
    UserPlus,
    Users,
    Volleyball,
} from 'lucide-vue-next';
import CommandCenterLayout from '@/layouts/CommandCenterLayout.vue';

type Metrics = {
    total_users: number;
    active_leagues: number;
    inactive_leagues: number;
    league_admins: number;
    members: number;
    guests: number;
    pending_invitations: number;
};

type MetricKey = keyof Metrics;

const props = defineProps<{
    metrics: Metrics;
}>();

const statCards = [
    {
        key: 'total_users' as MetricKey,
        title: 'Usuarios totales',
        icon: Users,
    },
    {
        key: 'active_leagues' as MetricKey,
        title: 'Ligas activas',
        icon: Volleyball,
    },
    {
        key: 'league_admins' as MetricKey,
        title: 'Admins de ligas',
        icon: Shield,
    },
    {
        key: 'members' as MetricKey,
        title: 'Miembros',
        icon: BadgeCheck,
    },
    {
        key: 'guests' as MetricKey,
        title: 'Invitados',
        icon: Trophy,
    },
    {
        key: 'inactive_leagues' as MetricKey,
        title: 'Ligas inactivas',
        icon: Volleyball,
    },
];
</script>

<template>
    <Head title="Centro de mando" />

    <CommandCenterLayout>
        <div class="app-page-stack">
            <section class="app-surface relative overflow-hidden">
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(229,184,73,0.12),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(74,222,128,0.09),transparent_32%)]"
                />

                <div class="relative space-y-4">
                    <p class="app-kicker text-[#E5B849]">
                        Administracion general
                    </p>
                    <div class="space-y-3">
                        <h1
                            class="app-display text-[46px] leading-[0.9] text-[#F8FAFC] md:text-[64px]"
                        >
                            Centro de mando
                        </h1>
                        <p class="max-w-2xl text-[14px] leading-7 text-[#94A3B8]">
                            Supervisa cuentas, ligas activas y estado general
                            del ecosistema desde un solo punto. Este entorno es
                            exclusivo para administradores generales.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.key"
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
                        {{ props.metrics[card.key] }}
                    </p>
                </article>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-12 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                        >
                            <UserPlus class="size-5 text-[#E5B849]" />
                        </div>
                        <div>
                            <p class="app-kicker">Invitaciones pendientes</p>
                            <p class="app-body-copy">
                                Usuarios creados por un admin general que aun no
                                completan el onboarding.
                            </p>
                        </div>
                    </div>
                    <p class="text-[34px] font-semibold text-[#F8FAFC]">
                        {{ props.metrics.pending_invitations }}
                    </p>
                </article>

                <article class="app-surface space-y-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-12 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                        >
                            <Volleyball class="size-5 text-[#E5B849]" />
                        </div>
                        <div>
                            <p class="app-kicker">Estado de ligas</p>
                            <p class="app-body-copy">
                                Puedes revocar o restaurar el acceso de una liga
                                desde el modulo de ligas.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="app-badge-positive">
                            {{ props.metrics.active_leagues }} activas
                        </span>
                        <span class="app-badge-negative">
                            {{ props.metrics.inactive_leagues }} inactivas
                        </span>
                    </div>
                </article>
            </section>
        </div>
    </CommandCenterLayout>
</template>
