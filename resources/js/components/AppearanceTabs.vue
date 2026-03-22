<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Claro' },
    { value: 'dark', Icon: Moon, label: 'Oscuro' },
    { value: 'system', Icon: Monitor, label: 'Sistema' },
] as const;
</script>

<template>
    <div
        class="grid gap-2 rounded-2xl border border-white/6 bg-[#0E1628] p-2 sm:grid-cols-3"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            :class="[
                'flex min-h-12 items-center justify-center gap-2 rounded-xl border px-4 py-3 text-[14px] font-semibold transition-all duration-150 ease-out',
                appearance === value
                    ? 'border-[rgba(229,184,73,0.3)] bg-[rgba(229,184,73,0.14)] text-[#F8FAFC]'
                    : 'border-white/6 bg-[#131B2F] text-[#94A3B8] hover:border-white/10 hover:text-[#F8FAFC]',
            ]"
        >
            <component :is="Icon" class="size-4" />
            <span>{{ label }}</span>
        </button>
    </div>
</template>
