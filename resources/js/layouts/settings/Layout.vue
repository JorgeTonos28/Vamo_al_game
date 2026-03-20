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
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="app-page-stack">
        <Heading
            title="Ajustes"
            description="Controla tu cuenta, seguridad y preferencias dentro del mismo shell mobile-first."
        />

        <nav class="-mx-4 px-4" aria-label="Settings">
            <div class="app-tab-strip">
                <Link
                    v-for="item in sidebarNavItems"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    :class="[
                        'app-tab-link',
                        { 'is-active': isCurrentOrParentUrl(item.href) },
                    ]"
                >
                    {{ item.title }}
                </Link>
            </div>
        </nav>

        <section class="app-surface space-y-12">
            <slot />
        </section>
    </div>
</template>
