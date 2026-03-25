<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { leagueNavItems } from '@/lib/league';
import { cn } from '@/lib/utils';

const props = defineProps<{
    leagueName: string;
    leagueEmoji?: string | null;
    roleLabel: string;
    activeModule: string;
    canManageLeague: boolean;
}>();

const items = computed(() => leagueNavItems(props.canManageLeague));
</script>

<template>
    <aside class="space-y-4">
        <article
            class="rounded-[20px] border border-white/6 bg-[linear-gradient(180deg,rgba(26,36,58,0.96),rgba(14,22,40,0.94))] p-4"
        >
            <p class="app-kicker text-[#E5B849]">Liga activa</p>
            <div class="mt-3 flex items-center gap-3">
                <span
                    v-if="props.leagueEmoji"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-[14px] border border-white/8 bg-[#0E1628] text-[24px] leading-none"
                >
                    {{ props.leagueEmoji }}
                </span>
                <h2
                    class="app-display pt-1 text-[28px] leading-[0.94] text-[#F8FAFC]"
                >
                    {{ props.leagueName }}
                </h2>
            </div>
            <p class="mt-3 text-[13px] leading-6 text-[#94A3B8]">
                {{ props.roleLabel }}
            </p>
        </article>

        <nav
            class="flex gap-3 overflow-x-auto rounded-[20px] border border-white/6 bg-[#131B2F] p-3 xl:flex-col xl:overflow-visible"
        >
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                :class="
                    cn(
                        'flex min-h-12 min-w-[160px] items-center gap-3 rounded-[14px] border px-4 text-sm font-semibold transition xl:min-w-0',
                        props.activeModule === item.key
                            ? 'border-[rgba(229,184,73,0.32)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                            : 'border-white/6 bg-[#0E1628] text-[#94A3B8] hover:text-[#F8FAFC]',
                    )
                "
            >
                <component :is="item.icon" class="size-4 shrink-0" />
                <span class="truncate">{{ item.label }}</span>
            </Link>
        </nav>
    </aside>
</template>
