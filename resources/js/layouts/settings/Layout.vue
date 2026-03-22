<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: editProfile(),
    },
    {
        title: 'Seguridad',
        href: editSecurity(),
    },
    {
        title: 'Apariencia',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="app-page-stack">
        <Heading
            title="Ajustes"
            description="Organiza tu cuenta, seguridad y apariencia desde un centro de control mas claro en desktop, sin perder el ritmo movil."
        />

        <div class="app-settings-shell">
            <aside
                class="app-surface space-y-5 xl:sticky xl:top-24 xl:self-start"
            >
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Centro de control</p>
                    <p class="text-[14px] leading-6 text-[#94A3B8]">
                        Ajusta tus datos, fortalece el acceso y define la
                        apariencia del panel desde una navegacion mas comoda en
                        pantallas amplias.
                    </p>
                </div>

                <nav class="app-settings-nav" aria-label="Settings">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        :class="[
                            'app-settings-link',
                            { 'is-active': isCurrentOrParentUrl(item.href) },
                        ]"
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <section class="app-surface space-y-10 md:space-y-12">
                <slot />
            </section>
        </div>
    </div>
</template>
