<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { Branding } from '@/types';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const page = usePage();
const branding = computed(() => page.props.branding as Branding | undefined);
</script>

<template>
    <div class="flex items-center">
        <img
            v-if="branding?.has_custom_logo && branding.logo_url"
            :src="branding.logo_url"
            alt="Vamo al Game"
            :class="
                compact
                    ? 'h-9 w-auto max-w-[190px] object-contain'
                    : 'h-12 w-auto max-w-[260px] object-contain'
            "
        />
        <template v-else>
        <div
            :class="
                compact
                    ? 'flex size-9 items-center justify-center rounded-lg border border-white/6 bg-[#E5B849] text-[#0A0F1D]'
                    : 'flex size-10 items-center justify-center rounded-xl border border-white/6 bg-[#E5B849] text-[#0A0F1D]'
            "
        >
            <AppLogoIcon
                :class="
                    compact ? 'size-[18px] fill-current' : 'size-5 fill-current'
                "
            />
        </div>

        <div class="ml-1.5 grid text-left">
            <span
                :class="
                    compact
                        ? 'app-display truncate text-[20px] leading-none tracking-[0.07em] text-[#E5B849]'
                        : 'app-display truncate text-[24px] leading-none tracking-[0.08em] text-[#E5B849]'
                "
            >
                Vamo
            </span>
            <span
                :class="
                    compact
                        ? 'truncate text-[10px] font-semibold tracking-[0.2em] text-[#94A3B8] uppercase'
                        : 'truncate text-[11px] font-semibold tracking-[0.22em] text-[#94A3B8] uppercase'
                "
            >
                al game
            </span>
        </div>
        </template>
    </div>
</template>
