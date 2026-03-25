<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import LeagueSidebarNav from './LeagueSidebarNav.vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        leagueName: string;
        roleLabel: string;
        activeModule: string;
        canManageLeague: boolean;
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)]">
            <div class="xl:sticky xl:top-[calc(env(safe-area-inset-top)+96px)] xl:self-start">
                <LeagueSidebarNav
                    :league-name="leagueName"
                    :role-label="roleLabel"
                    :active-module="activeModule"
                    :can-manage-league="canManageLeague"
                />
            </div>

            <div class="app-page-stack min-w-0">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
