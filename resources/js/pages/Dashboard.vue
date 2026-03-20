<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    CircleDollarSign,
    ShieldCheck,
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

const modules = [
    {
        title: 'Ligas y temporadas',
        description: 'Organiza competiciones, jornadas y estados de calendario.',
        icon: Trophy,
    },
    {
        title: 'Miembros y equipos',
        description: 'Controla jugadores, staff y participacion de cada plantilla.',
        icon: Users,
    },
    {
        title: 'Cobros y balance',
        description: 'Registra entradas, gastos y deudas sin salir del panel.',
        icon: CircleDollarSign,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="app-page-stack">
            <section class="app-surface space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-3">
                        <p class="app-kicker text-[#E5B849]">Jornada en curso</p>
                        <div class="space-y-2">
                            <h1
                                class="app-display text-[52px] leading-[0.9] text-[#F8FAFC]"
                            >
                                Liga Aurora
                            </h1>
                            <p class="text-[14px] leading-6 text-[#94A3B8]">
                                El sistema ya quedo alineado a una linea grafica
                                deportiva, oscura y mobile-first. Desde aqui
                                puedes seguir construyendo modulos sin salirte
                                del criterio visual.
                            </p>
                        </div>
                    </div>

                    <div class="app-badge-positive">
                        <ShieldCheck class="size-3.5" />
                        En juego
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[rgba(255,255,255,0.06)] bg-[#0E1628] p-4"
                >
                    <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-3">
                        <div class="space-y-2">
                            <p class="app-kicker text-[#94A3B8]">Eq. A</p>
                            <p
                                class="app-display text-[82px] leading-none text-[#4ADE80]"
                            >
                                08
                            </p>
                        </div>
                        <p class="app-kicker pb-3 text-[#94A3B8]">VS</p>
                        <div class="space-y-2 text-right">
                            <p class="app-kicker text-[#94A3B8]">Eq. B</p>
                            <p
                                class="app-display text-[82px] leading-none text-[#E5B849]"
                            >
                                07
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <Button variant="score-home" size="score">+1 Eq. A</Button>
                    <Button variant="score-away" size="score">+1 Eq. B</Button>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <div class="app-surface space-y-2">
                    <p class="app-kicker">Equipos activos</p>
                    <p class="text-[32px] leading-none font-semibold text-[#F8FAFC]">
                        12
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Listos para calendario y control de resultados.
                    </p>
                </div>

                <div class="app-surface space-y-2">
                    <p class="app-kicker">Partidos programados</p>
                    <p class="text-[32px] leading-none font-semibold text-[#F8FAFC]">
                        36
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Operacion priorizada para jornada y mesa tecnica.
                    </p>
                </div>

                <div class="app-surface space-y-3">
                    <div class="app-badge-positive">
                        <ArrowUpRight class="size-3.5" />
                        Entradas
                    </div>
                    <p class="text-[28px] leading-none font-semibold text-[#F8FAFC]">
                        RD$ 23,400
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Recaudacion actual de la liga.
                    </p>
                </div>

                <div class="app-surface space-y-3">
                    <div class="app-badge-negative">
                        <ArrowDownRight class="size-3.5" />
                        Gastos
                    </div>
                    <p class="text-[28px] leading-none font-semibold text-[#F8FAFC]">
                        RD$ 16,600
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Visual financiero con badging semantico.
                    </p>
                </div>
            </section>

            <section class="app-surface space-y-4">
                <div class="space-y-2">
                    <p class="app-kicker">Base de modulos</p>
                    <p class="text-[16px] font-medium text-[#F8FAFC]">
                        Lo que se agregue desde aqui ya debe reutilizar este
                        lenguaje de cards, tabs, feedback tactil y badges.
                    </p>
                </div>

                <div class="app-divider-list">
                    <div
                        v-for="item in modules"
                        :key="item.title"
                        class="flex items-start gap-4"
                    >
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#1E293B]"
                        >
                            <component
                                :is="item.icon"
                                class="size-5 text-[#E5B849]"
                            />
                        </div>

                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="app-kicker">{{ item.title }}</p>
                            <p class="text-[13px] leading-6 text-[#94A3B8]">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
