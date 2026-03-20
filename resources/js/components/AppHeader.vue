<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const currentPage = computed(() => props.breadcrumbs.at(-1));
const { isCurrentOrParentUrl } = useCurrentUrl();

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
    },
    {
        title: 'Ajustes',
        href: editProfile(),
    },
];
</script>

<template>
    <div
        class="sticky top-0 z-30 border-b border-white/6 bg-[rgba(10,15,29,0.88)] backdrop-blur-md"
    >
        <div
            class="app-shell-width flex items-center gap-3 pb-4 pt-[calc(env(safe-area-inset-top)+12px)]"
        >
            <Link :href="dashboard()" class="min-w-0 flex-1">
                <AppLogo />
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="secondary"
                        size="icon"
                        class="relative size-12 rounded-full border border-white/6 p-1"
                    >
                        <Avatar class="size-10 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="auth.user.avatar"
                                :src="auth.user.avatar"
                                :alt="auth.user.name"
                            />
                            <AvatarFallback
                                class="rounded-full bg-[#0E1628] font-semibold text-[#F8FAFC]"
                            >
                                {{ getInitials(auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="app-shell-width pb-3">
            <nav class="app-tab-strip">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.title"
                    :href="item.href"
                    :class="[
                        'app-tab-link',
                        { 'is-active': isCurrentOrParentUrl(item.href) },
                    ]"
                >
                    {{ item.title }}
                </Link>
            </nav>

            <p
                v-if="currentPage"
                class="mt-3 text-[12px] font-bold uppercase tracking-[0.08em] text-[#E5B849]"
            >
                {{ currentPage.title }}
            </p>
        </div>
    </div>
</template>
