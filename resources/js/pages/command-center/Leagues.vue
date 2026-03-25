<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CommandCenterLayout from '@/layouts/CommandCenterLayout.vue';

type LeagueAdmin = {
    id: number | null;
    name: string;
};

type LeagueRow = {
    id: number;
    name: string;
    emoji: string | null;
    slug: string;
    is_active: boolean;
    admins: LeagueAdmin[];
    members_count: number;
    created_at: string | null;
};

const props = defineProps<{
    leagues: LeagueRow[];
}>();

const page = usePage();
const status = computed(
    () =>
        (page.props.flash as { status?: string } | undefined)?.status,
);
const form = useForm({
    name: '',
    emoji: '',
});

const submit = () => {
    form.post('/command-center/leagues', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
};

const toggleLeague = (league: LeagueRow) => {
    router.patch(`/command-center/leagues/${league.id}`, undefined, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Ligas" />

    <CommandCenterLayout>
        <div class="app-page-stack">
            <section class="app-surface space-y-4">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Control de ligas</p>
                    <h1 class="text-[26px] font-semibold text-[#F8FAFC]">
                        Acceso operativo por liga
                    </h1>
                    <p class="app-body-copy">
                        Si una liga queda inactiva, sus administradores y
                        miembros perderan acceso al entorno regular mientras no
                        tengan otra liga activa disponible.
                    </p>
                </div>

                <p v-if="status" class="app-badge-positive inline-flex">
                    {{ status }}
                </p>
            </section>

            <section class="app-surface space-y-5">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Nueva liga</p>
                    <h2 class="text-[22px] font-semibold text-[#F8FAFC]">
                        Crear liga activa
                    </h2>
                    <p class="app-body-copy">
                        El nombre debe ser unico de forma exacta. La liga se crea activa y disponible para asignaciones.
                    </p>
                </div>

                <form
                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="league_name">Nombre de la liga</Label>
                        <Input
                            id="league_name"
                            v-model="form.name"
                            required
                            maxlength="120"
                            placeholder="Liga Aurora"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="league_emoji">Emoji</Label>
                        <Input
                            id="league_emoji"
                            v-model="form.emoji"
                            maxlength="16"
                            placeholder="⚽"
                        />
                        <p class="text-[13px] text-[#94A3B8]">
                            Opcional. Se mostrara junto al nombre de la liga en el shell.
                        </p>
                        <InputError :message="form.errors.emoji" />
                    </div>

                    <div class="md:col-span-2 md:self-end">
                        <Button :disabled="form.processing" class="w-full md:w-auto">
                            {{ form.processing ? 'Creando...' : 'Crear liga' }}
                        </Button>
                    </div>
                </form>
            </section>

            <section class="app-surface">
                <div class="app-divider-list">
                    <article
                        v-for="league in props.leagues"
                        :key="league.id"
                        class="flex flex-col gap-4"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="space-y-2">
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <p
                                        class="text-[18px] font-semibold text-[#F8FAFC]"
                                    >
                                        {{ league.emoji ? `${league.emoji} ${league.name}` : league.name }}
                                    </p>
                                    <span
                                        :class="
                                            league.is_active
                                                ? 'app-badge-positive'
                                                : 'app-badge-negative'
                                        "
                                    >
                                        {{
                                            league.is_active
                                                ? 'Con acceso'
                                                : 'Acceso revocado'
                                        }}
                                    </span>
                                </div>

                                <p class="text-[13px] text-[#94A3B8]">
                                    {{
                                        league.admins
                                            .map((admin) => admin.name)
                                            .join(', ') ||
                                        'Sin administrador asignado'
                                    }}
                                </p>
                            </div>

                            <Button
                                :variant="
                                    league.is_active ? 'destructive' : 'default'
                                "
                                class="w-full lg:w-auto"
                                @click="toggleLeague(league)"
                            >
                                {{
                                    league.is_active
                                        ? 'Revocar acceso'
                                        : 'Restaurar acceso'
                                }}
                            </Button>
                        </div>

                        <div
                            class="grid gap-3 text-[13px] text-[#94A3B8] sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Slug</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.slug }}
                                </p>
                            </div>
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Miembros</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.members_count }}
                                </p>
                            </div>
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Creada</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.created_at ?? 'Sin fecha' }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </CommandCenterLayout>
</template>
