<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CircleDollarSign,
    Clock3,
    RotateCcw,
    ShieldCheck,
    Target,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const quickCards = [
    {
        title: 'Cola activa',
        value: '06',
        description: 'Miembros e invitados ordenados por prioridad de juego.',
        icon: Clock3,
    },
    {
        title: 'Cobros del corte',
        value: 'RD$23.4K',
        description: 'Cuotas e invitados listos para recibos y cierre.',
        icon: CircleDollarSign,
    },
    {
        title: 'Racha actual',
        value: '2',
        description: 'El próximo ganador descansará si mantiene la racha.',
        icon: Trophy,
    },
];

const dayStats = [
    {
        title: 'Miembros listos',
        value: '18',
        description: 'Con preferencia activa para entrar.',
    },
    {
        title: 'Invitados hoy',
        value: '4',
        description: 'Integrados a la cola sin romper el orden.',
    },
    {
        title: 'Juegos jugados',
        value: '11',
        description: 'Historial breve guardado durante la jornada.',
    },
    {
        title: 'Anotadores activos',
        value: '9',
        description: 'Estadísticas individuales corriendo en vivo.',
    },
];

const moduleHighlights = [
    {
        title: 'Llegada y cola',
        description:
            'Controla llegada, prioridad de miembros, invitados y armado de equipos sin salir del panel.',
        icon: Users,
    },
    {
        title: 'Contador y rachas',
        description:
            'Registra puntos de 1, 2 y 3, corrige errores y administra rachas y descansos.',
        icon: Target,
    },
    {
        title: 'Tabla y temporada',
        description:
            'Resume el día y conecta lo que pase hoy con la historia completa de la liga.',
        icon: Trophy,
    },
    {
        title: 'Caja y recibos',
        description:
            'Sigue cortes, cobros del día y recibos compartibles con menos pasos en escritorio.',
        icon: CircleDollarSign,
    },
];
</script>

<template>
    <Head title="Dashboard" />

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
                                    Jornada en curso
                                </p>
                                <div class="space-y-3">
                                    <h1
                                        class="app-display text-[54px] leading-[0.9] text-[#F8FAFC] md:text-[72px]"
                                    >
                                        Liga Aurora
                                    </h1>
                                    <p
                                        class="text-[15px] leading-7 text-[#94A3B8]"
                                    >
                                        Supervisa marcador, cola activa, cobros,
                                        rachas y seguimiento del día desde un
                                        panel amplio, claro y listo para mover
                                        la jornada con ritmo en escritorio.
                                    </p>
                                </div>
                            </div>

                            <div class="app-badge-positive">
                                <ShieldCheck class="size-3.5" />
                                En juego
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
                                            Eq. A
                                        </p>
                                        <p
                                            class="app-display text-[84px] leading-none text-[#4ADE80] md:text-[108px]"
                                        >
                                            08
                                        </p>
                                    </div>
                                    <p class="app-kicker pb-4 text-[#94A3B8]">
                                        VS
                                    </p>
                                    <div class="space-y-2 text-right">
                                        <p class="app-kicker text-[#94A3B8]">
                                            Eq. B
                                        </p>
                                        <p
                                            class="app-display text-[84px] leading-none text-[#E5B849] md:text-[108px]"
                                        >
                                            07
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <Button variant="score-home" size="score">
                                        +1 Eq. A
                                    </Button>
                                    <Button variant="score-away" size="score">
                                        +1 Eq. B
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    class="rounded-[24px] border border-white/6 bg-[#0E1628] p-4"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        Modo activo
                                    </p>
                                    <p
                                        class="mt-3 text-[18px] font-semibold text-[#F8FAFC]"
                                    >
                                        Balance automático
                                    </p>
                                    <p
                                        class="mt-2 text-[13px] leading-6 text-[#94A3B8]"
                                    >
                                        El criterio de scouting define equipos
                                        más balanceados para la jornada.
                                    </p>
                                </div>

                                <div
                                    class="rounded-[24px] border border-white/6 bg-[#0E1628] p-4"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        Historial breve
                                    </p>
                                    <div class="mt-4 space-y-3">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Juego #09
                                            </span>
                                            <span
                                                class="text-[13px] font-semibold text-[#F8FAFC]"
                                            >
                                                08 - 06
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Juego #10
                                            </span>
                                            <span
                                                class="text-[13px] font-semibold text-[#F8FAFC]"
                                            >
                                                07 - 05
                                            </span>
                                        </div>
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-[13px] text-[#94A3B8]"
                                            >
                                                Deshacer
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#E5B849]"
                                            >
                                                <RotateCcw class="size-3.5" />
                                                Disponible
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
