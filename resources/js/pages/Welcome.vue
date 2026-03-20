<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Trophy, Users, Wallet } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const highlights = [
    {
        title: 'Miembros',
        description: 'Jugadores, staff y roles bien organizados.',
        icon: Users,
    },
    {
        title: 'Jornadas',
        description: 'Calendario, marcador y operaciones tactiles rapidas.',
        icon: Trophy,
    },
    {
        title: 'Cobros',
        description: 'Entradas, deudas y balance de la liga en un mismo flujo.',
        icon: Wallet,
    },
];
</script>

<template>
    <Head title="Vamo al Game" />

    <div class="min-h-svh bg-background text-foreground">
        <div class="app-shell-frame flex flex-col gap-6">
            <header class="flex items-center justify-between gap-3">
                <AppLogo />

                <div class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="app-kicker text-[#E5B849]"
                    >
                        Panel
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="app-kicker text-[#94A3B8]">
                            Entrar
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="app-kicker text-[#E5B849]"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </div>
            </header>

            <section class="app-surface space-y-6">
                <div class="space-y-4">
                    <p class="app-kicker text-[#E5B849]">
                        Gestion de ligas deportivas
                    </p>
                    <div class="space-y-3">
                        <h1
                            class="app-display text-[64px] leading-[0.88] text-[#F8FAFC]"
                        >
                            Organiza la jornada sin perder el ritmo
                        </h1>
                        <p class="text-[16px] leading-7 text-[#c9d5e3]">
                            Vamo al Game centraliza equipos, partidos, cobros y
                            resultados en una interfaz pensada primero para
                            movil, con una operacion rapida y consistente.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3">
                    <div
                        class="rounded-xl border border-[rgba(255,255,255,0.06)] bg-[#0E1628] p-4"
                    >
                        <div
                            class="grid grid-cols-[1fr_auto_1fr] items-end gap-3"
                        >
                            <div class="space-y-2">
                                <p class="app-kicker text-[#94A3B8]">Eq. A</p>
                                <p
                                    class="app-display text-[72px] leading-none text-[#4ADE80]"
                                >
                                    08
                                </p>
                            </div>
                            <p class="app-kicker pb-3 text-[#94A3B8]">VS</p>
                            <div class="space-y-2 text-right">
                                <p class="app-kicker text-[#94A3B8]">Eq. B</p>
                                <p
                                    class="app-display text-[72px] leading-none text-[#E5B849]"
                                >
                                    07
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div
                            v-for="item in highlights"
                            :key="item.title"
                            class="flex items-start gap-4 rounded-xl border border-[rgba(255,255,255,0.06)] bg-[#0E1628] p-4"
                        >
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#1E293B]"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-5 text-[#E5B849]"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="app-kicker">{{ item.title }}</p>
                                <p class="text-[13px] leading-6 text-[#94A3B8]">
                                    {{ item.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3">
                    <Button v-if="$page.props.auth.user" as-child>
                        <Link :href="dashboard()">Entrar al panel</Link>
                    </Button>

                    <template v-else>
                        <Button as-child>
                            <Link :href="login()">Iniciar sesion</Link>
                        </Button>
                        <Button v-if="canRegister" variant="secondary" as-child>
                            <Link :href="register()">Crear cuenta</Link>
                        </Button>
                    </template>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <div class="app-surface space-y-2">
                    <div class="app-badge-positive">Entradas</div>
                    <p class="text-[28px] leading-none font-semibold text-[#F8FAFC]">
                        RD$ 23,400
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Balance claro para la jornada.
                    </p>
                </div>
                <div class="app-surface space-y-2">
                    <div class="app-badge-negative">
                        <CalendarDays class="size-3.5" />
                        Gastos
                    </div>
                    <p class="text-[28px] leading-none font-semibold text-[#F8FAFC]">
                        RD$ 16,600
                    </p>
                    <p class="text-[13px] text-[#94A3B8]">
                        Resumen legible y sin ruido visual.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
