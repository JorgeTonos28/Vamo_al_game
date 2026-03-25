import type { Component } from 'vue';
import {
    ArrowDownToLine,
    BarChart3,
    CalendarRange,
    ClipboardList,
    FileText,
    LayoutDashboard,
    ListOrdered,
    Search,
    Settings,
    Swords,
    Table2,
    Trophy,
    Vote,
    Wallet,
} from 'lucide-vue-next';

export type LeagueModuleKey =
    | 'panel'
    | 'llegada'
    | 'juego'
    | 'cola'
    | 'stats'
    | 'tabla'
    | 'temporada'
    | 'scout'
    | 'torneo'
    | 'anotador'
    | 'votos'
    | 'post'
    | 'gestion'
    | 'ajustes';

export type LeagueNavItem = {
    key: LeagueModuleKey;
    label: string;
    href: string;
    icon: Component;
    adminOnly?: boolean;
};

const items: LeagueNavItem[] = [
    {
        key: 'panel',
        label: 'Panel',
        href: '/liga/panel',
        icon: LayoutDashboard,
    },
    {
        key: 'llegada',
        label: 'Llegada',
        href: '/liga/llegada',
        icon: ArrowDownToLine,
    },
    {
        key: 'juego',
        label: 'Juego',
        href: '/liga/modulos/juego',
        icon: Swords,
    },
    {
        key: 'cola',
        label: 'Cola',
        href: '/liga/modulos/cola',
        icon: ListOrdered,
    },
    {
        key: 'stats',
        label: 'Stats',
        href: '/liga/modulos/stats',
        icon: BarChart3,
    },
    {
        key: 'tabla',
        label: 'Tabla',
        href: '/liga/modulos/tabla',
        icon: Table2,
    },
    {
        key: 'temporada',
        label: 'Temporada',
        href: '/liga/modulos/temporada',
        icon: CalendarRange,
    },
    {
        key: 'scout',
        label: 'Scout',
        href: '/liga/modulos/scout',
        icon: Search,
    },
    {
        key: 'torneo',
        label: 'Torneo',
        href: '/liga/modulos/torneo',
        icon: Trophy,
    },
    {
        key: 'anotador',
        label: 'Anotador',
        href: '/liga/modulos/anotador',
        icon: ClipboardList,
    },
    {
        key: 'votos',
        label: 'Votos',
        href: '/liga/modulos/votos',
        icon: Vote,
    },
    {
        key: 'post',
        label: 'Post',
        href: '/liga/modulos/post',
        icon: FileText,
    },
    {
        key: 'gestion',
        label: 'Gestion',
        href: '/liga/gestion',
        icon: Wallet,
        adminOnly: true,
    },
    {
        key: 'ajustes',
        label: 'Ajustes',
        href: '/settings/profile',
        icon: Settings,
    },
];

export function leagueNavItems(canManageLeague: boolean): LeagueNavItem[] {
    return items.filter((item) => !item.adminOnly || canManageLeague);
}

export function formatMoney(amountCents: number): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP',
        maximumFractionDigits: 2,
        minimumFractionDigits: amountCents % 100 === 0 ? 0 : 2,
    }).format(amountCents / 100);
}

export function formatCompactDate(value: string | null | undefined): string {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}
