<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Clock3, ShieldCheck, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { formatMoney } from '@/lib/league';
import type { BreadcrumbItem } from '@/types';

type ModulePayload = {
    mode: 'operational' | 'guest' | 'no_league';
    league: { id: number; name: string; emoji: string | null; slug: string } | null;
    role: { value: string; label: string; can_manage: boolean } | null;
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

const props = defineProps<{
    module: ModulePayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel de liga',
        href: '/liga/panel',
    },
];

const roleSummary = computed(() =>
    props.module.role?.can_manage
        ? 'Tienes acceso completo para organizar la jornada, registrar pagos y mantener el roster.'
        : 'Tu vista prioriza la operacion de la jornada y la informacion de tu liga sin los controles administrativos.',
);

const stats = computed(() => {
    const summary = props.module.summary;

    if (!summary) {
        return [];
    }

    return [
        {
            title: 'Corte activo',
            value: summary.cut_label,
            description: summary.is_past_due
                ? 'El corte ya vencio y la prioridad de llegada depende del pago.'
                : 'Todavia estas dentro del plazo normal del corte actual.',
            icon: Clock3,
        },
        {
            title: 'Miembros en roster',
            value: `${summary.players_count}`,
            description: `${summary.paid_players_count} al dia y ${summary.pending_players_count} pendientes.`,
            icon: Users,
        },
        {
            title: 'Llegadas de hoy',
            value: `${summary.today_arrivals_count}`,
            description: `${summary.today_guests_count} invitados cargados para la jornada.`,
            icon: ShieldCheck,
        },
    ];
});
</script>

<template>
    <Head title="Panel de liga" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.module.league?.name ?? 'Liga activa'"
        :league-emoji="props.module.league?.emoji ?? null"
        :role-label="props.module.role?.label ?? 'Sin rol'"
        active-module="panel"
        :can-manage-league="props.module.role?.can_manage ?? false"
    >
        <section class="app-surface space-y-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-3xl space-y-3">
                    <p class="app-kicker text-[#E5B849]">Panel operativo</p>
                    <h1
                        class="app-display app-module-title text-[#F8FAFC]"
                    >
                        {{ props.module.league?.name }}
                    </h1>
                    <p class="text-[15px] leading-7 text-[#94A3B8]">
                        {{ roleSummary }}
                    </p>
                </div>

                <div
                    :class="
                        props.module.role?.can_manage
                            ? 'app-badge-positive'
                            : 'rounded-full border border-white/6 bg-[#0E1628] px-3 py-2 text-[12px] font-semibold text-[#94A3B8]'
                    "
                >
                    {{ props.module.role?.can_manage ? 'Administracion habilitada' : 'Vista de miembro' }}
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="stat in stats"
                        :key="stat.title"
                        class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="app-kicker">{{ stat.title }}</p>
                            <component
                                :is="stat.icon"
                                class="size-4 text-[#E5B849]"
                            />
                        </div>
                        <p class="mt-4 text-[22px] font-semibold text-[#F8FAFC]">
                            {{ stat.value }}
                        </p>
                        <p class="mt-3 text-[13px] leading-6 text-[#94A3B8]">
                            {{ stat.description }}
                        </p>
                    </article>
                </div>

                <article class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4">
                    <p class="app-kicker text-[#E5B849]">Accesos rapidos</p>
                    <div class="mt-4 grid gap-3">
                        <Link
                            href="/liga/llegada"
                            class="flex min-h-12 items-center justify-between rounded-[14px] border border-white/6 bg-[#131B2F] px-4 text-sm font-semibold text-[#F8FAFC]"
                        >
                            <span>Entrar a Llegada</span>
                            <ArrowRight class="size-4 text-[#E5B849]" />
                        </Link>
                        <Link
                            v-if="props.module.role?.can_manage"
                            href="/liga/gestion"
                            class="flex min-h-12 items-center justify-between rounded-[14px] border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] px-4 text-sm font-semibold text-[#F8FAFC]"
                        >
                            <span>Abrir Gestion</span>
                            <Wallet class="size-4 text-[#E5B849]" />
                        </Link>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="app-surface space-y-3">
                <p class="app-kicker text-[#E5B849]">Prioridad y cola</p>
                <p class="text-[14px] leading-7 text-[#94A3B8]">
                    Antes del vencimiento todos los miembros mantienen prioridad
                    en la llegada. Al vencer el corte, solo conservan prioridad
                    quienes ya pagaron; los demas pasan detras de los que estan
                    al dia y quedan alineados con la cola que luego consumira el
                    modulo Juego.
                </p>
            </article>

            <article class="app-surface space-y-3">
                <p class="app-kicker text-[#E5B849]">Transparencia del corte</p>
                <p class="text-[14px] leading-7 text-[#94A3B8]">
                    Gestion ya mantiene cuotas, invitados, gastos, directiva y
                    referidos por corte. Los cambios de cuota o de reglas de
                    jornada quedan versionados para no romper trazabilidad.
                </p>
                <p class="text-[12px] text-[#64748B]">
                    Credito por referido actual: {{ formatMoney(20000) }}.
                </p>
            </article>
        </section>
    </LeagueShellLayout>
</template>
