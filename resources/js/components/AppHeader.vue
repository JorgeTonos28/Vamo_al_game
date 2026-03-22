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
            class="app-shell-width py-[calc(env(safe-area-inset-top)+8px)] md:py-[calc(env(safe-area-inset-top)+6px)]"
        >
            <div class="hidden items-center justify-between gap-6 md:flex">
                <div class="flex min-w-0 flex-1 items-center gap-8">
                    <Link :href="dashboard()" class="shrink-0">
                        <AppLogo compact />
                    </Link>

                    <nav
                        class="app-tab-strip min-w-0 flex-1 items-center gap-6 overflow-visible"
                    >
                        <Link
                            v-for="item in mainNavItems"
                            :key="item.title"
                            :href="item.href"
                            :class="[
                                'app-tab-link min-h-10',
                                {
                                    'is-active': isCurrentOrParentUrl(
                                        item.href,
                                    ),
                                },
                            ]"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <p
                        v-if="currentPage"
                        class="hidden rounded-full border border-[rgba(229,184,73,0.14)] bg-[rgba(229,184,73,0.08)] px-3 py-2 text-[11px] font-bold tracking-[0.12em] text-[#E5B849] uppercase xl:inline-flex"
                    >
                        {{ currentPage.title }}
                    </p>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="secondary"
                                size="icon-sm"
                                class="relative shrink-0 rounded-full border border-white/6 bg-[#131B2F] p-0.5 hover:bg-[#1A243A]"
                            >
                                <Avatar
                                    class="size-[34px] overflow-hidden rounded-full"
                                >
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
            </div>

            <div class="md:hidden">
                <div class="flex items-center justify-between gap-4">
                    <Link :href="dashboard()" class="min-w-0">
                        <AppLogo compact />
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="secondary"
                                size="icon-sm"
                                class="relative rounded-full border border-white/6 bg-[#131B2F] p-0.5 hover:bg-[#1A243A]"
                            >
                                <Avatar
                                    class="size-[34px] overflow-hidden rounded-full"
                                >
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

                <div class="mt-3 flex items-end justify-between gap-4">
                    <nav class="app-tab-strip flex-1">
                        <Link
                            v-for="item in mainNavItems"
                            :key="item.title"
                            :href="item.href"
                            :class="[
                                'app-tab-link',
                                {
                                    'is-active': isCurrentOrParentUrl(
                                        item.href,
                                    ),
                                },
                            ]"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>
                </div>

                <p
                    v-if="currentPage"
                    class="mt-2 text-[11px] font-bold tracking-[0.12em] text-[#E5B849] uppercase"
                >
                    {{ currentPage.title }}
                </p>
            </div>
        </div>
    </div>
</template>
