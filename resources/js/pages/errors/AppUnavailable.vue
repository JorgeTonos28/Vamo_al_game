<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppHeader from '@/components/AppHeader.vue';
</script>

<template>
    <Head title="Acceso no disponible" />

    <div>
        <AppHeader
            :breadcrumbs="[
                { title: 'Acceso no disponible', href: '/app-unavailable' },
            ]"
        />

        <main class="app-shell-frame flex items-center">
            <section class="app-surface mx-auto w-full max-w-[560px] text-center">
                <p class="app-kicker text-[#E5B849]">Acceso restringido</p>
                <h1 class="mt-4 text-[24px] font-semibold text-[#F8FAFC]">
                    ¡Ups! Lo sentimos, ha ocurrido un problema accediendo a la
                    app. Comuníquese con la administración para más detalles.
                </h1>
            </section>
        </main>
    </div>
</template>
