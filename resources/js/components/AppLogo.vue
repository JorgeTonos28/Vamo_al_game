<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex size-10 items-center justify-center rounded-xl border border-white/6 bg-[#E5B849] text-[#0A0F1D]"
    >
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-1.5 grid flex-1 text-left">
        <span
            class="app-display truncate text-[24px] leading-none tracking-[0.08em] text-[#E5B849]"
        >
            Vamo
        </span>
        <span
            class="truncate text-[11px] font-semibold uppercase tracking-[0.22em] text-[#94A3B8]"
        >
            al game
        </span>
    </div>
</template>
