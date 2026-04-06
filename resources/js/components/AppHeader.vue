<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
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
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem, NavItem, TenancyContext } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const tenancy = computed(() => page.props.tenancy as TenancyContext | null);
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

const switchLeague = (leagueId: number) => {
    router.post(
        '/active-league',
        { league_id: leagueId },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};
</script>

<template>
    <div
        class="sticky top-0 z-30 border-b border-white/6 bg-[rgba(10,15,29,0.88)] backdrop-blur-md"
    >
        <div
            class="app-shell-width py-[calc(env(safe-area-inset-top)+8px)] md:py-[calc(env(safe-area-inset-top)+6px)]"
        >
            <div class="hidden items-center justify-between gap-4 xl:flex 2xl:gap-6">
                <div class="flex min-w-0 flex-1 items-center gap-4 overflow-hidden 2xl:gap-8">
                    <Link :href="dashboard()" class="shrink-0">
                        <AppLogo compact />
                    </Link>

                    <nav
                        class="scrollbar-none flex min-w-0 flex-1 items-center gap-2 overflow-x-auto pb-1"
                    >
                        <Link
                            v-for="item in mainNavItems"
                            :key="item.title"
                            :href="item.href"
                            :class="[
                                'app-tab-link shrink-0 justify-center rounded-[14px] border border-transparent px-4',
                                {
                                    'is-active border-[rgba(229,184,73,0.2)] bg-[rgba(229,184,73,0.08)]':
                                        isCurrentOrParentUrl(item.href),
                                },
                            ]"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>
                </div>

                <div class="flex shrink-0 items-center gap-2 2xl:gap-3">
                    <DropdownMenu
                        v-if="tenancy?.active_league || tenancy?.guest_mode"
                    >
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="secondary"
                                class="min-h-12 max-w-[220px] rounded-full border border-white/6 bg-[#131B2F] px-4 text-left hover:bg-[#1A243A] 2xl:max-w-[260px]"
                            >
                                <span
                                    :class="
                                        tenancy?.active_league?.is_active === false
                                            ? 'mr-2 app-kicker text-[#FCA5A5]'
                                            : 'mr-2 app-kicker text-[#E5B849]'
                                    "
                                >
                                    <span class="inline-flex max-w-[160px] truncate align-middle 2xl:max-w-[200px]">
                                        {{
                                            tenancy?.active_league
                                                ? tenancy.active_league.name
                                                : 'Modo invitado'
                                        }}
                                    </span>
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <DropdownMenuLabel>
                                {{
                                    tenancy?.active_league
                                        ? 'Cambiar liga'
                                        : 'Cuenta sin liga activa'
                                }}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-if="tenancy?.guest_mode"
                                disabled
                            >
                                Tu usuario solo ve informacion personal hasta
                                que un admin te agregue a una liga.
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-for="league in tenancy?.available_leagues ?? []"
                                :key="league.id"
                                @click="switchLeague(league.id)"
                            >
                                <div class="flex flex-col">
                                    <span>{{ league.name }}</span>
                                    <span class="text-[11px] text-[#94A3B8]">
                                        {{ league.role_label }}
                                        {{
                                            league.is_active
                                                ? ''
                                                : ' - acceso revocado'
                                        }}
                                    </span>
                                </div>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <p
                        v-if="currentPage"
                        class="hidden rounded-full border border-[rgba(229,184,73,0.14)] bg-[rgba(229,184,73,0.08)] px-3 py-2 text-[11px] font-bold tracking-[0.12em] text-[#E5B849] uppercase 2xl:inline-flex"
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

            <div class="xl:hidden">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <Link :href="dashboard()" class="min-w-0">
                            <AppLogo compact />
                        </Link>
                        <p
                            v-if="tenancy?.active_league"
                            :class="
                                tenancy.active_league.is_active
                                    ? 'mt-2 text-[11px] font-bold tracking-[0.12em] text-[#E5B849] uppercase'
                                    : 'mt-2 text-[11px] font-bold tracking-[0.12em] text-[#FCA5A5] uppercase'
                            "
                        >
                            {{
                                tenancy.active_league.is_active
                                    ? tenancy.active_league.name
                                    : `${tenancy.active_league.name} - sin acceso`
                            }}
                        </p>
                        <p
                            v-else-if="tenancy?.guest_mode"
                            class="mt-2 text-[11px] font-bold tracking-[0.12em] text-[#94A3B8] uppercase"
                        >
                            Invitado
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
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
                                        Navegacion
                                    </SheetTitle>
                                </SheetHeader>

                                <nav class="mt-6 flex flex-col gap-2">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
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

                                <div
                                    v-if="tenancy?.active_league || tenancy?.guest_mode"
                                    class="mt-6 rounded-[18px] border border-white/6 bg-[#0E1628] p-4"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        {{
                                            tenancy?.active_league
                                                ? 'Ligas'
                                                : 'Modo actual'
                                        }}
                                    </p>
                                    <p class="mt-2 text-sm font-semibold text-[#F8FAFC]">
                                        {{
                                            tenancy?.active_league
                                                ? tenancy.active_league.name
                                                : 'Invitado'
                                        }}
                                    </p>

                                    <div
                                        v-if="tenancy?.available_leagues?.length"
                                        class="mt-4 flex flex-col gap-2"
                                    >
                                        <Button
                                            v-for="league in tenancy.available_leagues"
                                            :key="league.id"
                                            variant="secondary"
                                            class="min-h-12 justify-start rounded-[14px] border border-white/6 bg-[#131B2F] px-4 text-left hover:bg-[#1A243A]"
                                            @click="switchLeague(league.id)"
                                        >
                                            <span class="flex flex-col">
                                                <span>{{ league.name }}</span>
                                                <span class="text-[11px] text-[#94A3B8]">
                                                    {{ league.role_label }}
                                                    {{
                                                        league.is_active
                                                            ? ''
                                                            : ' - acceso revocado'
                                                    }}
                                                </span>
                                            </span>
                                        </Button>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
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
