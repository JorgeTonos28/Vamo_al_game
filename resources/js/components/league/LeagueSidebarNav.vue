<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
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
const navRef = ref<HTMLElement | null>(null);

function keepActiveItemVisible(): void {
    nextTick(() => {
        const nav = navRef.value;

        if (!nav || nav.scrollWidth <= nav.clientWidth) {
            return;
        }

        const activeItem = nav.querySelector<HTMLElement>(
            '[data-nav-active="true"]',
        );
        activeItem?.scrollIntoView({
            block: 'nearest',
            inline: 'center',
        });
    });
}

onMounted(() => {
    keepActiveItemVisible();
});

watch(
    () => props.activeModule,
    () => {
        keepActiveItemVisible();
    },
);
</script>

<template>
    <aside class="min-w-0 space-y-4">
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
                    class="app-display min-w-0 pt-1 text-[clamp(1.75rem,6vw,2.25rem)] leading-[0.94] break-words text-[#F8FAFC]"
                >
                    {{ props.leagueName }}
                </h2>
            </div>
            <p class="mt-3 text-[13px] leading-6 text-[#94A3B8]">
                {{ props.roleLabel }}
            </p>
        </article>

        <nav
            ref="navRef"
            class="scrollbar-none flex snap-x snap-mandatory gap-3 overflow-x-auto rounded-[20px] border border-white/6 bg-[#131B2F] p-3 xl:snap-none xl:flex-col xl:overflow-visible"
        >
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                :data-nav-active="props.activeModule === item.key ? 'true' : null"
                :class="
                    cn(
                        'flex min-h-12 min-w-[132px] snap-start items-center gap-3 rounded-[14px] border px-4 text-sm font-semibold transition sm:min-w-[152px] xl:min-w-0',
                        props.activeModule === item.key
                            ? 'border-[rgba(229,184,73,0.32)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                            : 'border-white/6 bg-[#0E1628] text-[#94A3B8] hover:text-[#F8FAFC]',
                    )
                "
            >
                <component :is="item.icon" class="size-4 shrink-0" />
                <span class="min-w-0 truncate">{{ item.label }}</span>
            </Link>
        </nav>
    </aside>
</template>
