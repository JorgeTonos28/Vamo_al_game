<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Construction, Hammer } from 'lucide-vue-next';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    module: {
        key: string;
        label: string;
        league: { id: number; name: string; emoji: string | null };
        role: { value: string; label: string; can_manage: boolean };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: props.module.label,
        href: `/liga/modulos/${props.module.key}`,
    },
];
</script>

<template>
    <Head :title="props.module.label" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.module.league.name"
        :league-emoji="props.module.league.emoji"
        :role-label="props.module.role.label"
        :active-module="props.module.key"
        :can-manage-league="props.module.role.can_manage"
    >
        <section
            class="app-surface flex min-h-[420px] flex-col items-center justify-center gap-5 text-center"
        >
            <div
                class="flex size-20 items-center justify-center rounded-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)]"
            >
                <Construction class="size-9 text-[#E5B849]" />
            </div>
            <div class="space-y-3">
                <p class="app-kicker text-[#E5B849]">{{ props.module.label }}</p>
                <h1 class="app-display app-module-title text-[#F8FAFC]">
                    Modulo aun en construccion
                </h1>
                <p class="mx-auto max-w-xl text-[15px] leading-7 text-[#94A3B8]">
                    Este espacio ya forma parte del shell de la liga, pero
                    todavia no esta listo. Vuelve mas tarde para encontrarlo
                    conectado con la misma logica operativa de legacy.
                </p>
            </div>

            <div
                class="inline-flex items-center gap-2 rounded-full border border-white/6 bg-[#0E1628] px-4 py-2 text-sm text-[#94A3B8]"
            >
                <Hammer class="size-4 text-[#E5B849]" />
                Desarrollo en progreso
            </div>
        </section>
    </LeagueShellLayout>
</template>
