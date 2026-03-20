<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? 'space-y-2' : 'space-y-3'">
        <h2
            :class="
                variant === 'small'
                    ? 'app-kicker'
                    : 'app-display text-[40px] leading-[0.92] text-[#E5B849]'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            :class="
                variant === 'small'
                    ? 'app-body-copy'
                    : 'max-w-sm text-[14px] leading-6 text-[#94A3B8]'
            "
        >
            {{ description }}
        </p>
    </header>
</template>
