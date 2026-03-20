<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="font-medium text-[#E5B849] underline decoration-[rgba(229,184,73,0.35)] underline-offset-4 transition-colors duration-150 ease-out hover:text-[#f3cb67] hover:decoration-current"
    >
        <slot />
    </Link>
</template>
