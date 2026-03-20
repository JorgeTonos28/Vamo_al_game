<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <div class="app-shell-frame flex flex-col justify-center gap-6">
            <Link :href="home()" class="self-start">
                <AppLogo />
            </Link>

            <section class="app-surface space-y-6">
                <div class="space-y-2">
                    <p class="app-kicker">Acceso</p>
                    <h1 class="text-[26px] leading-tight font-semibold text-[#F8FAFC]">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-[14px] leading-6 text-[#94A3B8]"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />
            </section>
        </div>
    </div>
</template>
