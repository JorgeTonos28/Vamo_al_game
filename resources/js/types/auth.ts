export type User = {
    id: number;
    first_name?: string | null;
    last_name?: string | null;
    name: string;
    document_id?: string | null;
    phone?: string | null;
    address?: string | null;
    email: string;
    avatar?: string;
    account_role?: string | null;
    account_role_label?: string | null;
    is_general_admin?: boolean;
    active_league_id?: number | null;
    email_verified_at: string | null;
    invited_at?: string | null;
    onboarded_at?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type LeagueOption = {
    id: number;
    name: string;
    slug: string;
    role: string;
    role_label: string;
    is_active: boolean;
};

export type Branding = {
    logo_url: string | null;
    favicon_url: string | null;
    favicon_type: string | null;
    has_custom_logo: boolean;
    has_custom_favicon: boolean;
    updated_at: string | null;
};

export type Auth = {
    user: User;
};

export type TenancyContext = {
    available_leagues: LeagueOption[];
    active_league: LeagueOption | null;
    can_switch: boolean;
    has_memberships: boolean;
    has_blocked_access: boolean;
    guest_mode: boolean;
    can_access_modules: boolean;
    can_manage_league: boolean;
    is_guest_role: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
