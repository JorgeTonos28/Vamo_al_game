<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Menu } from 'lucide-vue-next';
import AppContent from '@/components/AppContent.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppShell from '@/components/AppShell.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type NavItem = {
    title: string;
    href: string;
};

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const { isCurrentOrParentUrl } = useCurrentUrl();

const navItems: NavItem[] = [
    { title: 'Panel', href: '/command-center' },
    { title: 'Usuarios', href: '/command-center/users' },
    { title: 'Ligas', href: '/command-center/leagues' },
    { title: 'Ajustes', href: '/command-center/settings/profile' },
];
</script>

<template>
    <AppShell variant="header">
        <div
            class="sticky top-0 z-30 border-b border-white/6 bg-[rgba(10,15,29,0.92)] backdrop-blur-md"
        >
            <div class="app-shell-width py-[calc(env(safe-area-inset-top)+8px)]">
                <div class="hidden items-center gap-5 xl:flex">
                    <Link href="/command-center" class="shrink-0">
                        <AppLogo compact />
                    </Link>

                    <nav class="grid min-w-0 flex-1 grid-flow-col auto-cols-fr gap-2">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'app-tab-link min-w-0 justify-center rounded-[14px] border border-transparent px-4',
                                {
                                    'is-active border-[rgba(229,184,73,0.2)] bg-[rgba(229,184,73,0.08)]':
                                        isCurrentOrParentUrl(item.href),
                                },
                            ]"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="secondary"
                                size="icon-sm"
                                class="rounded-full border border-white/6 bg-[#131B2F] p-0.5 hover:bg-[#1A243A]"
                            >
                                <Avatar
                                    class="size-[34px] overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="user.avatar"
                                        :src="user.avatar"
                                        :alt="user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-full bg-[#0E1628] font-semibold text-[#F8FAFC]"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex items-center justify-between gap-4 xl:hidden">
                    <div class="min-w-0 flex-1">
                        <Link href="/command-center" class="inline-flex min-w-0">
                            <AppLogo compact />
                        </Link>
                    </div>

                    <div class="flex items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger :as-child="true">
                                <Button
                                    variant="secondary"
                                    size="icon-sm"
                                    class="rounded-full border border-white/6 bg-[#131B2F] p-0.5 hover:bg-[#1A243A]"
                                >
                                    <Avatar
                                        class="size-[34px] overflow-hidden rounded-full"
                                    >
                                        <AvatarImage
                                            v-if="user.avatar"
                                            :src="user.avatar"
                                            :alt="user.name"
                                        />
                                        <AvatarFallback
                                            class="rounded-full bg-[#0E1628] font-semibold text-[#F8FAFC]"
                                        >
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-56">
                                <UserMenuContent :user="user" />
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Sheet>
                            <SheetTrigger :as-child="true">
                                <Button
                                    variant="secondary"
                                    size="icon-sm"
                                    class="rounded-full border border-white/6 bg-[#131B2F] hover:bg-[#1A243A]"
                                >
                                    <Menu class="size-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="right"
                                class="bg-[#131B2F] px-5 py-6"
                            >
                                <SheetHeader class="border-b border-white/6 pb-4">
                                    <SheetTitle class="text-left text-[#F8FAFC]">
                                        Centro de mando
                                    </SheetTitle>
                                </SheetHeader>

                                <nav class="mt-6 flex flex-col gap-2">
                                    <Link
                                        v-for="item in navItems"
                                        :key="item.href"
                                        :href="item.href"
                                        :class="[
                                            'flex min-h-12 items-center rounded-[16px] border px-4 text-sm font-semibold transition',
                                            isCurrentOrParentUrl(item.href)
                                                ? 'border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.12)] text-[#F8FAFC]'
                                                : 'border-white/6 bg-[#0E1628] text-[#94A3B8] hover:text-[#F8FAFC]',
                                        ]"
                                    >
                                        {{ item.title }}
                                    </Link>
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </div>
        </div>

        <AppContent variant="header">
            <slot />
        </AppContent>
    </AppShell>
</template>
